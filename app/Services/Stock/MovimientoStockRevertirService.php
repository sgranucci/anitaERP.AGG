<?php

namespace App\Services\Stock;

use App\Models\Stock\MovimientoStock;
use App\Models\Stock\Transferencia_Mercaderia;
use App\Repositories\Stock\Articulo_Saldo_DepositoRepositoryInterface;
use App\Support\Contable\AsientoReversoSupport;
use App\Support\Contable\PeriodoContableCierreSupport;
use App\Support\Stock\AltaNpuMovimientoStockSupport;
use App\Support\Stock\BajaNpuMovimientoStockSupport;
use App\Support\Stock\MovimientoStockSalidaSaldoSupport;
use App\Support\Stock\MovimientoStockVisibilidadSupport;
use App\Support\Stock\TransferenciaMercaderiaEstados;
use Auth;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class MovimientoStockRevertirService
{
    public function __construct(
        private MovimientoStockService $movimientoStockService,
        private MovimientoStockAsientoService $asientoService,
        private AsientoReversoSupport $asientoReversoSupport,
        private Articulo_Saldo_DepositoRepositoryInterface $saldoDepositoRepository,
        private TransferenciaMercaderiaService $transferenciaMercaderiaService,
    ) {}

    /**
     * @return array{id: int, codigo: string, mensaje: string}
     */
    public function revertirMovimiento(int $id, ?string $fecha = null): array
    {
        $movimiento = MovimientoStock::query()
            ->with([
                'tipotransaccion_stock',
                'articulos_movimiento.articulos',
                'asientos',
            ])
            ->findOrFail($id);

        if (! MovimientoStockVisibilidadSupport::movimientoAccesible($movimiento)) {
            throw new \Illuminate\Database\Eloquent\ModelNotFoundException('Movimiento de stock no encontrado');
        }

        $this->assertMovimientoRevertible($movimiento);

        $fechaOperacion = $this->resolverFecha($fecha, $movimiento->fecha);
        $empresaId = $this->resolverEmpresaIdMovimiento($movimiento);

        PeriodoContableCierreSupport::assertOperacionPermitida(
            $empresaId,
            $fechaOperacion,
            PeriodoContableCierreSupport::ALCANCE_STOCK
        );

        $tipo = $movimiento->tipotransaccion_stock;
        if ($tipo === null) {
            throw new \RuntimeException('El movimiento no tiene tipo de transacción de stock.');
        }

        if (($tipo->operacion ?? '') === 'T') {
            throw new \RuntimeException('Para revertir una transferencia use la acción Revertir en el listado de transferencias.');
        }

        $signoReverso = ($tipo->signo ?? 'S') === 'S' ? 'R' : 'S';
        $payload = $this->armarPayloadReversoMovimiento($movimiento, $signoReverso, $fechaOperacion, $empresaId);

        $this->validarSaldoReversoMovimiento($movimiento, $payload, $signoReverso);

        return DB::transaction(function () use ($movimiento, $payload, $fechaOperacion) {
            $resultado = $this->movimientoStockService->guardaMovimientoStock($payload, 'create');
            if (! is_array($resultado) || empty($resultado['id'])) {
                throw new \RuntimeException(is_string($resultado) ? $resultado : 'No se pudo grabar el movimiento de reversión.');
            }

            $revertId = (int) $resultado['id'];
            $revert = MovimientoStock::query()->findOrFail($revertId);
            $revert->movimientostock_origen_id = (int) $movimiento->id;
            $revert->save();

            $asientoReversoId = null;
            if ((int) ($movimiento->asiento_id ?? 0) > 0 && $movimiento->asientos) {
                $asientoReverso = $this->asientoReversoSupport->generarDesdeAsiento(
                    $movimiento->asientos,
                    $fechaOperacion,
                    $revertId,
                    'Revierte movimiento stock '.$movimiento->codigo
                );
                $asientoReversoId = (int) $asientoReverso['asiento_id'];
                $revert->asiento_id = $asientoReversoId;
                $revert->save();

                try {
                    $revert->loadMissing(['asientos', 'tipotransaccion_stock', 'articulos_movimiento.articulos.articulo_cuentacontables']);
                    $this->asientoService->sincronizarCtamovAnitaMovimiento($revert);
                } catch (\Throwable $e) {
                    report($e);
                }
            }

            $movimiento->movimientostock_revertido_por_id = $revertId;
            $movimiento->estado = 'I';
            $movimiento->save();

            return [
                'id' => $revertId,
                'codigo' => (string) ($resultado['codigo'] ?? $revert->codigo),
                'mensaje' => 'Movimiento revertido. Se generó el compensatorio #'.$revertId
                    .($asientoReversoId ? ' con asiento contable #'.$asientoReversoId : '').'.',
            ];
        });
    }

    /**
     * @return array{id: int, codigo: string, mensaje: string}
     */
    public function revertirTransferencia(int $transferenciaId, ?string $fecha = null): array
    {
        $transferencia = $this->transferenciaMercaderiaService->revertirTransferenciaConfirmada($transferenciaId, $fecha);

        return [
            'id' => (int) $transferencia->id,
            'codigo' => (string) $transferencia->codigo,
            'mensaje' => 'Transferencia '.$transferencia->transferencia_origen_id.' revertida. '
                .'Compensatorio: '.$transferencia->codigo.'.',
        ];
    }

    private function assertMovimientoRevertible(MovimientoStock $movimiento): void
    {
        if ((int) ($movimiento->movimientostock_revertido_por_id ?? 0) > 0) {
            throw new \RuntimeException('El movimiento ya fue revertido.');
        }
        if ((int) ($movimiento->movimientostock_origen_id ?? 0) > 0) {
            throw new \RuntimeException('No se puede revertir un movimiento que es compensación de otro.');
        }
        if (($movimiento->estado ?? 'A') !== 'A') {
            throw new \RuntimeException('Solo se pueden revertir movimientos activos.');
        }

        $vinculada = Transferencia_Mercaderia::query()
            ->where(function ($q) use ($movimiento) {
                $q->where('movimientostock_salida_id', (int) $movimiento->id)
                    ->orWhere('movimientostock_entrada_id', (int) $movimiento->id);
            })
            ->whereNull('transferencia_revertido_por_id')
            ->whereNotIn('estado', [
                TransferenciaMercaderiaEstados::RECHAZADA,
                TransferenciaMercaderiaEstados::ANULADA,
                TransferenciaMercaderiaEstados::REVERTIDA,
            ])
            ->exists();

        if ($vinculada) {
            throw new \RuntimeException(
                'Este movimiento pertenece a una transferencia. Revierta la transferencia completa desde el listado.'
            );
        }

        if ($movimiento->articulos_movimiento->isEmpty()) {
            throw new \RuntimeException('El movimiento no tiene ítems para revertir.');
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function armarPayloadReversoMovimiento(
        MovimientoStock $movimiento,
        string $signoReverso,
        string $fecha,
        int $empresaId
    ): array {
        $lineas = $movimiento->articulos_movimiento;
        $n = $lineas->count();

        $depositoId = (int) ($lineas->first()->deposito_id ?? 0) ?: null;
        $bienUsoId = (int) ($lineas->first()->bien_uso_id ?? 0) ?: null;

        $articulosId = [];
        $cantidades = [];
        $precios = [];
        $numeropartes = [];
        $items = [];

        foreach ($lineas as $i => $linea) {
            $articulosId[] = (int) $linea->articulo_id;
            $cantidades[] = abs((float) $linea->cantidad);
            $precios[] = (float) ($linea->precio ?? $linea->costo ?? 0);
            $numeropartes[] = trim((string) ($linea->numeroparte ?? ''));
            $items[] = $i;
        }

        $codigoReverso = trim((string) $movimiento->codigo).'-RV-'.Carbon::now()->format('His');

        $payload = [
            'tipotransaccion_stock_id' => (int) $movimiento->tipotransaccion_stock_id,
            'signo_cantidad' => $signoReverso,
            'fecha' => $fecha,
            'fechajornada' => $fecha,
            'deposito_id' => $depositoId,
            'bien_uso_id' => $bienUsoId,
            'empresa_id' => $empresaId > 0 ? $empresaId : null,
            'centrocosto_destino_id' => $movimiento->centrocosto_destino_id,
            'mventa_id' => $movimiento->mventa_id,
            'lote' => (int) ($lineas->first()->lote ?? $movimiento->id),
            'leyenda' => 'Revierte movimiento #'.$movimiento->id.' '.$movimiento->codigo,
            'loteimportacion_id' => null,
            'codigo' => $codigoReverso,
            'letra' => '',
            'puntoventa' => '',
            'numerocomprobante' => '',
            'codigocliente' => '',
            'codigotransporte' => '',
            'codigovendedor' => '',
            'codigozona' => '',
            'codigoprovincia' => '',
            'pedido' => '',
            'empresa' => config('app.empresa'),
            'omitir_asiento_contable' => (int) ($movimiento->asiento_id ?? 0) > 0,
            'articulos_id' => $articulosId,
            'skus' => array_fill(0, $n, ''),
            'combinaciones_id' => array_fill(0, $n, null),
            'modulos_id' => array_fill(0, $n, null),
            'items' => $items,
            'cantidades' => $cantidades,
            'cajas' => array_fill(0, $n, 0),
            'piezas' => array_fill(0, $n, 0),
            'precios' => $precios,
            'listasprecios_id' => array_fill(0, $n, null),
            'incluyeimpuestos' => array_fill(0, $n, '0'),
            'monedas_id' => array_fill(0, $n, null),
            'descuentos' => array_fill(0, $n, 0),
            'loteids' => array_fill(0, $n, 0),
            'medidas' => [],
            'numeropartes' => $numeropartes,
        ];

        $movimiento->loadMissing('tipotransaccion_stock');
        if (BajaNpuMovimientoStockSupport::esTipoBajaNpu($movimiento->tipotransaccion_stock)) {
            $payload['reactivar_npu_movimiento_origen_id'] = (int) $movimiento->id;
            $payload['omitir_asiento_contable'] = true;
        }
        if (AltaNpuMovimientoStockSupport::esTipoAltaNpu($movimiento->tipotransaccion_stock)) {
            $payload['eliminar_npu_movimiento_origen_id'] = (int) $movimiento->id;
            $payload['omitir_asiento_contable'] = true;
        }

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function validarSaldoReversoMovimiento(MovimientoStock $movimiento, array $payload, string $signoReverso): void
    {
        if ($signoReverso !== 'R') {
            return;
        }

        $depositoId = (int) ($payload['deposito_id'] ?? 0);
        if ($depositoId <= 0) {
            return;
        }

        MovimientoStockSalidaSaldoSupport::validarDesdeLineasFormulario(
            $depositoId,
            $payload['articulos_id'] ?? [],
            $payload['cantidades'] ?? [],
            $this->saldoDepositoRepository,
        );
    }

    private function resolverEmpresaIdMovimiento(MovimientoStock $movimiento): int
    {
        $depositoId = (int) ($movimiento->articulos_movimiento->first()->deposito_id ?? 0);
        if ($depositoId > 0) {
            return (int) (\App\Models\Stock\Depmae::query()->whereKey($depositoId)->value('empresa_id') ?? 0);
        }

        return 0;
    }

    private function resolverFecha(?string $fecha, ?string $fechaOriginal): string
    {
        $fecha = trim((string) ($fecha ?? ''));
        if ($fecha !== '') {
            return Carbon::parse($fecha)->format('Y-m-d');
        }

        if ($fechaOriginal) {
            return Carbon::parse($fechaOriginal)->format('Y-m-d');
        }

        return now()->format('Y-m-d');
    }
}
