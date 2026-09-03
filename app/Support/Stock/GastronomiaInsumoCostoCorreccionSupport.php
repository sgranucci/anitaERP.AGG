<?php

namespace App\Support\Stock;

use App\Models\Stock\Articulo;
use App\Models\Stock\Articulo_Movimiento;
use App\Models\Stock\Transferencia_Mercaderia;
use App\Models\Stock\Transferencia_Mercaderia_Articulo;
use Illuminate\Support\Facades\DB;

/**
 * Corrige costo unitario de insumos gastronomía: coef. de compra, TRA destino y recuentos.
 */
final class GastronomiaInsumoCostoCorreccionSupport
{
    /** @var array<string, float> SKU compra → coeficiente caja/UM insumo */
    private const COEF_COMPRA = [
        '203131' => 800.0,
        '203759' => 198.0,
        '203760' => 198.0,
        '203761' => 3.0,
        '203762' => 3.0,
        'I0133' => 3.0,
    ];

    /** @var list<string> */
    private const INSUMO_SKUS = ['I0404', 'I0133', 'I0044', 'I0042', 'I0221', 'I0149', 'I0053', 'I0038'];

    /**
     * TRA puntuales: origen inflado. [sku_compra, fecha, origen_actual_aprox, origen_correcto, coef]
     *
     * @var list<array{sku: string, fecha: string, origen_desde: float, origen: float, coef: float}>
     */
    private const ORIGEN_TRA = [
        ['sku' => '201127', 'fecha' => '2026-08-09', 'origen_desde' => 1_000_000.0, 'origen' => 28015.74, 'coef' => 25.0],
        ['sku' => '203763', 'fecha' => '2026-08-10', 'origen_desde' => 20000.0, 'origen' => 11728.90, 'coef' => 3.0],
    ];

    /**
     * @return array{
     *     coeficientes: int,
     *     lineas_tra: int,
     *     movimientos_tra: int,
     *     recuentos: int,
     *     stkmae: int,
     *     costos: array<string, float|null>
     * }
     */
    public static function corregir(bool $aplicar, bool $anita): array
    {
        $ret = [
            'coeficientes' => 0,
            'lineas_tra' => 0,
            'movimientos_tra' => 0,
            'recuentos' => 0,
            'stkmae' => 0,
            'costos' => [],
        ];

        $compraIds = Articulo::query()
            ->whereIn('sku', array_keys(self::COEF_COMPRA))
            ->get(['id', 'sku', 'coeficienteconversion']);

        $tmIdsAnita = [];

        DB::beginTransaction();
        try {
            foreach ($compraIds as $art) {
                $coef = self::COEF_COMPRA[(string) $art->sku];
                if (abs((float) $art->coeficienteconversion - $coef) <= 0.000001) {
                    continue;
                }
                $ret['coeficientes']++;
                if ($aplicar) {
                    $art->coeficienteconversion = $coef;
                    $art->save();
                }
            }

            foreach (self::ORIGEN_TRA as $regla) {
                $compra = Articulo::query()->where('sku', $regla['sku'])->first(['id']);
                if ($compra === null) {
                    continue;
                }
                $lineas = Transferencia_Mercaderia_Articulo::query()
                    ->with('transferencias')
                    ->where('articulo_origen_id', $compra->id)
                    ->whereHas('transferencias', function ($q) use ($regla) {
                        $q->where('estado', TransferenciaMercaderiaEstados::CONFIRMADA)
                            ->whereDate('fecha', $regla['fecha']);
                    })
                    ->where('precio_costo_origen', '>=', $regla['origen_desde'])
                    ->get();

                foreach ($lineas as $linea) {
                    $ret['lineas_tra']++;
                    if (! $aplicar) {
                        continue;
                    }
                    $r = TransferenciaMercaderiaRepararCostosSupport::aplicarPrecioDestinoConservandoCantidad(
                        $linea,
                        (float) $regla['coef'],
                        (float) $regla['origen']
                    );
                    $ret['movimientos_tra'] += (int) $r['movimientos_actualizados'];
                    $tmIdsAnita[(int) $linea->transferencia_mercaderia_id] = true;
                }
            }

            foreach (self::COEF_COMPRA as $sku => $coef) {
                if (str_starts_with($sku, 'I')) {
                    continue;
                }
                $compra = Articulo::query()->where('sku', $sku)->first(['id']);
                if ($compra === null) {
                    continue;
                }
                $lineas = Transferencia_Mercaderia_Articulo::query()
                    ->with('transferencias')
                    ->where('articulo_origen_id', $compra->id)
                    ->whereHas('transferencias', function ($q) {
                        $q->where('estado', TransferenciaMercaderiaEstados::CONFIRMADA);
                    })
                    ->where('fl_conversion_formula', true)
                    ->get();

                foreach ($lineas as $linea) {
                    $origen = (float) $linea->precio_costo_origen;
                    $destEsperado = $coef > 0 ? round($origen / $coef, 6) : $origen;
                    $yaOk = abs((float) $linea->precio_costo_destino - $destEsperado) <= 0.000001
                        && abs((float) $linea->coeficienteconversion - $coef) <= 0.000001;
                    if ($yaOk) {
                        continue;
                    }
                    $ret['lineas_tra']++;
                    if (! $aplicar) {
                        continue;
                    }
                    $r = TransferenciaMercaderiaRepararCostosSupport::aplicarPrecioDestinoConservandoCantidad($linea, $coef);
                    $ret['movimientos_tra'] += (int) $r['movimientos_actualizados'];
                    $tmIdsAnita[(int) $linea->transferencia_mercaderia_id] = true;
                }
            }

            $insumos = Articulo::query()->whereIn('sku', self::INSUMO_SKUS)->get();
            $precios = ArticuloPrecioUltimaCompraSupport::resolverPorArticulos($insumos);

            foreach ($insumos as $insumo) {
                $precio = (float) ($precios[(int) $insumo->id]['precio'] ?? 0);
                if ($precio <= 0) {
                    continue;
                }
                $q = Articulo_Movimiento::query()
                    ->where('articulo_id', $insumo->id)
                    ->where('concepto', 'like', 'Recuento%')
                    ->where(function ($w) use ($precio) {
                        $w->whereRaw('ABS(precio - ?) > 0.0001', [$precio])
                            ->orWhereRaw('ABS(costo - ?) > 0.0001', [$precio]);
                    });
                $n = (clone $q)->count();
                $ret['recuentos'] += $n;
                if ($aplicar && $n > 0) {
                    $q->update(['precio' => $precio, 'costo' => $precio]);
                }
            }

            if ($aplicar && $anita) {
                foreach (array_keys($tmIdsAnita) as $tmId) {
                    $tm = Transferencia_Mercaderia::query()
                        ->with(['articulos.articuloDestino'])
                        ->find($tmId);
                    if ($tm === null) {
                        continue;
                    }
                    $ret['stkmae'] += StkmaePrecioCompraAnitaBridgeSupport::actualizarDesdeTransferencia($tm);
                }
            }

            if ($aplicar) {
                DB::commit();
            } else {
                DB::rollBack();
            }
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        $insumos = Articulo::query()->whereIn('sku', self::INSUMO_SKUS)->get();
        $precios = ArticuloPrecioUltimaCompraSupport::resolverPorArticulos($insumos);
        foreach ($insumos as $insumo) {
            $ret['costos'][(string) $insumo->sku] = $precios[(int) $insumo->id]['precio'] ?? null;
        }

        return $ret;
    }
}
