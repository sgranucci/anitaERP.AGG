<?php

namespace App\Services\Configuracion;

use App\Models\Configuracion\Arbolaprobacion;
use App\Models\Configuracion\Arbolaprobacion_Movimiento;
use App\Models\Solicitudpago\Solicitudpago;
use App\Support\Configuracion\ArbolAprobacionContextoSupport;
use App\Support\Configuracion\ArbolAprobacionEnlaceSupport;
use App\Support\Navegacion\ModoConsultaUrlSupport;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;
use RuntimeException;

/**
 * Bandeja unificada de pendientes del árbol (sesión autenticada).
 * Reutiliza ArbolaprobacionService::aprobar / rechazar; no usa hashes públicos.
 */
class MisAprobacionesArbolService
{
    public function __construct(
        private ArbolaprobacionService $arbolaprobacionService,
    ) {}

    public function contarPendientes(int $usuarioId): int
    {
        if ($usuarioId <= 0) {
            return 0;
        }

        return $this->queryPendientes($usuarioId)->count();
    }

    /**
     * @param  array{tipo?: string|null}  $filtros
     * @return Collection<int, array<string, mixed>>
     */
    public function listarPendientes(int $usuarioId, array $filtros = []): Collection
    {
        if ($usuarioId <= 0) {
            return collect();
        }

        $tipoFiltro = strtoupper(trim((string) ($filtros['tipo'] ?? '')));

        $movimientos = $this->queryPendientes($usuarioId)->get();
        $reemplazos = $this->mapaReemplazosActivos($usuarioId);

        $items = collect();
        foreach ($movimientos as $mov) {
            try {
                $ref = $this->referenciaMovimiento($mov);
                if ($ref['tipo'] === '' || $ref['comprobante_id'] <= 0) {
                    continue;
                }
                if (! $this->pasaFiltroTipo($ref, $tipoFiltro)) {
                    continue;
                }

                $items->push($this->enriquecer($mov, $ref, $reemplazos));
            } catch (\Throwable $e) {
                // Un comprobante borrado / inaccesible no debe tumbar toda la bandeja (Laravel lo
                // convertiría en 404 vía ModelNotFoundException).
                \Log::warning('mis_aprobaciones_item_omitido', [
                    'movimiento_id' => (int) $mov->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $items->sortByDesc(fn (array $row) => (int) ($row['movimiento_id'] ?? 0))->values();
    }

    /**
     * @return array{mensaje: string}
     */
    public function aprobar(int $movimientoId, int $usuarioId, ?string $observacion = null): array
    {
        $mov = $this->movimientoPendienteDeUsuario($movimientoId, $usuarioId);
        $ref = $this->referenciaMovimiento($mov);
        $this->assertReferenciaValida($ref);

        $documento = $this->arbolaprobacionService->cargarDocumentoArbol($ref['tipo'], $ref['comprobante_id']);
        if (! $documento) {
            throw new RuntimeException(
                'No se puede aprobar: el comprobante '.$ref['tipo'].' #'.$ref['comprobante_id'].' ya no existe en el sistema (pendiente huérfano).'
            );
        }

        if ($ref['tipo'] === 'SP' && $this->esAvisoPagoSp($ref['comprobante_id'], (int) $mov->nivel)) {
            throw new RuntimeException(
                'Nivel aviso a pagadores: no se aprueba por el árbol. Use Ingresos/egresos o Rechazar.'
            );
        }

        $snapshot = $this->arbolaprobacionService->snapshotDocumentoArbol($ref['tipo'], $documento);
        $numero = (string) ($snapshot['numero'] ?? $ref['comprobante_id']);

        $resultado = $this->arbolaprobacionService->aprobar(
            $ref['tipo'],
            $ref['comprobante_id'],
            $mov->id,
            $usuarioId,
            ArbolAprobacionEnlaceSupport::observacionConCanal($observacion, 'bandeja')
        );

        $movFresh = Arbolaprobacion_Movimiento::query()->find($mov->id);
        $estadoOk = $movFresh && $movFresh->estado === 'Aprobado';

        return array_merge(is_array($resultado) ? $resultado : [], [
            'tipo' => $ref['tipo'],
            'numero' => $numero,
            'movimiento_id' => (int) $mov->id,
            'aprobado_ok' => $estadoOk,
        ]);
    }

    public function rechazar(int $movimientoId, int $usuarioId, ?string $observacion = null): void
    {
        $mov = $this->movimientoPendienteDeUsuario($movimientoId, $usuarioId);
        $ref = $this->referenciaMovimiento($mov);
        $this->assertReferenciaValida($ref);

        $obs = trim((string) ($observacion ?? ''));
        if ($obs === '') {
            throw new InvalidArgumentException('Indicá el motivo del rechazo.');
        }

        $this->arbolaprobacionService->rechazar(
            $ref['tipo'],
            $ref['comprobante_id'],
            $mov->id,
            $usuarioId,
            ArbolAprobacionEnlaceSupport::observacionConCanal($obs, 'bandeja')
        );
    }

    /**
     * Reenvía el mail de aprobación/rechazo del pendiente (mismo formato que el árbol).
     *
     * @param  array<string, mixed>  $extras
     */
    public function reenviarCorreoPendiente(Arbolaprobacion_Movimiento $mov, array $extras = []): void
    {
        $ref = $this->referenciaMovimiento($mov);
        if ($ref['tipo'] === '' || $ref['comprobante_id'] <= 0) {
            throw new RuntimeException('Movimiento sin comprobante asociado.');
        }

        $documento = $this->arbolaprobacionService->cargarDocumentoArbol($ref['tipo'], $ref['comprobante_id']);
        if (! $documento) {
            throw new RuntimeException(
                'No se puede reenviar: el comprobante '.$ref['tipo'].' #'.$ref['comprobante_id'].' no existe.'
            );
        }

        $idx = array_search($ref['tipo'], array_column(\App\Models\Configuracion\Arbolaprobacion::$enumTipoArbol, 'valor'));
        $tipoNombre = $idx === false
            ? $ref['tipo']
            : (string) \App\Models\Configuracion\Arbolaprobacion::$enumTipoArbol[$idx]['nombre'];

        $ip = (string) config('arbolaprobacion.ip_link');
        $linkAprobacion = ArbolAprobacionEnlaceSupport::enlaceAprobar(
            $ip,
            $ref['tipo'],
            $ref['comprobante_id'],
            (string) $mov->hashaprobacion
        );
        $linkRechazo = ArbolAprobacionEnlaceSupport::enlaceRechazo(
            $ip,
            $ref['tipo'],
            $ref['comprobante_id'],
            (string) $mov->hashrechazo
        );
        $linkVisualizar = ArbolAprobacionEnlaceSupport::enlaceVisualizar(
            $ip,
            $ref['ruta_visualizar'],
            $ref['comprobante_id'],
            (string) $mov->hashvisualizar
        );

        $mailExtras = array_merge([
            'link_bandeja' => urlAppAbsoluta('mis-aprobaciones'),
        ], $extras);

        $this->arbolaprobacionService->enviaCorreo(
            (int) $mov->destinatariousuario_id,
            $tipoNombre,
            $documento,
            $linkAprobacion,
            $linkRechazo,
            $linkVisualizar,
            $mailExtras
        );

        $mov->fechaenvio = Carbon::now();
        $mov->save();
    }

    public function reenviarCorreoDesdeBandeja(int $movimientoId, int $usuarioId): void
    {
        $mov = $this->movimientoPendienteDeUsuario($movimientoId, $usuarioId);
        $this->reenviarCorreoPendiente($mov, ['es_recordatorio' => true]);
    }

    /**
     * Marca como «Sin efecto» los pendientes del usuario cuyo comprobante ya no existe.
     *
     * @return array{limpiados: int, revisados: int}
     */
    public function limpiarHuerfanos(int $usuarioId): array
    {
        if ($usuarioId <= 0) {
            return ['limpiados' => 0, 'revisados' => 0];
        }

        $nombrePendiente = Arbolaprobacion_Movimiento::$enumEstado[
            array_search('P', array_column(Arbolaprobacion_Movimiento::$enumEstado, 'valor'))
        ]['nombre'];
        $nombreSinEfecto = Arbolaprobacion_Movimiento::$enumEstado[
            array_search('X', array_column(Arbolaprobacion_Movimiento::$enumEstado, 'valor'))
        ]['nombre'];

        $movimientos = $this->queryPendientes($usuarioId)->get();
        $limpiados = 0;

        foreach ($movimientos as $mov) {
            $ref = $this->referenciaMovimiento($mov);
            if ($ref['tipo'] === '' || $ref['comprobante_id'] <= 0) {
                $this->marcarSinEfectoHuerfano($mov, $nombrePendiente, $nombreSinEfecto, 'Sin comprobante asociado');
                $limpiados++;

                continue;
            }

            $documento = $this->arbolaprobacionService->cargarDocumentoArbol($ref['tipo'], $ref['comprobante_id']);
            if ($documento !== null) {
                continue;
            }

            $this->marcarSinEfectoHuerfano(
                $mov,
                $nombrePendiente,
                $nombreSinEfecto,
                'Pendiente huérfano: '.$ref['tipo'].' #'.$ref['comprobante_id'].' inexistente (limpieza bandeja)'
            );
            $limpiados++;
        }

        return ['limpiados' => $limpiados, 'revisados' => $movimientos->count()];
    }

    /**
     * Descarta un único pendiente huérfano del usuario.
     */
    public function descartarHuerfano(int $movimientoId, int $usuarioId): void
    {
        $mov = $this->movimientoPendienteDeUsuario($movimientoId, $usuarioId);
        $ref = $this->referenciaMovimiento($mov);
        $documento = ($ref['tipo'] !== '' && $ref['comprobante_id'] > 0)
            ? $this->arbolaprobacionService->cargarDocumentoArbol($ref['tipo'], $ref['comprobante_id'])
            : null;

        if ($documento !== null) {
            throw new RuntimeException('Este pendiente tiene documento vigente; usá Aprobar o Rechazar.');
        }

        $nombrePendiente = Arbolaprobacion_Movimiento::$enumEstado[
            array_search('P', array_column(Arbolaprobacion_Movimiento::$enumEstado, 'valor'))
        ]['nombre'];
        $nombreSinEfecto = Arbolaprobacion_Movimiento::$enumEstado[
            array_search('X', array_column(Arbolaprobacion_Movimiento::$enumEstado, 'valor'))
        ]['nombre'];

        $detalle = $ref['tipo'] !== ''
            ? $ref['tipo'].' #'.$ref['comprobante_id'].' inexistente'
            : 'sin comprobante';

        $this->marcarSinEfectoHuerfano(
            $mov,
            $nombrePendiente,
            $nombreSinEfecto,
            'Pendiente huérfano descartado: '.$detalle
        );
    }

    private function marcarSinEfectoHuerfano(
        Arbolaprobacion_Movimiento $mov,
        string $nombrePendiente,
        string $nombreSinEfecto,
        string $observacion
    ): void {
        Arbolaprobacion_Movimiento::query()
            ->where('id', $mov->id)
            ->where('estado', $nombrePendiente)
            ->update([
                'estado' => $nombreSinEfecto,
                'fechaproceso' => Carbon::now(),
                'observacion' => $observacion,
            ]);
    }

    /**
     * @return array{tipo: string, comprobante_id: int, ruta_visualizar: string, ruta_editar: ?string}
     */
    public function referenciaMovimiento(Arbolaprobacion_Movimiento $mov): array
    {
        if ((int) ($mov->requisicion_id ?? 0) > 0) {
            return [
                'tipo' => 'RE',
                'comprobante_id' => (int) $mov->requisicion_id,
                'ruta_visualizar' => 'compras/requisicion/visualizar',
                'ruta_editar' => 'editar_requisicion',
            ];
        }
        if ((int) ($mov->ordencompra_id ?? 0) > 0) {
            $tipoArbol = (string) (Arbolaprobacion::query()
                ->whereKey((int) ($mov->arbolaprobacion_id ?? 0))
                ->value('tipoarbol') ?? '');
            $esSu = $tipoArbol === 'Suscripciones';

            return [
                'tipo' => $esSu ? 'SU' : 'OC',
                'comprobante_id' => (int) $mov->ordencompra_id,
                'ruta_visualizar' => $esSu ? 'compras/suscripciones' : 'compras/ordencompra/visualizar',
                'ruta_editar' => $esSu ? 'ver_suscripcion' : 'editar_ordencompra',
            ];
        }
        if ((int) ($mov->solicitudpago_id ?? 0) > 0) {
            return [
                'tipo' => 'SP',
                'comprobante_id' => (int) $mov->solicitudpago_id,
                'ruta_visualizar' => 'solicitudpago/solicitudpago/visualizar',
                'ruta_editar' => 'editar_solicitudpago',
            ];
        }
        if ((int) ($mov->ordenventa_id ?? 0) > 0) {
            return [
                'tipo' => 'OV',
                'comprobante_id' => (int) $mov->ordenventa_id,
                'ruta_visualizar' => 'ordenventa/visualizar',
                'ruta_editar' => 'edita_ordenventa',
            ];
        }
        if ((int) ($mov->requisicion_sala_id ?? 0) > 0) {
            return [
                'tipo' => 'RS',
                'comprobante_id' => (int) $mov->requisicion_sala_id,
                'ruta_visualizar' => 'sala/requisicion-sala/visualizar',
                'ruta_editar' => 'editar_requisicion_sala',
            ];
        }
        if ((int) ($mov->pedido_id ?? 0) > 0) {
            return [
                'tipo' => 'PE',
                'comprobante_id' => (int) $mov->pedido_id,
                'ruta_visualizar' => 'ventas/pedido/visualizar',
                'ruta_editar' => 'editar_pedido',
            ];
        }
        if ((int) ($mov->propuesta_pago_id ?? 0) > 0) {
            return [
                'tipo' => 'PP',
                'comprobante_id' => (int) $mov->propuesta_pago_id,
                'ruta_visualizar' => 'compras/propuesta-pago',
                'ruta_editar' => 'editar_propuesta_pago',
            ];
        }
        if ((int) ($mov->articulo_id ?? 0) > 0) {
            return [
                'tipo' => 'AR',
                'comprobante_id' => (int) $mov->articulo_id,
                'ruta_visualizar' => 'editar_articulo',
                'ruta_editar' => 'editar_articulo',
            ];
        }

        return ['tipo' => '', 'comprobante_id' => 0, 'ruta_visualizar' => '', 'ruta_editar' => null];
    }

    private function queryPendientes(int $usuarioId)
    {
        $nombrePendiente = Arbolaprobacion_Movimiento::$enumEstado[
            array_search('P', array_column(Arbolaprobacion_Movimiento::$enumEstado, 'valor'))
        ]['nombre'];

        return Arbolaprobacion_Movimiento::query()
            ->where('destinatariousuario_id', $usuarioId)
            ->where('estado', $nombrePendiente)
            ->orderByDesc('id');
    }

    private function movimientoPendienteDeUsuario(int $movimientoId, int $usuarioId): Arbolaprobacion_Movimiento
    {
        $nombrePendiente = Arbolaprobacion_Movimiento::$enumEstado[
            array_search('P', array_column(Arbolaprobacion_Movimiento::$enumEstado, 'valor'))
        ]['nombre'];

        $mov = Arbolaprobacion_Movimiento::query()
            ->where('id', $movimientoId)
            ->where('destinatariousuario_id', $usuarioId)
            ->where('estado', $nombrePendiente)
            ->first();

        if (! $mov) {
            throw new RuntimeException(
                'El pendiente ya no está disponible (puede haber sido resuelto por otro firmante o reasignado).'
            );
        }

        return $mov;
    }

    /**
     * @param  array{tipo: string, comprobante_id: int, ruta_visualizar: string, ruta_editar: ?string}  $ref
     * @param  array<int, string>  $reemplazos  titular_id => nombre
     * @return array<string, mixed>
     */
    private function enriquecer(Arbolaprobacion_Movimiento $mov, array $ref, array $reemplazos): array
    {
        $documento = null;
        $snapshot = null;
        try {
            $documento = $this->arbolaprobacionService->cargarDocumentoArbol($ref['tipo'], $ref['comprobante_id']);
            $snapshot = $documento
                ? $this->arbolaprobacionService->snapshotDocumentoArbol($ref['tipo'], $documento)
                : null;
        } catch (\Throwable) {
            $documento = null;
            $snapshot = null;
        }
        $documentoExiste = $documento !== null;

        $esAvisoPago = false;
        try {
            $esAvisoPago = $documentoExiste && $ref['tipo'] === 'SP'
                && $this->esAvisoPagoSp($ref['comprobante_id'], (int) $mov->nivel);
        } catch (\Throwable) {
            $esAvisoPago = false;
        }

        $urlEditar = null;
        if ($documentoExiste && ! empty($ref['ruta_editar']) && Route::has($ref['ruta_editar'])) {
            $urlEditar = ModoConsultaUrlSupport::route($ref['ruta_editar'], [
                'id' => $ref['comprobante_id'],
            ]);
        }
        // Suscripciones: Ver apunta al módulo dedicado si existe la ruta.
        if ($documentoExiste
            && $ref['tipo'] === 'OC'
            && $documento instanceof \App\Models\Compras\Ordencompra
            && (bool) ($documento->es_suscripcion ?? false)
            && Route::has('ver_suscripcion')
        ) {
            $urlEditar = route('ver_suscripcion', $ref['comprobante_id']);
        }
        // Bandeja autenticada: Ver = editar en modo consulta (2ª solapa sin menú).
        $urlVer = $urlEditar;

        $monedaAbrev = '';
        if ($documento && isset($documento->monedas)) {
            $monedaAbrev = (string) (optional($documento->monedas)->abreviatura ?? '');
        } elseif ($documento && isset($documento->moneda)) {
            $monedaAbrev = (string) (optional($documento->moneda)->abreviatura ?? '');
        }

        $reemplazoDe = null;
        $obs = (string) ($mov->observacion ?? '');
        if ($reemplazos !== [] && str_contains($obs, 'Reasignado por reemplazo')) {
            $reemplazoDe = implode(', ', array_values($reemplazos));
        }

        $fechaEnvio = $mov->fechaenvio ? Carbon::parse($mov->fechaenvio) : null;
        $diasPendiente = $fechaEnvio ? max(0, $fechaEnvio->copy()->startOfDay()->diffInDays(Carbon::now()->startOfDay())) : 0;
        $urgencia = 'normal';
        if ($diasPendiente >= 5) {
            $urgencia = 'urgente';
        } elseif ($diasPendiente >= 2) {
            $urgencia = 'atencion';
        }

        $sla = $this->calcularSlaArbol($mov, $fechaEnvio, $diasPendiente, $urgencia);

        return [
            'movimiento_id' => (int) $mov->id,
            'tipo' => $this->tipoBandejaDesdeReferencia($ref, $documento),
            'etiqueta_tipo' => $snapshot['etiqueta_tipo']
                ?? ArbolAprobacionContextoSupport::etiquetaTipo($ref['tipo']),
            'comprobante_id' => $ref['comprobante_id'],
            'numero' => $snapshot['numero'] ?? ('#'.$ref['comprobante_id']),
            'monto' => (float) ($snapshot['monto'] ?? 0),
            'moneda_abrev' => $monedaAbrev,
            'nivel' => (int) ($mov->nivel ?? 0),
            'fecha_envio' => $mov->fechaenvio,
            'dias_pendiente' => $diasPendiente,
            'urgencia' => $urgencia,
            'documento_existe' => $documentoExiste,
            'es_aviso_pago' => $esAvisoPago,
            'puede_aprobar' => $documentoExiste && ! $esAvisoPago,
            'url_ver' => $urlVer,
            'url_editar' => $urlEditar,
            'url_aprobar' => url('mis-aprobaciones/'.$mov->id.'/aprobar'),
            'url_rechazar' => url('mis-aprobaciones/'.$mov->id.'/rechazar'),
            'url_descartar' => url('mis-aprobaciones/'.$mov->id.'/descartar-huerfano'),
            'url_reenviar' => url('mis-aprobaciones/'.$mov->id.'/reenviar'),
            'reemplazo_de' => $reemplazoDe,
            'es_reemplazo' => $reemplazoDe !== null,
            'sla_label' => $sla['label'],
            'sla_estado' => $sla['estado'],
            'sla_fecha_limite' => $sla['fecha_limite'],
            'dias_para_vencer' => $sla['dias_para_vencer'],
            'observacion' => $obs !== '' ? $obs : null,
        ];
    }

    /**
     * Filtro de bandeja: SU = árbol Suscripciones; OC = órdenes de compra (no suscripción).
     *
     * @param  array{tipo: string, comprobante_id: int}  $ref
     */
    private function pasaFiltroTipo(array $ref, string $tipoFiltro): bool
    {
        if ($tipoFiltro === '') {
            return true;
        }

        if ($tipoFiltro === 'SU') {
            return $ref['tipo'] === 'SU';
        }

        if ($tipoFiltro === 'OC') {
            return $ref['tipo'] === 'OC';
        }

        return $ref['tipo'] === $tipoFiltro;
    }

    private function esOrdencompraSuscripcion(int $ordencompraId): bool
    {
        if ($ordencompraId <= 0) {
            return false;
        }

        return (bool) \App\Models\Compras\Ordencompra::query()
            ->where('id', $ordencompraId)
            ->where('es_suscripcion', true)
            ->exists();
    }

    /**
     * Código mostrado en bandeja (SU = circuito Suscripciones).
     *
     * @param  array{tipo: string, comprobante_id: int}  $ref
     */
    private function tipoBandejaDesdeReferencia(array $ref, mixed $documento): string
    {
        if ($ref['tipo'] === 'SU') {
            return 'SU';
        }

        if ($ref['tipo'] === 'OC'
            && $documento instanceof \App\Models\Compras\Ordencompra
            && (bool) ($documento->es_suscripcion ?? false)
        ) {
            return 'SU';
        }

        return $ref['tipo'];
    }

    /**
     * @return array{label: string, estado: string, fecha_limite: ?string, dias_para_vencer: ?int}
     */
    private function calcularSlaArbol(
        Arbolaprobacion_Movimiento $mov,
        ?Carbon $fechaEnvio,
        int $diasPendiente,
        string $urgencia
    ): array {
        $arbol = \App\Models\Configuracion\Arbolaprobacion::query()->find((int) $mov->arbolaprobacion_id);
        $diasLimite = max(1, (int) ($arbol->diasinrespuesta ?? 5));
        if ($arbol && strtoupper((string) ($arbol->recordatorio ?? 'N')) === 'S') {
            $diasLimite = max(1, (int) ($arbol->diasinrespuesta ?? 1));
        }

        if (! $fechaEnvio) {
            return [
                'label' => 'Sin fecha de envío',
                'estado' => 'normal',
                'fecha_limite' => null,
                'dias_para_vencer' => null,
            ];
        }

        $limite = $fechaEnvio->copy()->startOfDay()->addDays($diasLimite);
        $hoy = Carbon::now()->startOfDay();
        $diasParaVencer = (int) $hoy->diffInDays($limite, false);

        if ($diasParaVencer < 0) {
            $atraso = abs($diasParaVencer);

            return [
                'label' => $atraso === 1 ? 'Vencido hace 1 día' : 'Vencido hace '.$atraso.' días',
                'estado' => 'vencido',
                'fecha_limite' => $limite->format('Y-m-d'),
                'dias_para_vencer' => $diasParaVencer,
            ];
        }
        if ($diasParaVencer === 0) {
            return [
                'label' => 'Vence hoy',
                'estado' => 'urgente',
                'fecha_limite' => $limite->format('Y-m-d'),
                'dias_para_vencer' => 0,
            ];
        }
        if ($diasParaVencer <= 2 || $urgencia === 'atencion') {
            return [
                'label' => 'Vence en '.$diasParaVencer.' día'.($diasParaVencer === 1 ? '' : 's'),
                'estado' => 'atencion',
                'fecha_limite' => $limite->format('Y-m-d'),
                'dias_para_vencer' => $diasParaVencer,
            ];
        }

        return [
            'label' => 'Vence el '.$limite->format('d/m/Y'),
            'estado' => 'ok',
            'fecha_limite' => $limite->format('Y-m-d'),
            'dias_para_vencer' => $diasParaVencer,
        ];
    }

    /**
     * @param  array{tipo: string, comprobante_id: int, ruta_visualizar: string, ruta_editar: ?string}  $ref
     */
    private function urlVerDocumento(Arbolaprobacion_Movimiento $mov, array $ref): ?string
    {
        $hash = trim((string) ($mov->hashvisualizar ?? ''));
        if ($hash === '' || $ref['ruta_visualizar'] === '') {
            return $ref['ruta_editar'] && Route::has($ref['ruta_editar'])
                ? route($ref['ruta_editar'], $ref['comprobante_id'])
                : null;
        }

        $named = match ($ref['tipo']) {
            'RE' => 'visualizar_requisicion',
            'OC' => 'visualizar_ordencompra',
            'SU' => 'ver_suscripcion',
            'SP' => 'visualizar_solicitudpago',
            'RS' => 'visualizar_requisicion_sala',
            default => null,
        };

        if ($named && Route::has($named)) {
            if ($ref['tipo'] === 'SU') {
                return route($named, ['id' => $ref['comprobante_id']]);
            }

            return route($named, ['id' => $ref['comprobante_id'], 'hash' => $hash]);
        }

        $ip = rtrim((string) config('arbolaprobacion.ip_link'), '/');

        return ArbolAprobacionEnlaceSupport::enlaceVisualizar(
            $ip,
            $ref['ruta_visualizar'],
            $ref['comprobante_id'],
            $hash
        );
    }

    private function esAvisoPagoSp(int $solicitudpagoId, int $nivel): bool
    {
        $sp = Solicitudpago::query()->find($solicitudpagoId);
        if (! $sp) {
            return false;
        }

        return app(\App\Services\Solicitudpago\SolicitudpagoArbolIntegracionService::class)
            ->esNivelAvisoPago($sp, $nivel);
    }

    /**
     * @return array<int, string> usuario_origen_id => nombre
     */
    private function mapaReemplazosActivos(int $usuarioDestinoId): array
    {
        if (! Schema::hasTable('arbol_reemplazo_firmante_log')) {
            return [];
        }

        $q = DB::table('arbol_reemplazo_firmante_log')
            ->where('usuario_destino_id', $usuarioDestinoId);
        if (Schema::hasColumn('arbol_reemplazo_firmante_log', 'operacion')) {
            $q->where('operacion', 'reemplazo');
        }
        if (Schema::hasColumn('arbol_reemplazo_firmante_log', 'restaurado_at')) {
            $q->whereNull('restaurado_at');
        }

        $origenIds = $q->pluck('usuario_origen_id')
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values()
            ->all();

        if ($origenIds === []) {
            return [];
        }

        $nombres = DB::table('usuario')
            ->whereIn('id', $origenIds)
            ->pluck('nombre', 'id');

        $out = [];
        foreach ($origenIds as $id) {
            $out[$id] = (string) ($nombres[$id] ?? ('Usuario #'.$id));
        }

        return $out;
    }

    /**
     * @param  array{tipo: string, comprobante_id: int}  $ref
     */
    private function assertReferenciaValida(array $ref): void
    {
        if ($ref['tipo'] === '' || $ref['comprobante_id'] <= 0) {
            throw new RuntimeException('Movimiento sin comprobante asociado.');
        }
    }
}
