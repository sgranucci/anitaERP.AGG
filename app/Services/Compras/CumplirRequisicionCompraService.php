<?php

namespace App\Services\Compras;

use App\Models\Compras\Requisicion;
use App\Models\Compras\Requisicion_Articulo;
use App\Models\Compras\Requisicion_Estado;
use App\Models\Stock\Articulo;
use App\Repositories\Compras\Requisicion_EstadoRepositoryInterface;
use App\Repositories\Stock\Tipotransaccion_StockRepository;
use App\Models\Stock\Depmae;
use App\Models\Stock\Transferencia_Mercaderia;
use App\Services\Configuracion\ModuloAvisoService;
use App\Services\Stock\TransferenciaMercaderiaService;
use Auth;
use Carbon\Carbon;
use DB;
use Illuminate\Support\Collection;

class CumplirRequisicionCompraService
{
    public const ESTADO_PARCIAL = 'PARCIAL';

    public function __construct(
        private TransferenciaMercaderiaService $transferenciaService,
        private Requisicion_EstadoRepositoryInterface $requisicionEstadoRepository,
        private CumplimientoRequisicionCompraPersistenciaService $persistenciaService,
        private Tipotransaccion_StockRepository $tipotransaccionRepository,
        private ModuloAvisoService $moduloAvisoService,
        private RequisicionArticuloCambioService $articuloCambioService,
    ) {
    }

    /**
     * Solo se cumplen requisiciones "internas" que se abastecen del stock existente.
     * Las que tienen orden de compra (GENERO ORDEN COMPRA) no se cumplen por este circuito.
     *
     * @return list<string>
     */
    public static function estadosPermitidosParaCumplir(): array
    {
        return [
            self::nombreEstado('A'),   // APROBADA
        ];
    }

    private static function nombreEstado(string $valor): string
    {
        foreach (Requisicion_Estado::$enumEstado as $row) {
            if ($row['valor'] === $valor) {
                return $row['nombre'];
            }
        }

        return $valor;
    }

    public function puedeCumplir(Requisicion $req): bool
    {
        return in_array((string) $req->estado, self::estadosPermitidosParaCumplir(), true);
    }

    public function resolverTipoTransaccionId(): int
    {
        $configId = config('compras.requisicion_cumplimiento_tipotransaccion_stock_id');
        if (! empty($configId)) {
            return (int) $configId;
        }

        $abrev = (string) config('compras.requisicion_cumplimiento_tipotransaccion_abreviatura', 'TRA');
        try {
            return $this->tipotransaccionRepository->findIdPorAbreviatura($abrev);
        } catch (\Throwable $e) {
            throw new \RuntimeException('No se pudo resolver el tipo de transacción de stock ("'.$abrev.'"). Configure COMPRAS_REQUISICION_CUMPLIMIENTO_TIPOTRANSACCION_ID.');
        }
    }

    /**
     * @return array{ok: bool, mensaje?: string, requisicion?: Requisicion, lineas?: Collection<int, Requisicion_Articulo>}
     */
    public function cargarRequisicion(int $id): array
    {
        $req = Requisicion::query()
            ->with([
                'empresas',
                'centrocostos',
                'usuarios',
                'requisicion_articulos.articulos',
                'requisicion_articulos.monedas',
                'requisicion_articulos.centrocostos_destino',
            ])
            ->find($id);

        if (! $req) {
            return ['ok' => false, 'mensaje' => 'Requisici&oacute;n de compra no encontrada.'];
        }
        if (! $this->puedeCumplir($req)) {
            return ['ok' => false, 'mensaje' => 'Solo se pueden cumplir requisiciones internas en estado APROBADA (se abastecen del stock existente).'];
        }

        $lineas = $this->filtrarLineasPendientes($req->requisicion_articulos);
        if ($lineas->isEmpty()) {
            return ['ok' => false, 'mensaje' => 'No hay &iacute;tems pendientes de cumplir en esta requisici&oacute;n.'];
        }

        return ['ok' => true, 'requisicion' => $req, 'lineas' => $lineas];
    }

    /**
     * @param  iterable<int, Requisicion_Articulo>  $articulos
     * @return Collection<int, Requisicion_Articulo>
     */
    private function filtrarLineasPendientes(iterable $articulos): Collection
    {
        return collect($articulos)->filter(function (Requisicion_Articulo $linea) {
            $pendiente = (float) $linea->cantidad - (float) ($linea->cantidadentregada ?? 0);

            return $pendiente > 0;
        })->values();
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{mensaje: string, errores?: string, cumplimiento_id?: int, cumplimiento_numero?: int, transferencias?: list<int>, transferencias_detalle?: list<array<string,mixed>>, impresion?: array<string, mixed>}
     */
    public function grabar(array $payload): array
    {
        $requisicionId = (int) ($payload['requisicion_id'] ?? 0);
        if ($requisicionId <= 0) {
            return ['mensaje' => 'error', 'errores' => 'Debe seleccionar una requisici&oacute;n de compra.'];
        }

        $lineasPayload = $payload['lineas'] ?? [];
        if (! is_array($lineasPayload) || $lineasPayload === []) {
            return ['mensaje' => 'error', 'errores' => 'Debe indicar al menos una l&iacute;nea con cantidad a cumplir.'];
        }

        $depositoOrigenDefault = (int) ($payload['deposito_origen_id'] ?? 0);
        $depositoDestinoDefault = (int) ($payload['deposito_destino_id'] ?? 0);
        if ($depositoOrigenDefault <= 0 || $depositoDestinoDefault <= 0) {
            return ['mensaje' => 'error', 'errores' => 'Debe indicar dep&oacute;sito de origen y de destino.'];
        }
        if ($depositoOrigenDefault === $depositoDestinoDefault) {
            return ['mensaje' => 'error', 'errores' => 'El dep&oacute;sito de origen y destino no pueden ser el mismo.'];
        }

        $usuarioId = (int) Auth::id();
        $filasImpresion = [];
        $snapshotsPersistencia = [];
        $transferenciasPorGrupo = [];
        $cambiosArticuloPendientes = [];
        $leyenda = trim((string) ($payload['leyenda'] ?? ''));

        DB::beginTransaction();
        try {
            $req = Requisicion::query()
                ->with(['empresas', 'centrocostos', 'usuarios'])
                ->find($requisicionId);
            if (! $req || ! $this->puedeCumplir($req)) {
                throw new \RuntimeException('La requisici&oacute;n no est&aacute; en un estado v&aacute;lido para cumplir.');
            }

            $estadoRequisicionAntes = (string) $req->estado;
            $depositoOrigenImpresion = Depmae::query()->find($depositoOrigenDefault);
            $depositoDestinoImpresion = Depmae::query()->find($depositoDestinoDefault);
            $cabeceraReq = CumplirRequisicionCompraPdfService::armarCabeceraDesdeRequisicion(
                $req,
                $depositoOrigenImpresion,
                $depositoDestinoImpresion
            );

            foreach ($lineasPayload as $fila) {
                if (! is_array($fila)) {
                    continue;
                }
                $this->procesarLinea(
                    $req,
                    $fila,
                    $depositoOrigenDefault,
                    $depositoDestinoDefault,
                    $estadoRequisicionAntes,
                    $cabeceraReq,
                    $transferenciasPorGrupo,
                    $filasImpresion,
                    $snapshotsPersistencia,
                    $cambiosArticuloPendientes
                );
            }

            if ($snapshotsPersistencia === []) {
                throw new \RuntimeException('Debe indicar cantidad a cumplir mayor a cero en al menos una l&iacute;nea.');
            }

            $transferenciaIds = $this->generarTransferencias($req, $transferenciasPorGrupo);

            $nuevoEstado = $this->resolverEstadoCabecera($requisicionId);
            if ($nuevoEstado !== '' && $nuevoEstado !== $estadoRequisicionAntes) {
                $this->requisicionEstadoRepository->creaEstado(
                    $requisicionId,
                    Carbon::now()->toDateTimeString(),
                    $nuevoEstado,
                    $usuarioId,
                    'Cumplimiento de requisici&oacute;n de compra'
                );
                $req->update(['estado' => $nuevoEstado]);
            }

            $cumplimiento = $this->persistenciaService->persistir(
                $usuarioId,
                $leyenda !== '' ? $leyenda : null,
                $snapshotsPersistencia,
                $transferenciaIds,
                (int) $req->empresa_id ?: null
            );

            foreach ($cambiosArticuloPendientes as $cambio) {
                $this->articuloCambioService->registrar(
                    (int) $cambio['requisicion_id'],
                    (int) $cambio['requisicion_articulo_id'],
                    (int) $cambio['articulo_id_anterior'],
                    (int) $cambio['articulo_id_nuevo'],
                    $usuarioId,
                    (int) $cumplimiento->id,
                    $cambio['motivo'] ?? null
                );
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();

            return ['mensaje' => 'error', 'errores' => strip_tags($e->getMessage())];
        }

        // Aviso configurable al generador de la requisición (no bloquea el flujo).
        $this->moduloAvisoService->enviar('compras', 'requisicion_compra_cumplida', $requisicionId, [
            'cumplimiento_id' => (int) $cumplimiento->id,
            'cumplimiento_numero' => (int) $cumplimiento->numero,
        ]);

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
                'referencia' => CumplirRequisicionCompraPdfService::armarReferenciaImpresion([$cabeceraReq]),
                'cabeceras' => [$cabeceraReq],
                'filas' => $filasImpresion,
                'transferencias' => $transferenciasImpresion,
                'leyenda' => $leyenda !== '' ? $leyenda : null,
                'usuario' => Auth::user()?->nombre ?? Auth::user()?->email ?? '',
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $fila
     * @param  array<string, mixed>  $cabeceraReq
     * @param  array<string, array{empresa_id:int, deposito_origen_id:int, deposito_destino_id:int, lineas: list<array{articulo_id:int, cantidad:float}>}>  $transferenciasPorGrupo
     * @param  list<array<string, mixed>>  $filasImpresion
     * @param  list<array<string, mixed>>  $snapshotsPersistencia
     * @param  list<array<string, mixed>>  $cambiosArticuloPendientes
     */
    private function procesarLinea(
        Requisicion $req,
        array $fila,
        int $depositoOrigenDefault,
        int $depositoDestinoDefault,
        string $estadoRequisicionAntes,
        array $cabeceraReq,
        array &$transferenciasPorGrupo,
        array &$filasImpresion,
        array &$snapshotsPersistencia,
        array &$cambiosArticuloPendientes
    ): void {
        $entrega = (float) ($fila['cantidad_entrega'] ?? 0);
        if ($entrega <= 0) {
            return;
        }

        $lineaId = (int) ($fila['requisicion_articulo_id'] ?? 0);
        if ($lineaId <= 0) {
            throw new \RuntimeException('L&iacute;nea de art&iacute;culo inv&aacute;lida.');
        }

        /** @var Requisicion_Articulo|null $linea */
        $linea = Requisicion_Articulo::query()->with(['articulos'])->find($lineaId);
        if (! $linea || (int) $linea->requisicion_id !== (int) $req->id) {
            throw new \RuntimeException('L&iacute;nea de art&iacute;culo no pertenece a la requisici&oacute;n.');
        }
        if ((int) $linea->articulo_id <= 0) {
            throw new \RuntimeException('La l&iacute;nea no tiene art&iacute;culo asociado y no puede transferirse.');
        }

        $articuloOriginalId = (int) $linea->articulo_id;
        $articuloEfectivoId = (int) ($fila['articulo_id'] ?? 0) ?: $articuloOriginalId;
        $huboCambioArticulo = $articuloEfectivoId !== $articuloOriginalId;

        if ($huboCambioArticulo) {
            if (! can('cambiar-articulo-cumplir-requisicion-compra', false)) {
                throw new \RuntimeException('No tiene permiso para cambiar el art&iacute;culo en el cumplimiento.');
            }
            if ($articuloEfectivoId <= 0 || ! Articulo::query()->whereKey($articuloEfectivoId)->exists()) {
                throw new \RuntimeException('El art&iacute;culo sustituto indicado no es v&aacute;lido.');
            }

            $linea->update(['articulo_id' => $articuloEfectivoId]);
            $linea->load('articulos');

            $cambiosArticuloPendientes[] = [
                'requisicion_id' => (int) $linea->requisicion_id,
                'requisicion_articulo_id' => (int) $linea->id,
                'articulo_id_anterior' => $articuloOriginalId,
                'articulo_id_nuevo' => $articuloEfectivoId,
                'motivo' => 'Cambio de art&iacute;culo al cumplir requisici&oacute;n #'.($req->numerorequisicion ?? $req->id),
            ];
        }

        $entregadaAntes = (float) ($linea->cantidadentregada ?? 0);
        $pendiente = (float) $linea->cantidad - $entregadaAntes;
        if ($pendiente <= 0) {
            throw new \RuntimeException('El art&iacute;culo '.($linea->articulos?->sku ?? $linea->articulo_id).' no tiene pendiente para cumplir.');
        }
        if ($entrega > $pendiente + 0.0001) {
            throw new \RuntimeException('La cantidad a cumplir supera el pendiente del art&iacute;culo '.($linea->articulos?->sku ?? $linea->articulo_id).'.');
        }

        $origenId = (int) ($fila['deposito_origen_id'] ?? 0) ?: $depositoOrigenDefault;
        $destinoId = (int) ($fila['deposito_destino_id'] ?? 0) ?: $depositoDestinoDefault;
        if ($origenId <= 0 || $destinoId <= 0) {
            throw new \RuntimeException('Dep&oacute;sito de origen o destino inv&aacute;lido.');
        }
        if ($origenId === $destinoId) {
            throw new \RuntimeException('El dep&oacute;sito de origen y destino no pueden ser el mismo.');
        }

        $snapshotsPersistencia[] = [
            'requisicion_id' => (int) $linea->requisicion_id,
            'requisicion_articulo_id' => (int) $linea->id,
            'articulo_id' => $articuloEfectivoId,
            'articulo_id_original' => $huboCambioArticulo ? $articuloOriginalId : null,
            'cantidad_entrega' => $entrega,
            'cantidad_pendiente_antes' => $pendiente,
            'cantidadentregada_antes' => $entregadaAntes,
            'deposito_origen_id' => $origenId,
            'deposito_destino_id' => $destinoId,
            'precio' => (float) ($linea->precio ?? 0),
            'moneda_id' => $linea->moneda_id ? (int) $linea->moneda_id : null,
            'centrocostodestino_id' => $linea->centrocostodestino_id ? (int) $linea->centrocostodestino_id : null,
            'detalle' => $linea->detalle,
            'estado_requisicion_antes' => $estadoRequisicionAntes,
        ];

        $linea->update([
            'cantidadentregada' => $entregadaAntes + $entrega,
        ]);

        $filasImpresion[] = [
            'requisicion_nro' => $cabeceraReq['numerorequisicion'] ?? '',
            'sku' => $linea->articulos?->sku,
            'descripcion' => $linea->articulos?->descripcion,
            'sku_original' => $huboCambioArticulo
                ? optional(Articulo::query()->find($articuloOriginalId))->sku
                : null,
            'entrega' => $entrega,
            'pendiente_restante' => max(0, $pendiente - $entrega),
            'precio' => (float) ($linea->precio ?? 0),
            'deposito_origen_codigo' => optional(Depmae::query()->find($origenId))->codigo,
            'deposito_destino_codigo' => optional(Depmae::query()->find($destinoId))->codigo,
        ];

        $clave = $origenId.'-'.$destinoId;
        if (! isset($transferenciasPorGrupo[$clave])) {
            $transferenciasPorGrupo[$clave] = [
                'empresa_id' => (int) $req->empresa_id,
                'deposito_origen_id' => $origenId,
                'deposito_destino_id' => $destinoId,
                'lineas' => [],
            ];
        }
        $transferenciasPorGrupo[$clave]['lineas'][] = [
            'articulo_id' => $articuloEfectivoId,
            'cantidad' => $entrega,
        ];
    }

    /**
     * @param  array<string, array{empresa_id:int, deposito_origen_id:int, deposito_destino_id:int, lineas: list<array{articulo_id:int, cantidad:float}>}>  $porGrupo
     * @return list<int>
     */
    private function generarTransferencias(Requisicion $req, array $porGrupo): array
    {
        if ($porGrupo === []) {
            return [];
        }

        $tipoTransaccionId = $this->resolverTipoTransaccionId();
        $obsBase = 'Cumple requisici&oacute;n compra #'.($req->numerorequisicion ?? $req->id);
        $ids = [];

        foreach ($porGrupo as $grupo) {
            $lineasAgrupadas = $this->agruparLineasTransferencia($grupo['lineas']);
            if ($lineasAgrupadas === []) {
                continue;
            }

            $result = $this->transferenciaService->grabarTransferencia([
                'deposito_salida_id' => (int) $grupo['deposito_origen_id'],
                'deposito_entrada_id' => (int) $grupo['deposito_destino_id'],
                'tipotransaccion_stock_id' => $tipoTransaccionId,
                'empresa_id' => (int) $grupo['empresa_id'],
                'observacion' => $obsBase,
            ], $lineasAgrupadas);

            if (! ($result['ok'] ?? false)) {
                throw new \RuntimeException((string) ($result['mensaje'] ?? 'Error al generar la transferencia de stock.'));
            }
            if (! empty($result['transferencia_id'])) {
                $ids[] = (int) $result['transferencia_id'];
            }
        }

        return $ids;
    }

    /**
     * @param  list<array{articulo_id:int, cantidad:float}>  $lineas
     * @return list<array{articulo_id:int, cantidad:float}>
     */
    private function agruparLineasTransferencia(array $lineas): array
    {
        $map = [];
        foreach ($lineas as $linea) {
            $articuloId = (int) ($linea['articulo_id'] ?? 0);
            $cantidad = (float) ($linea['cantidad'] ?? 0);
            if ($articuloId <= 0 || $cantidad <= 0) {
                continue;
            }
            $map[$articuloId] = ($map[$articuloId] ?? 0.0) + $cantidad;
        }

        $salida = [];
        foreach ($map as $articuloId => $cantidad) {
            $salida[] = ['articulo_id' => (int) $articuloId, 'cantidad' => $cantidad];
        }

        return $salida;
    }

    /**
     * @param  list<int>  $ids
     * @return list<array<string, mixed>>
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

    public function resolverEstadoCabecera(int $requisicionId): string
    {
        $cumplida = self::nombreEstado('C');

        $lineas = Requisicion_Articulo::query()
            ->where('requisicion_id', $requisicionId)
            ->get();

        if ($lineas->isEmpty()) {
            return '';
        }

        foreach ($lineas as $linea) {
            if ((float) ($linea->cantidadentregada ?? 0) < (float) $linea->cantidad) {
                // Aún quedan ítems pendientes: la requisición permanece APROBADA
                // para poder continuar cumpliéndola desde el stock interno.
                return '';
            }
        }

        return $cumplida;
    }
}
