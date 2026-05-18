<?php

namespace App\Services\Ventas\Gastronomia;

use App\Models\Ventas\ConfiguracionPuntoventaGastronomia;
use App\Models\Ventas\CuentaGastronomia;
use App\Models\Ventas\CuentaGastronomiaLinea;
use App\Models\Stock\Formula_Articulo;
use App\Models\Stock\Formula_Articulo_Hijo;
use App\Models\Ventas\Venta;
use App\Services\Stock\Articulo_MovimientoService;
use App\Support\Stock\FormulaArticuloFactorCosto;
use App\Support\Stock\FormulaArticuloGastronomia;
use App\Support\Ventas\GastronomiaDepositoConfigSupport;
use App\Support\Ventas\GastronomiaMovimientoStockSupport;
use App\Support\Ventas\GastronomiaVentaDetalleSupport;
use App\Support\Ventas\GastronomiaVentaEmisionMapSupport;

/**
 * Movimientos de stock al emitir gastronomía: salida del ítem facturado e insumos por línea,
 * vinculados a venta_emision_id para consulta por artículo padre.
 */
final class GastronomiaFormulaConsumoService
{
    public function __construct(
        private readonly Articulo_MovimientoService $articuloMovimientoService,
    ) {
    }

    /**
     * Registra salida del artículo vendido y, si tiene fórmula, salida de cada insumo por línea de cuenta.
     *
     * @throws \Throwable
     */
    public function registrarMovimientosIngredientes(
        Venta $venta,
        CuentaGastronomia $cuenta,
        ConfiguracionPuntoventaGastronomia $cfg,
        int $tipotransaccionId,
        string $conceptoTipoNombre,
        string $fechaFactura,
        int $monedaId,
        ?string $fechaJornada = null,
    ): void {
        $fechaJornada = $fechaJornada ?? $fechaFactura;
        $venta->loadMissing(['venta_emisiones']);
        $cuenta->loadMissing(['lineas.articulo']);

        $mapEmision = GastronomiaVentaEmisionMapSupport::mapLineasCuentaAVentaEmision(
            $venta,
            $cuenta->lineas,
        );

        $depositoVentaId = GastronomiaDepositoConfigSupport::depositoVentaId($cfg);
        $depositoInsumosId = GastronomiaDepositoConfigSupport::depositoInsumosId($cfg);

        /** @var CuentaGastronomiaLinea $linea */
        foreach ($cuenta->lineas as $linea) {
            $articulo = $linea->articulo;
            if (! $articulo) {
                continue;
            }

            $ventaEmisionId = $mapEmision[(int) $linea->id] ?? null;
            if ($ventaEmisionId === null) {
                continue;
            }

            $this->registrarMovimientoItemFacturado(
                $venta,
                $linea,
                $ventaEmisionId,
                $tipotransaccionId,
                $conceptoTipoNombre,
                $fechaFactura,
                $monedaId,
                $depositoVentaId,
                $fechaJornada,
            );

            if (! $articulo->formula) {
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

            foreach ($aggregados as $ingArticuloId => $cantidad) {
                if ($cantidad <= 0) {
                    continue;
                }

                $dataMovimiento = GastronomiaMovimientoStockSupport::normalizarPayloadMovimiento([
                    'fecha' => $fechaFactura,
                    'fechajornada' => $fechaJornada,
                    'tipotransaccion_id' => $tipotransaccionId,
                    'venta_id' => $venta->id,
                    'venta_emision_id' => $ventaEmisionId,
                    'articulo_id' => (int) $ingArticuloId,
                    'concepto' => $conceptoTipoNombre.GastronomiaVentaDetalleSupport::SUFIJO_CONCEPTO_INSUMO,
                    'cantidad' => $cantidad,
                    'precio' => '0',
                    'costo' => 0,
                    'moneda_id' => $monedaId,
                    'incluyeimpuesto' => 1,
                    'deposito_id' => $depositoInsumosId,
                ]);

                $this->articuloMovimientoService->guardaArticuloMovimiento('create', $dataMovimiento, []);
            }
        }
    }

    private function registrarMovimientoItemFacturado(
        Venta $venta,
        CuentaGastronomiaLinea $linea,
        int $ventaEmisionId,
        int $tipotransaccionId,
        string $conceptoTipoNombre,
        string $fechaFactura,
        int $monedaId,
        int $depositoId,
        string $fechaJornada,
    ): void {
        $pct = (float) $linea->descuento_linea_pct;
        $precioNet = (float) $linea->precio_unitario * (1 - $pct / 100);

        $dataMovimiento = GastronomiaMovimientoStockSupport::normalizarPayloadMovimiento([
            'fecha' => $fechaFactura,
            'fechajornada' => $fechaJornada,
            'tipotransaccion_id' => $tipotransaccionId,
            'venta_id' => $venta->id,
            'venta_emision_id' => $ventaEmisionId,
            'articulo_id' => (int) $linea->articulo_id,
            'concepto' => $conceptoTipoNombre,
            'cantidad' => (float) $linea->cantidad,
            'precio' => (string) $precioNet,
            'costo' => 0,
            'moneda_id' => $monedaId,
            'incluyeimpuesto' => 1,
            'deposito_id' => $depositoId,
        ]);

        $this->articuloMovimientoService->guardaArticuloMovimiento('create', $dataMovimiento, []);
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
        $factorLinea = (float) $hijo->cantidad * FormulaArticuloFactorCosto::efectivo($hijo->factorcosto);
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
