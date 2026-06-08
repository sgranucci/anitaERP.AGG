<?php

namespace App\Support\Ventas\Gastronomia;

use App\Models\Stock\MovimientoStock;
use App\Models\Stock\Tipotransaccion_Stock;
use App\Services\Stock\Articulo_MovimientoService;
use App\Support\Ventas\GastronomiaMovimientoStockSupport;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Ajuste de stock por consumo de insumos (comandas no facturadas — parte efectivo).
 */
final class CierreJornadaProcesoInsumoAjusteSupport
{
    /**
     * @param  list<array{articulo_id:int,cantidad:float}>  $lineasInsumos
     * @return array{movimientostock_id:int,codigo:string,cantidad_lineas:int}|null
     */
    public static function registrar(
        array $lineasInsumos,
        int $empresaId,
        int $depositoInsumosId,
        string $fecha,
        string $fechaJornada,
        string $leyenda,
        Articulo_MovimientoService $articuloMovimientoService,
    ): ?array {
        if ($lineasInsumos === []) {
            return null;
        }

        $tipoStockId = (int) config('gastronomia.cierre_jornada_tipotransaccion_stock_ajuste_consumo_id', 7);
        if ($tipoStockId <= 0) {
            throw new InvalidArgumentException(
                'Configure GASTRONOMIA_CIERRE_JORNADA_TIPOTRANSACCION_STOCK_AJUSTE_CONSUMO_ID (ajuste por consumo).',
            );
        }

        $tipo = Tipotransaccion_Stock::query()->find($tipoStockId);
        if ($tipo === null) {
            throw new InvalidArgumentException('Tipo de transacción de stock id '.$tipoStockId.' inexistente.');
        }

        if ($depositoInsumosId <= 0) {
            throw new InvalidArgumentException('Configure el depósito de insumos para el ajuste de stock del proceso.');
        }

        return DB::transaction(function () use (
            $lineasInsumos,
            $tipoStockId,
            $tipo,
            $depositoInsumosId,
            $fecha,
            $fechaJornada,
            $leyenda,
            $articuloMovimientoService,
        ) {
            $ultimo = MovimientoStock::query()->orderByDesc('id')->value('id');
            $codigo = (string) ((int) $ultimo + 1);

            $cabecera = MovimientoStock::query()->create([
                'fecha' => $fecha,
                'fechajornada' => $fechaJornada,
                'tipotransaccion_stock_id' => $tipoStockId,
                'mventa_id' => null,
                'codigo' => $codigo,
                'leyenda' => $leyenda,
                'estado' => 0,
                'usuario_id' => Auth::id() ? (int) Auth::id() : 1,
            ]);

            $concepto = (string) ($tipo->nombre ?? 'Ajuste por consumo');
            $n = 0;

            foreach ($lineasInsumos as $ln) {
                $articuloId = (int) ($ln['articulo_id'] ?? 0);
                $cantidad = (float) ($ln['cantidad'] ?? 0);
                if ($articuloId <= 0 || $cantidad <= 0.) {
                    continue;
                }

                $data = GastronomiaMovimientoStockSupport::normalizarPayloadMovimiento([
                    'fecha' => $fecha,
                    'fechajornada' => $fechaJornada,
                    'tipotransaccion_stock_id' => $tipoStockId,
                    'signo_cantidad' => $tipo->signo ?? 'R',
                    'movimientostock_id' => (int) $cabecera->id,
                    'articulo_id' => $articuloId,
                    'concepto' => $concepto.' — cierre Waitry',
                    'cantidad' => $cantidad,
                    'precio' => '0',
                    'costo' => 0,
                    'moneda_id' => 1,
                    'incluyeimpuesto' => 1,
                    'deposito_id' => $depositoInsumosId,
                ]);

                $articuloMovimientoService->guardaArticuloMovimiento('create', $data, []);
                $n++;
            }

            if ($n === 0) {
                $cabecera->delete();

                return null;
            }

            return [
                'movimientostock_id' => (int) $cabecera->id,
                'codigo' => $codigo,
                'cantidad_lineas' => $n,
            ];
        });
    }
}
