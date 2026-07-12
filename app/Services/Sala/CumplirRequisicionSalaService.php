<?php

namespace App\Services\Sala;

use App\Models\Sala\RequisicionSala;
use App\Models\Sala\RequisicionSalaArticulo;
use App\Models\Sala\RequisicionSalaEstado;
use App\Models\Sala\TecnicoLaboratorio;
use App\Models\Stock\Articulo_ParteUnica;
use App\Models\Stock\Depmae;
use App\Models\Stock\Transferencia_Mercaderia;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Repositories\Sala\RequisicionSalaEstadoRepositoryInterface;
use App\Repositories\Sala\RequisicionSalaRepositoryInterface;
use App\Services\Stock\TransferenciaMercaderiaService;
use App\Traits\Sala\RequisicionSalaArticuloEstadoParcialTrait;
use App\Traits\Sala\RequisicionSalaArticuloEstadoTrait;
use Auth;
use Carbon\Carbon;
use DB;
use Illuminate\Support\Collection;

class CumplirRequisicionSalaService
{
    use RequisicionSalaArticuloEstadoParcialTrait;

    public function __construct(
        private RequisicionSalaRepositoryInterface $requisicionSalaRepository,
        private RequisicionSalaEstadoRepositoryInterface $requisicionSalaEstadoRepository,
        private TransferenciaMercaderiaService $transferenciaService,
        private EmpresaRepositoryInterface $empresaRepository,
        private CumplimientoRequisicionSalaPersistenciaService $persistenciaService,
    ) {
    }

    /** @return list<string> */
    public static function estadosPermitidosParaCumplir(): array
    {
        $trait = RequisicionSalaEstado::class;
        $aprobada = $trait::$enumEstado[array_search('A', array_column($trait::$enumEstado, 'valor'))]['nombre'];
        $parcial = $trait::$enumEstado[array_search('2', array_column($trait::$enumEstado, 'valor'))]['nombre'];

        return [$aprobada, $parcial];
    }

    public function resolverDepositoLaboratorioId(): int
    {
        $codigo = trim((string) config('sala.requisicion_deposito_laboratorio_codigo', '406'));
        if ($codigo === '') {
            return 0;
        }
        $id = Depmae::query()->where('codigo', $codigo)->value('id');

        return $id ? (int) $id : 0;
    }

    public function puedeCumplir(RequisicionSala $req): bool
    {
        return in_array((string) $req->estado, self::estadosPermitidosParaCumplir(), true);
    }

    /**
     * @return array{ok: bool, mensaje?: string, requisicion?: RequisicionSala, lineas?: Collection<int, RequisicionSalaArticulo>}
     */
    public function cargarRequisicion(int $id): array
    {
        $req = RequisicionSala::query()
            ->with([
                'depositos',
                'centrocostos',
                'empresas',
                'solicitante',
                'requisicion_sala_articulos.articulos',
            ])
            ->find($id);

        if (! $req) {
            return ['ok' => false, 'mensaje' => 'Requisici&oacute;n de sala no encontrada.'];
        }
        if (! $this->puedeCumplir($req)) {
            return ['ok' => false, 'mensaje' => 'La requisici&oacute;n debe estar en estado APROBADA o PARCIAL para cumplir.'];
        }

        $lineas = $this->filtrarLineasPendientes($req->requisicion_sala_articulos);
        if ($lineas->isEmpty()) {
            return ['ok' => false, 'mensaje' => 'No hay &iacute;tems pendientes de cumplir en esta requisici&oacute;n.'];
        }

        return ['ok' => true, 'requisicion' => $req, 'lineas' => $lineas];
    }

    /**
     * Busca un &iacute;tem pendiente por NPU (modo carga por c&oacute;digo de parte &uacute;nica).
     *
     * @return array{ok: bool, mensaje?: string, requisicion?: RequisicionSala, linea?: RequisicionSalaArticulo}
     */
    public function buscarLineaPorNpu(string $npu): array
    {
        $npu = trim($npu);
        if ($npu === '') {
            return ['ok' => false, 'mensaje' => 'Indique un NPU.'];
        }

        $estados = self::estadosPermitidosParaCumplir();
        $empresas = $this->empresaRepository->traeEmpresasAsignadas();

        $candidatos = RequisicionSalaArticulo::query()
            ->with(['articulos', 'requisicion_salas.depositos', 'requisicion_salas.centrocostos', 'requisicion_salas.empresas'])
            ->where(function ($q) use ($npu) {
                $q->where('numeroparte', $npu);
                if (ctype_digit($npu)) {
                    $q->orWhere('numeroparte', (string) ((int) $npu));
                }
            })
            ->whereHas('requisicion_salas', function ($q) use ($estados, $empresas) {
                $q->whereIn('estado', $estados)->whereIn('empresa_id', $empresas);
            })
            ->orderByDesc('id')
            ->get();

        foreach ($candidatos as $linea) {
            $pendiente = (float) $linea->cantidad - (float) ($linea->cantidadentregada ?? 0);
            $estadoLinea = (string) ($linea->estado ?? ' ');
            if ($pendiente <= 0 || $estadoLinea === 'C') {
                continue;
            }

            /** @var RequisicionSala|null $req */
            $req = $linea->requisicion_salas;
            if (! $req || ! $this->puedeCumplir($req)) {
                continue;
            }

            return ['ok' => true, 'requisicion' => $req, 'linea' => $linea];
        }

        return ['ok' => false, 'mensaje' => 'No hay requisici&oacute;n pendiente de cumplir para el NPU indicado.'];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{mensaje: string, errores?: string, transferencias?: list<int>, impresion?: array<string, mixed>}
     */
    public function grabar(array $payload): array
    {
        $lineasPayload = $payload['lineas'] ?? [];
        if (! is_array($lineasPayload) || $lineasPayload === []) {
            return ['mensaje' => 'error', 'errores' => 'Debe indicar al menos una l&iacute;nea con cantidad a cumplir o cierre de &iacute;tem.'];
        }

        $lineasPayload = array_values(array_filter($lineasPayload, static function ($fila): bool {
            if (! is_array($fila)) {
                return false;
            }
            $entrega = (float) ($fila['cantidad_entrega'] ?? 0);
            $estadoParcial = trim((string) ($fila['estadoparcial'] ?? ''));

            return $entrega > 0 || self::esCierraItem($estadoParcial);
        }));
        if ($lineasPayload === []) {
            return ['mensaje' => 'error', 'errores' => 'Debe indicar al menos una l&iacute;nea con cantidad a cumplir o cierre de &iacute;tem.'];
        }

        $depositoLabDefault = $this->resolverDepositoLaboratorioId();
        if ($depositoLabDefault <= 0) {
            return ['mensaje' => 'error', 'errores' => 'Dep&oacute;sito de laboratorio (406) no configurado en depmae.'];
        }

        $agrupado = [];
        foreach ($lineasPayload as $fila) {
            $lineaId = (int) ($fila['requisicion_sala_articulo_id'] ?? 0);
            if ($lineaId <= 0) {
                return ['mensaje' => 'error', 'errores' => 'L&iacute;nea de art&iacute;culo inv&aacute;lida.'];
            }
            $linea = RequisicionSalaArticulo::query()->with(['articulos', 'requisicion_salas'])->find($lineaId);
            if (! $linea) {
                return ['mensaje' => 'error', 'errores' => 'L&iacute;nea de art&iacute;culo no encontrada.'];
            }
            $reqId = (int) $linea->requisicion_sala_id;
            $agrupado[$reqId][] = ['fila' => $fila, 'linea' => $linea];
        }

        $usuarioId = (int) Auth::id();
        $transferenciaIds = [];
        $filasImpresion = [];
        $cabecerasImpresion = [];
        $snapshotsPersistencia = [];
        $empresasGrabar = [];
        $depositoOrigenImpresion = Depmae::query()->find($depositoLabDefault);

        DB::beginTransaction();
        try {
            foreach ($agrupado as $reqId => $items) {
                $req = RequisicionSala::query()
                    ->with(['depositos', 'centrocostos', 'empresas'])
                    ->find($reqId);
                if (! $req || ! $this->puedeCumplir($req)) {
                    throw new \RuntimeException('Requisici&oacute;n #'.$reqId.' no est&aacute; en estado v&aacute;lido para cumplir.');
                }

                $cabecerasImpresion[$reqId] = CumplirRequisicionSalaPdfService::armarCabeceraDesdeRequisicion(
                    $req,
                    $depositoOrigenImpresion
                );
                $empresasGrabar[(int) $req->empresa_id] = true;

                $transferenciasPorOrigen = [];
                $hayMovimiento = false;

                foreach ($items as $item) {
                    $resultado = $this->procesarLineaCumple(
                        $item['linea'],
                        $item['fila'],
                        $depositoLabDefault,
                        $transferenciasPorOrigen,
                        $filasImpresion,
                        $cabecerasImpresion[$reqId],
                        $snapshotsPersistencia
                    );
                    if ($resultado) {
                        $hayMovimiento = true;
                    }
                }

                if (! $hayMovimiento) {
                    continue;
                }

                $transferenciaIds = array_merge(
                    $transferenciaIds,
                    $this->generarTransferencias($req, $transferenciasPorOrigen)
                );

                $nuevoEstadoCab = $this->resolverEstadoCabecera((int) $reqId);
                if ($nuevoEstadoCab !== $req->estado) {
                    $this->requisicionSalaEstadoRepository->creaEstado(
                        (int) $reqId,
                        Carbon::now()->toDateTimeString(),
                        $nuevoEstadoCab,
                        $usuarioId,
                        'Cumplimiento de requisici&oacute;n de sala'
                    );
                    $this->requisicionSalaRepository->update(['estado' => $nuevoEstadoCab], (int) $reqId);
                }
            }

            if ($filasImpresion === []) {
                throw new \RuntimeException('No hay cantidades ni cierres de &iacute;tem para grabar.');
            }

            $empresaIds = array_keys($empresasGrabar);
            $empresaPersistir = count($empresaIds) === 1 ? (int) $empresaIds[0] : null;
            $leyendaTx = trim((string) ($payload['leyenda'] ?? ''));
            if (is_array($payload['leyenda'] ?? null)) {
                $leyendaTx = trim(implode("\n", array_filter(array_map('trim', $payload['leyenda']), static fn ($l) => $l !== '')));
            }

            $cumplimiento = $this->persistenciaService->persistir(
                $usuarioId,
                $leyendaTx !== '' ? $leyendaTx : null,
                $snapshotsPersistencia,
                $transferenciaIds,
                $empresaPersistir
            );

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();

            return ['mensaje' => 'error', 'errores' => strip_tags($e->getMessage())];
        }

        $leyendaRaw = $payload['leyenda'] ?? '';
        if (is_array($leyendaRaw)) {
            $leyendaRaw = implode("\n", array_filter(array_map('trim', $leyendaRaw), static fn ($l) => $l !== ''));
        }
        $leyenda = trim((string) $leyendaRaw);
        $cabeceras = array_values($cabecerasImpresion);
        $transferenciasImpresion = $this->armarTransferenciasImpresion($transferenciaIds);

        return [
            'mensaje' => 'ok',
            'cumplimiento_id' => (int) $cumplimiento->id,
            'cumplimiento_numero' => (int) $cumplimiento->numero,
            'transferencias' => $transferenciaIds,
            'transferencias_detalle' => $transferenciasImpresion,
            'impresion' => [
                'cumplimiento_id' => (int) $cumplimiento->id,
                'cumplimiento_numero' => (int) $cumplimiento->numero,
                'referencia' => CumplirRequisicionSalaPdfService::armarReferenciaImpresion($cabeceras),
                'cabeceras' => $cabeceras,
                'filas' => $filasImpresion,
                'transferencias' => $transferenciasImpresion,
                'leyenda' => $leyenda !== '' ? $leyenda : null,
                'usuario' => Auth::user()?->nombre ?? Auth::user()?->email ?? '',
            ],
        ];
    }

    /**
     * @param  list<int>  $ids
     * @return list<array{id: int, codigo: string, origen_codigo: ?string, origen: ?string, destino_codigo: ?string, destino: ?string}>
     */
    private function armarTransferenciasImpresion(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        return Transferencia_Mercaderia::query()
            ->with(['depositoOrigen', 'depositoDestino'])
            ->whereIn('id', $ids)
            ->orderBy('id')
            ->get()
            ->map(static function (Transferencia_Mercaderia $t): array {
                return [
                    'id' => (int) $t->id,
                    'codigo' => (string) ($t->codigo ?? ''),
                    'origen_codigo' => $t->depositoOrigen?->codigo,
                    'origen' => $t->depositoOrigen?->nombre,
                    'destino_codigo' => $t->depositoDestino?->codigo,
                    'destino' => $t->depositoDestino?->nombre,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @param  array<int, list<array{articulo_id: int, cantidad: float}>>  $transferenciasPorOrigen
     * @param  list<array<string, mixed>>  $filasImpresion
     * @param  array<string, mixed>  $cabeceraReq
     */
    private function procesarLineaCumple(
        RequisicionSalaArticulo $linea,
        array $fila,
        int $depositoLabDefault,
        array &$transferenciasPorOrigen,
        array &$filasImpresion,
        array $cabeceraReq,
        array &$snapshotsPersistencia
    ): bool {
        $pendiente = (float) $linea->cantidad - (float) ($linea->cantidadentregada ?? 0);
        if ($pendiente <= 0) {
            return false;
        }

        $estadoParcial = trim((string) ($fila['estadoparcial'] ?? ''));
        $entrega = (float) ($fila['cantidad_entrega'] ?? 0);
        $cierraItem = self::esCierraItem($estadoParcial);

        if ($entrega <= 0 && ! $cierraItem) {
            return false;
        }

        if ($cierraItem) {
            $entrega = $pendiente;
        } elseif ($entrega > $pendiente) {
            throw new \RuntimeException('La cantidad entregada supera el pendiente del art&iacute;culo '.$linea->articulos?->sku);
        }

        if ($entrega < $pendiente && $estadoParcial === '') {
            throw new \RuntimeException('Debe indicar motivo de entrega parcial para el art&iacute;culo '.$linea->articulos?->sku);
        }

        $tecnicoId = (int) ($fila['tecnico_laboratorio_id'] ?? 0);

        $depositoOrigenId = (int) ($fila['deposito_origen_id'] ?? $depositoLabDefault);
        if ($depositoOrigenId <= 0) {
            throw new \RuntimeException('Dep&oacute;sito de origen inv&aacute;lido.');
        }

        $estadoLinea = trim((string) ($fila['estado_linea'] ?? ''));
        if ($estadoLinea === '' && $entrega > 0) {
            $estadoLinea = $entrega >= $pendiente ? 'E' : 'A';
        }
        if ($cierraItem) {
            $estadoLinea = 'C';
        }

        $fechaEntrega = trim((string) ($fila['fecha_entrega'] ?? ''));
        if ($fechaEntrega === '' && ($entrega > 0 || $cierraItem)) {
            $fechaEntrega = Carbon::now()->format('Y-m-d');
        }

        $nuevaEntregada = (float) ($linea->cantidadentregada ?? 0) + $entrega;
        if ($cierraItem) {
            $nuevaEntregada = (float) $linea->cantidad;
        }

        $numeroparteGrabar = $this->resolverNumeroparteCumple($linea, $fila, $entrega, $cierraItem);
        $tecnicoIdResuelto = $this->resolverTecnicoLaboratorioId($linea, $tecnicoId);

        $snapshotsPersistencia[] = [
            'requisicion_sala_id' => (int) $linea->requisicion_sala_id,
            'requisicion_sala_articulo_id' => (int) $linea->id,
            'articulo_id' => (int) $linea->articulo_id,
            'cantidad_entrega' => $entrega,
            'cantidad_pendiente_antes' => $pendiente,
            'cantidadentregada_antes' => (float) ($linea->cantidadentregada ?? 0),
            'deposito_origen_id' => $depositoOrigenId,
            'tecnico_laboratorio_id' => $tecnicoIdResuelto,
            'numeroparte' => $numeroparteGrabar ?? $linea->numeroparte,
            'uid' => $linea->uid,
            'destino' => (string) ($linea->destino ?? ''),
            'estado_linea' => $estadoLinea,
            'estadoparcial' => $entrega < $pendiente || $cierraItem ? $estadoParcial : null,
            'fecha_entrega' => $fechaEntrega !== '' ? $fechaEntrega : null,
            'numeroremito' => $fila['numeroremito'] ?? $linea->numeroremito,
            'nombreresponsable' => $fila['nombreresponsable'] ?? $linea->nombreresponsable,
            'estado_linea_antes' => (string) ($linea->estado ?? ' '),
            'estadoparcial_antes' => $linea->estadoparcial,
            'fecha_entrega_antes' => $linea->fecha_entrega,
            'numeroremito_antes' => $linea->numeroremito,
            'nombreresponsable_antes' => $linea->nombreresponsable,
            'tecnico_laboratorio_id_antes' => $linea->tecnico_laboratorio_id,
            'deposito_origen_id_antes' => $linea->deposito_origen_id,
            'numeroparte_antes' => $linea->numeroparte,
        ];

        $linea->update([
            'cantidadentregada' => $nuevaEntregada,
            'estado' => $estadoLinea !== '' ? $estadoLinea : $linea->estado,
            'estadoparcial' => $entrega < $pendiente || $cierraItem ? $estadoParcial : null,
            'fecha_entrega' => $fechaEntrega !== '' ? $fechaEntrega : $linea->fecha_entrega,
            'numeroremito' => $fila['numeroremito'] ?? $linea->numeroremito,
            'nombreresponsable' => $fila['nombreresponsable'] ?? $linea->nombreresponsable,
            'tecnico_laboratorio_id' => $tecnicoIdResuelto,
            'deposito_origen_id' => $depositoOrigenId,
            'numeroparte' => $numeroparteGrabar ?? $linea->numeroparte,
        ]);

        $depOrigen = Depmae::query()->find($depositoOrigenId);
        $tecnico = $tecnicoIdResuelto ? TecnicoLaboratorio::query()->find($tecnicoIdResuelto) : null;
        $pendienteRestante = max(0, $pendiente - $entrega);
        if ($cierraItem) {
            $pendienteRestante = 0;
        }

        $motivoNombre = self::estadoParcialNombrePorValor($estadoParcial);
        if ($motivoNombre !== '' || $entrega > 0) {
            $filasImpresion[] = [
                'requisicion_nro' => $cabeceraReq['numerorequisicion'] ?? '',
                'sku' => $linea->articulos?->sku,
                'descripcion' => $linea->descripcionArticulo(),
                'entrega' => $entrega,
                'pendiente_restante' => $pendienteRestante,
                'precio' => (float) ($linea->precio ?? 0),
                'deposito_origen_codigo' => $depOrigen?->codigo,
                'deposito_origen' => $depOrigen?->nombre,
                'uid' => $linea->uid,
                'npu' => $numeroparteGrabar ?? $linea->numeroparte,
                'tecnico' => $tecnico?->nombre,
                'motivo_parcial' => $motivoNombre,
            ];
        }

        if ($entrega > 0 && ! $cierraItem) {
            $transferenciasPorOrigen[$depositoOrigenId][] = [
                'articulo_id' => (int) $linea->articulo_id,
                'cantidad' => $entrega,
                'numeroparte' => $numeroparteGrabar,
            ];
        }

        return true;
    }

    private function lineaRequiereTecnico(RequisicionSalaArticulo $linea): bool
    {
        return (string) ($linea->destino ?? '') === 'R';
    }

    private function resolverTecnicoLaboratorioId(RequisicionSalaArticulo $linea, int $tecnicoId): ?int
    {
        if (! $this->lineaRequiereTecnico($linea)) {
            return null;
        }

        return $tecnicoId > 0 ? $tecnicoId : ($linea->tecnico_laboratorio_id ? (int) $linea->tecnico_laboratorio_id : null);
    }

    /**
     * @param  iterable<int, RequisicionSalaArticulo>  $articulos
     * @return Collection<int, RequisicionSalaArticulo>
     */
    private function filtrarLineasPendientes(iterable $articulos): Collection
    {
        return collect($articulos)->filter(function (RequisicionSalaArticulo $linea) {
            $pendiente = (float) $linea->cantidad - (float) ($linea->cantidadentregada ?? 0);
            $estadoLinea = (string) ($linea->estado ?? ' ');

            return $pendiente > 0 && $estadoLinea !== 'C';
        })->values();
    }

    /**
     * @param  array<int, list<array{articulo_id: int, cantidad: float}>>  $porOrigen
     * @return list<int>
     */
    private function generarTransferencias(RequisicionSala $req, array $porOrigen): array
    {
        if ($porOrigen === []) {
            return [];
        }

        $depositoDestinoId = (int) $req->deposito_id;
        if ($depositoDestinoId <= 0) {
            throw new \RuntimeException('La requisici&oacute;n no tiene dep&oacute;sito destino.');
        }

        $tipoTransaccionId = (int) config('sala.requisicion_transferencia_tipotransaccion_id', 1);
        $obsBase = 'Cumple requisici&oacute;n sala #'.($req->numerorequisicion ?? $req->id);
        $ids = [];

        foreach ($porOrigen as $depositoOrigenId => $lineas) {
            if ((int) $depositoOrigenId === $depositoDestinoId) {
                continue;
            }
            $lineasAgrupadas = $this->agruparLineasTransferencia($lineas);
            if ($lineasAgrupadas === []) {
                continue;
            }

            $result = $this->transferenciaService->grabarTransferencia([
                'deposito_salida_id' => (int) $depositoOrigenId,
                'deposito_entrada_id' => $depositoDestinoId,
                'tipotransaccion_stock_id' => $tipoTransaccionId,
                'empresa_id' => (int) $req->empresa_id,
                'observacion' => $obsBase.' — origen dep&oacute;sito '.$depositoOrigenId,
            ], $lineasAgrupadas);

            if (! ($result['ok'] ?? false)) {
                throw new \RuntimeException((string) ($result['mensaje'] ?? 'Error al generar transferencia de stock.'));
            }
            if (! empty($result['transferencia_id'])) {
                $ids[] = (int) $result['transferencia_id'];
            }
        }

        return $ids;
    }

    /**
     * @param  list<array{articulo_id: int, cantidad: float}>  $lineas
     * @return list<array{articulo_id: int, cantidad: float}>
     */
    private function agruparLineasTransferencia(array $lineas): array
    {
        $map = [];
        $salida = [];
        foreach ($lineas as $linea) {
            $articuloId = (int) ($linea['articulo_id'] ?? 0);
            $cantidad = (float) ($linea['cantidad'] ?? 0);
            $numeroparte = trim((string) ($linea['numeroparte'] ?? ''));
            if ($articuloId <= 0 || $cantidad <= 0) {
                continue;
            }
            if ($numeroparte !== '') {
                $salida[] = [
                    'articulo_id' => $articuloId,
                    'cantidad' => $cantidad,
                    'numeroparte' => $numeroparte,
                ];

                continue;
            }
            if (! isset($map[$articuloId])) {
                $map[$articuloId] = 0.0;
            }
            $map[$articuloId] += $cantidad;
        }
        foreach ($map as $articuloId => $cantidad) {
            $salida[] = ['articulo_id' => (int) $articuloId, 'cantidad' => $cantidad];
        }

        return $salida;
    }

    private function resolverNumeroparteCumple(
        RequisicionSalaArticulo $linea,
        array $fila,
        float $entrega,
        bool $cierraItem
    ): ?string {
        if ($entrega <= 0 && ! $cierraItem) {
            $actual = trim((string) ($linea->numeroparte ?? ''));

            return $actual !== '' ? $actual : null;
        }

        $npu = trim((string) ($fila['numeroparte'] ?? $linea->numeroparte ?? ''));
        if ($npu === '') {
            return null;
        }
        if (strlen($npu) > 50) {
            throw new \RuntimeException('El NPU no puede superar 50 caracteres.');
        }

        $articuloId = (int) $linea->articulo_id;
        if ($articuloId > 0) {
            try {
                \App\Support\Stock\ArticuloParteUnicaDisponibilidadSupport::assertActivaParaUso($npu, $articuloId);
            } catch (\RuntimeException $e) {
                throw $e;
            }
        } elseif (\App\Support\Stock\ArticuloParteUnicaDisponibilidadSupport::estaDadaDeBaja($npu)) {
            throw new \RuntimeException('El NPU '.$npu.' fue dado de baja y no puede utilizarse.');
        }

        return $npu;
    }

    private function resolverEstadoCabecera(int $requisicionSalaId): string
    {
        $trait = RequisicionSalaEstado::class;
        $cumplido = $trait::$enumEstado[array_search('3', array_column($trait::$enumEstado, 'valor'))]['nombre'];
        $parcial = $trait::$enumEstado[array_search('2', array_column($trait::$enumEstado, 'valor'))]['nombre'];

        $lineas = RequisicionSalaArticulo::query()
            ->where('requisicion_sala_id', $requisicionSalaId)
            ->get();

        foreach ($lineas as $linea) {
            if ((float) ($linea->cantidadentregada ?? 0) < (float) $linea->cantidad) {
                return $parcial;
            }
        }

        return $cumplido;
    }
}
