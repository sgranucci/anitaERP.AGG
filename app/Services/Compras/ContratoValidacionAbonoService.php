<?php

namespace App\Services\Compras;

use App\Models\Compras\Comprobante_Proveedor;
use App\Models\Compras\Contrato_Validacion_Abono;
use App\Models\Compras\Contrato_Validacion_Abono_Respuesta;
use App\Models\Compras\Ordencompra;
use App\Models\Compras\Validacion_Abono_Plantilla;
use App\Models\Stock\Recepcion_Proveedor;
use App\Services\Configuracion\ModuloAvisoService;
use App\Support\Compras\ContratoPeriodoServicioSupport;
use App\Support\Compras\ContratoValidacionAbonoCumplimientoSupport;
use App\Support\Compras\ContratoValidacionAbonoDocumentoSupport;
use App\Support\Compras\ContratoValidacionAbonoEstados;
use App\Support\Compras\ContratoValidacionAbonoPoliticaSupport;
use App\Support\Seguridad\IngresoProveedorConsultaSupport;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class ContratoValidacionAbonoService
{
    public const AVISO_PENDIENTE = 'contrato_validacion_abono_pendiente';

    public function __construct(
        private readonly ModuloAvisoService $moduloAvisoService,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function politicaDeOc(?Ordencompra $oc): array
    {
        return ContratoValidacionAbonoPoliticaSupport::desdeOc($oc);
    }

    public function deRecepcion(Recepcion_Proveedor $recepcion): ?Contrato_Validacion_Abono
    {
        return Contrato_Validacion_Abono::query()
            ->where('recepcion_proveedor_id', (int) $recepcion->id)
            ->first();
    }

    public function deComprobante(Comprobante_Proveedor $comprobante): ?Contrato_Validacion_Abono
    {
        return Contrato_Validacion_Abono::query()
            ->where('comprobante_proveedor_id', (int) $comprobante->id)
            ->first();
    }

    public function asegurarParaRecepcion(Recepcion_Proveedor $recepcion): ?Contrato_Validacion_Abono
    {
        if (($recepcion->tipo ?? '') === Recepcion_Proveedor::TIPO_DEVOLUCION) {
            return null;
        }
        $recepcion->loadMissing('ordencompras');
        $oc = $recepcion->ordencompras;
        $politica = ContratoValidacionAbonoPoliticaSupport::desdeOc($oc);
        if (! ContratoValidacionAbonoPoliticaSupport::cortaRecepcion($politica)) {
            return $this->deRecepcion($recepcion);
        }

        return $this->asegurar(
            (int) $oc->id,
            $politica,
            (string) ($recepcion->fecha?->format('Y-m-d') ?? ''),
            (int) $recepcion->id,
            null
        );
    }

    public function asegurarParaComprobante(Comprobante_Proveedor $comprobante): ?Contrato_Validacion_Abono
    {
        $comprobante->loadMissing('ordencompras');
        $oc = $comprobante->ordencompras;
        $politica = ContratoValidacionAbonoPoliticaSupport::desdeOc($oc);
        if (! ContratoValidacionAbonoPoliticaSupport::cortaFactura($politica)) {
            return $this->deComprobante($comprobante);
        }

        $fecha = (string) ($comprobante->fechacomprobante?->format('Y-m-d')
            ?? $comprobante->fecharecepcion?->format('Y-m-d')
            ?? '');

        return $this->asegurar(
            (int) $oc->id,
            $politica,
            $fecha,
            null,
            (int) $comprobante->id
        );
    }

    public function assertRecepcionConfirmable(Recepcion_Proveedor $recepcion): void
    {
        if (($recepcion->tipo ?? '') === Recepcion_Proveedor::TIPO_DEVOLUCION) {
            return;
        }
        $recepcion->loadMissing('ordencompras');
        $politica = ContratoValidacionAbonoPoliticaSupport::desdeOc($recepcion->ordencompras);
        if (! ContratoValidacionAbonoPoliticaSupport::cortaRecepcion($politica)) {
            return;
        }

        $validacion = $this->asegurarParaRecepcion($recepcion);
        $resultado = ContratoValidacionAbonoCumplimientoSupport::evaluar(
            $politica,
            $validacion ? $this->aSnapshotConIngresosVivos($validacion, $politica) : null
        );
        if ($resultado['puede_confirmar_com']) {
            return;
        }

        throw new RuntimeException(
            ContratoValidacionAbonoCumplimientoSupport::mensajeBloqueo($resultado, 'confirmar la recepción')
        );
    }

    public function assertComprobanteContabilizable(Comprobante_Proveedor $comprobante): void
    {
        $comprobante->loadMissing('ordencompras');
        $politica = ContratoValidacionAbonoPoliticaSupport::desdeOc($comprobante->ordencompras);
        if (! ContratoValidacionAbonoPoliticaSupport::cortaFactura($politica)) {
            return;
        }

        $validacion = $this->asegurarParaComprobante($comprobante);
        $resultado = ContratoValidacionAbonoCumplimientoSupport::evaluar(
            $politica,
            $validacion ? $this->aSnapshotConIngresosVivos($validacion, $politica) : null
        );
        if ($resultado['puede_contabilizar_fac']) {
            return;
        }

        throw new RuntimeException(
            ContratoValidacionAbonoCumplimientoSupport::mensajeBloqueo($resultado, 'contabilizar la factura')
        );
    }

    public function eliminarDeRecepcion(int $recepcionId): int
    {
        if ($recepcionId <= 0) {
            return 0;
        }

        return $this->eliminarPorDocumento('recepcion_proveedor_id', $recepcionId);
    }

    public function eliminarDeComprobante(int $comprobanteId): int
    {
        if ($comprobanteId <= 0) {
            return 0;
        }

        return $this->eliminarPorDocumento('comprobante_proveedor_id', $comprobanteId);
    }

    /**
     * Borra validaciones cuya COM o factura ya no existe.
     */
    public function purgarHuerfanas(?int $ordencompraId = null): int
    {
        $query = Contrato_Validacion_Abono::query()
            ->when($ordencompraId && $ordencompraId > 0, fn ($q) => $q->where('ordencompra_id', $ordencompraId));

        $ids = [];
        foreach ($query->get(['id', 'recepcion_proveedor_id', 'comprobante_proveedor_id']) as $validacion) {
            $rp = (int) ($validacion->recepcion_proveedor_id ?? 0);
            $cp = (int) ($validacion->comprobante_proveedor_id ?? 0);
            $rpExiste = $rp > 0 && Recepcion_Proveedor::query()->whereKey($rp)->exists();
            $cpExiste = $cp > 0 && Comprobante_Proveedor::query()->whereKey($cp)->exists();
            if (ContratoValidacionAbonoDocumentoSupport::esHuerfana($rp ?: null, $rpExiste, $cp ?: null, $cpExiste)) {
                $ids[] = (int) $validacion->id;
            }
        }

        return $this->eliminarPorIds($ids, [
            'motivo' => 'huerfana',
            'ordencompra_id' => $ordencompraId,
        ]);
    }

    /**
     * @return list<string>
     */
    public function erroresEnvioCuentasAPagar(Ordencompra $oc): array
    {
        $this->purgarHuerfanas((int) $oc->id);

        $politica = ContratoValidacionAbonoPoliticaSupport::desdeOc($oc);
        if (! ($politica['aplica'] ?? false)) {
            return [];
        }

        $pendientes = Contrato_Validacion_Abono::query()
            ->conDocumentoVivo()
            ->where('ordencompra_id', (int) $oc->id)
            ->where('estado', ContratoValidacionAbonoEstados::PENDIENTE)
            ->count();
        if ($pendientes > 0) {
            return [
                'Hay '.$pendientes.' validación(es) de abono pendiente(s) de confirmar. '
                .'El legajo no se puede enviar a Cuentas a pagar hasta completarlas.',
            ];
        }

        $incumplidas = Contrato_Validacion_Abono::query()
            ->conDocumentoVivo()
            ->where('ordencompra_id', (int) $oc->id)
            ->where('estado', ContratoValidacionAbonoEstados::COMPLETA)
            ->get();
        foreach ($incumplidas as $validacion) {
            $resultado = ContratoValidacionAbonoCumplimientoSupport::evaluar(
                $politica,
                $this->aSnapshotConIngresosVivos($validacion, $politica)
            );
            if (! $resultado['puede_enviar_cxp']) {
                return $resultado['errores'];
            }
        }

        return [];
    }

    /**
     * @param  array<int, array{valor?: mixed, comentario?: mixed}>  $respuestas
     */
    public function confirmar(Contrato_Validacion_Abono $validacion, array $respuestas, int $usuarioId): Contrato_Validacion_Abono
    {
        if ($validacion->estaCompleta()) {
            throw new RuntimeException('La validación ya está completa.');
        }

        $validacion->loadMissing(['plantillas.preguntas', 'ordencompras']);
        $plantilla = $validacion->plantillas;
        if (! $plantilla) {
            throw new RuntimeException('La plantilla de validación no existe.');
        }

        $preguntas = $plantilla->preguntas;
        if ($preguntas->isEmpty()) {
            throw new RuntimeException('La plantilla no tiene preguntas.');
        }

        $ingresos = 0;
        $ticketRespondidoSi = false;

        DB::transaction(function () use ($validacion, $preguntas, $respuestas, $usuarioId, &$ingresos, &$ticketRespondidoSi) {
            Contrato_Validacion_Abono_Respuesta::query()
                ->where('contrato_validacion_abono_id', (int) $validacion->id)
                ->delete();

            foreach ($preguntas as $pregunta) {
                $raw = $respuestas[(int) $pregunta->id] ?? [];
                $valor = strtolower(trim((string) ($raw['valor'] ?? '')));
                if (! in_array($valor, ['si', 'no'], true)) {
                    throw new RuntimeException('Responda Sí o No: '.$pregunta->enunciado);
                }
                $comentario = trim((string) ($raw['comentario'] ?? ''));
                $exigeComentario = strtolower((string) ($pregunta->comentario_si_valor ?? '')) === $valor;
                if ($exigeComentario && $comentario === '') {
                    throw new RuntimeException('El comentario es obligatorio para: '.$pregunta->enunciado);
                }

                Contrato_Validacion_Abono_Respuesta::query()->create([
                    'contrato_validacion_abono_id' => (int) $validacion->id,
                    'pregunta_id' => (int) $pregunta->id,
                    'valor' => $valor,
                    'comentario' => $comentario !== '' ? $comentario : null,
                ]);

                if ((bool) $pregunta->es_tickets || $pregunta->codigo === ContratoValidacionAbonoEstados::CODIGO_TICKETS) {
                    $ticketRespondidoSi = $valor === 'si';
                }
            }

            $politica = ContratoValidacionAbonoPoliticaSupport::desdeOc($validacion->ordencompras);
            $minimo = max(1, (int) $politica['minimo_ingresos']);
            $oc = $validacion->ordencompras;
            $conteoTickets = IngresoProveedorConsultaSupport::cantidadEnPeriodo(
                (int) ($oc->proveedor_id ?? 0),
                (string) ($validacion->periodo_desde?->format('Y-m-d') ?? ''),
                (string) ($validacion->periodo_hasta?->format('Y-m-d') ?? ''),
                (int) ($oc->empresa_id ?? 0) ?: null,
                (int) ($oc->id ?? 0) ?: null
            );
            $ingresos = $conteoTickets;
            $fuente = 'ingreso_proveedor';

            $validacion->forceFill([
                'estado' => ContratoValidacionAbonoEstados::COMPLETA,
                'ingresos_informados' => $ingresos,
                'snapshot_ingresos_json' => [
                    'fuente' => $fuente,
                    'tickets_si' => $ticketRespondidoSi,
                    'cantidad' => $ingresos,
                    'conteo_tickets' => $conteoTickets,
                    'minimo' => $minimo,
                ],
                'usuario_id' => $usuarioId > 0 ? $usuarioId : null,
                'confirmado_at' => now(),
            ])->save();
        });

        return $validacion->fresh(['respuestas', 'usuarios', 'plantillas.preguntas']);
    }

    /**
     * @param  array<string, mixed>  $politica
     */
    private function asegurar(
        int $ordencompraId,
        array $politica,
        string $fechaYmd,
        ?int $recepcionId,
        ?int $comprobanteId,
    ): Contrato_Validacion_Abono {
        $existente = Contrato_Validacion_Abono::query()
            ->when($recepcionId, fn ($q) => $q->where('recepcion_proveedor_id', $recepcionId))
            ->when($comprobanteId, fn ($q) => $q->where('comprobante_proveedor_id', $comprobanteId))
            ->first();

        $ventana = ContratoPeriodoServicioSupport::ventana($politica['periodo'], $fechaYmd);
        $plantillaId = $this->resolverPlantillaId((int) $politica['plantilla_id']);

        if ($existente) {
            if (! $existente->estaCompleta()) {
                $existente->forceFill([
                    'periodo_modalidad' => $ventana['modalidad'],
                    'periodo_desde' => $ventana['desde'],
                    'periodo_hasta' => $ventana['hasta'],
                    'plantilla_id' => $plantillaId,
                ])->save();
            }
            $this->enviarAvisoPendiente($existente);

            return $existente->fresh();
        }

        $validacion = Contrato_Validacion_Abono::query()->create([
            'ordencompra_id' => $ordencompraId,
            'recepcion_proveedor_id' => $recepcionId,
            'comprobante_proveedor_id' => $comprobanteId,
            'plantilla_id' => $plantillaId,
            'estado' => ContratoValidacionAbonoEstados::PENDIENTE,
            'periodo_modalidad' => $ventana['modalidad'],
            'periodo_desde' => $ventana['desde'],
            'periodo_hasta' => $ventana['hasta'],
            'ingresos_informados' => 0,
        ]);

        $this->enviarAvisoPendiente($validacion);

        return $validacion;
    }

    private function resolverPlantillaId(int $plantillaId): int
    {
        if ($plantillaId > 0 && Validacion_Abono_Plantilla::query()->whereKey($plantillaId)->exists()) {
            return $plantillaId;
        }

        $id = (int) (Validacion_Abono_Plantilla::query()->where('codigo', 'estandar')->value('id') ?? 0);
        if ($id <= 0) {
            throw new RuntimeException('No hay plantilla de validación de abono. Ejecute las migraciones.');
        }

        return $id;
    }

    private function enviarAvisoPendiente(Contrato_Validacion_Abono $validacion): void
    {
        if ($validacion->estaCompleta() || $validacion->aviso_pendiente_enviado_at) {
            return;
        }

        $this->moduloAvisoService->enviar('compras', self::AVISO_PENDIENTE, (int) $validacion->id);
        $validacion->forceFill(['aviso_pendiente_enviado_at' => now()])->save();
    }

    /**
     * @return array{estado: string, ingresos_informados: int}
     */
    private function aSnapshot(Contrato_Validacion_Abono $validacion): array
    {
        return [
            'estado' => (string) $validacion->estado,
            'ingresos_informados' => (int) $validacion->ingresos_informados,
        ];
    }

    /**
     * Al cortar COM/FAC se cuenta el ingreso real, no el número guardado en el cuestionario.
     *
     * @param  array<string, mixed>  $politica
     * @return array{estado: string, ingresos_informados: int}
     */
    private function aSnapshotConIngresosVivos(Contrato_Validacion_Abono $validacion, array $politica): array
    {
        $snapshot = $this->aSnapshot($validacion);
        if (! ($politica['exige_ingresos'] ?? false)) {
            return $snapshot;
        }

        $validacion->loadMissing('ordencompras');
        $oc = $validacion->ordencompras;
        $snapshot['ingresos_informados'] = IngresoProveedorConsultaSupport::cantidadEnPeriodo(
            (int) ($oc->proveedor_id ?? 0),
            (string) ($validacion->periodo_desde?->format('Y-m-d') ?? ''),
            (string) ($validacion->periodo_hasta?->format('Y-m-d') ?? ''),
            (int) ($oc->empresa_id ?? 0) ?: null,
            (int) ($oc->id ?? $validacion->ordencompra_id ?? 0) ?: null
        );

        return $snapshot;
    }

    private function eliminarPorDocumento(string $columna, int $documentoId): int
    {
        $ids = Contrato_Validacion_Abono::query()
            ->where($columna, $documentoId)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        return $this->eliminarPorIds($ids, [
            'motivo' => 'documento_eliminado',
            'columna' => $columna,
            'documento_id' => $documentoId,
        ]);
    }

    /**
     * @param  list<int>  $ids
     * @param  array<string, mixed>  $contexto
     */
    private function eliminarPorIds(array $ids, array $contexto = []): int
    {
        $ids = array_values(array_unique(array_filter(
            array_map('intval', $ids),
            static fn (int $id) => $id > 0
        )));
        if ($ids === []) {
            return 0;
        }

        Contrato_Validacion_Abono_Respuesta::query()
            ->whereIn('contrato_validacion_abono_id', $ids)
            ->delete();
        $borradas = Contrato_Validacion_Abono::query()->whereIn('id', $ids)->delete();
        if ($borradas > 0) {
            Log::info('contrato_validacion_abono.eliminadas', array_merge($contexto, [
                'ids' => $ids,
                'cantidad' => $borradas,
            ]));
        }

        return $borradas;
    }

    public function usuarioActualId(): int
    {
        return (int) (Auth::id() ?? 0);
    }
}
