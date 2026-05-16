<?php

namespace App\Services\Stock\Gastronomia;

use App\Models\Stock\CuentaGastronomia;
use App\Models\Stock\CuentaGastronomiaLinea;
use App\Models\Stock\Formula_Articulo;
use App\Models\Stock\Formula_Articulo_Hijo;
use App\Models\Ventas\Venta;
use App\Services\Stock\Articulo_MovimientoService;
use App\Support\Stock\FormulaArticuloGastronomia;

/**
 * Descarga de ingredientes por fórmula / subfórmula al emitir gastronomía (misma venta_id).
 */
final class GastronomiaFormulaConsumoService
{
    public function __construct(
        private readonly Articulo_MovimientoService $articuloMovimientoService,
    ) {
    }

    /**
     * Registra movimientos de stock por ingredientes según líneas de cuenta ya facturadas.
     *
     * @throws \Throwable
     */
    public function registrarMovimientosIngredientes(
        Venta $venta,
        CuentaGastronomia $cuenta,
        int $tipotransaccionId,
        string $conceptoTipoNombre,
        string $fechaFactura,
        int $monedaId
    ): void {
        $cuenta->loadMissing(['lineas.articulo']);

        /** @var CuentaGastronomiaLinea $linea */
        foreach ($cuenta->lineas as $linea) {
            $articulo = $linea->articulo;
            if (! $articulo || ! $articulo->formula) {
                continue;
            }

            $formulaId = (int) $articulo->formula;

            $opcMap = [];
            if (FormulaArticuloGastronomia::opcionalesHabilitados()) {
                foreach (($linea->opcionales_json ?? []) as $k => $v) {
                    $opcMap[(string) $k] = $v !== null ? (int) $v : null;
                }
            }

            $aggregados = [];
            $this->expandFormula($formulaId, (float) $linea->cantidad, $opcMap, $aggregados, 0);

            $depositoId = (int) (config('facturacion.DEPOSITO_VENTA_ID') ?: 1);

            foreach ($aggregados as $ingArticuloId => $cantidad) {
                if ($cantidad <= 0) {
                    continue;
                }

                $dataMovimiento = [
                    'fecha' => $fechaFactura,
                    'fechajornada' => $fechaFactura,
                    'tipotransaccion_id' => $tipotransaccionId,
                    'venta_id' => $venta->id,
                    'pedido_combinacion_id' => null,
                    'ordentrabajo_id' => null,
                    'lote' => 0,
                    'articulo_id' => (int) $ingArticuloId,
                    'combinacion_id' => null,
                    'codigocombinacion' => '',
                    'modulo_id' => null,
                    'concepto' => $conceptoTipoNombre.' — Ing.',
                    'cantidad' => $cantidad,
                    'precio' => '0',
                    'costo' => 0,
                    'despacho' => '',
                    'loteimportacion_id' => null,
                    'descuento' => 0,
                    'descuentointegrado' => '',
                    'moneda_id' => $monedaId,
                    'incluyeimpuesto' => 1,
                    'listaprecio_id' => 1,
                    'deposito_id' => $depositoId,
                ];

                $this->articuloMovimientoService->guardaArticuloMovimiento('create', $dataMovimiento, []);
            }
        }
    }

    /**
     * @param  array<string, int|null>  $opcionalesPorOrden
     * @param  array<int, float>  $aggregados articulo_id => cantidad
     */
    private function expandFormula(int $formulaArticuloId, float $multiplier, array $opcionalesPorOrden, array &$aggregados, int $depth): void
    {
        if ($depth > 25) {
            throw new \RuntimeException('Fórmula demasiado anidada (posible ciclo).');
        }

        /** @var Formula_Articulo|null $formula */
        $formula = Formula_Articulo::query()
            ->with(['formula_articulo_hijos' => fn ($q) => $q->orderBy('ordenopcional')->orderBy('id')])
            ->find($formulaArticuloId);

        if (! $formula) {
            return;
        }

        $hijos = $formula->formula_articulo_hijos;

        foreach ($hijos->where('esopcional', false) as $hijo) {
            $this->procesarHijo($hijo, $multiplier, $aggregados, $depth);
        }

        if (FormulaArticuloGastronomia::opcionalesHabilitados()) {
            $opcionales = $hijos->where('esopcional', true)->groupBy(fn ($h) => (string) ($h->ordenopcional ?? '0'));

            foreach ($opcionales->sortKeys() as $orden => $grupo) {
                $chosen = $opcionalesPorOrden[$orden] ?? null;
                if ($chosen === null || $chosen === 0) {
                    continue;
                }

                /** @var Formula_Articulo_Hijo|null $match */
                $match = $grupo->firstWhere('articulo_id', $chosen);
                if (! $match) {
                    continue;
                }

                $this->procesarHijo($match, $multiplier, $aggregados, $depth);
            }
        }
    }

    /**
     * @param  array<int, float>  $aggregados
     */
    private function procesarHijo(Formula_Articulo_Hijo $hijo, float $multiplier, array &$aggregados, int $depth): void
    {
        $factorLinea = (float) $hijo->cantidad * (float) ($hijo->factorcosto ?: 1);
        $mult = $multiplier * $factorLinea;

        if ($hijo->formula_hija_id) {
            $this->expandFormula((int) $hijo->formula_hija_id, $mult, [], $aggregados, $depth + 1);

            return;
        }

        if ($hijo->articulo_id) {
            $aid = (int) $hijo->articulo_id;
            $aggregados[$aid] = ($aggregados[$aid] ?? 0) + $mult;
        }
    }
}
