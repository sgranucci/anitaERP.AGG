<?php

namespace App\Services\Ventas\Gastronomia;

use App\Models\Ventas\ConfiguracionPuntoventaGastronomia;
use App\Models\Ventas\CuentaGastronomia;
use App\Models\Ventas\CuentaGastronomiaLinea;
use App\Models\Stock\Articulo_Movimiento;
use App\Models\Stock\Formula_Articulo;
use App\Models\Stock\Formula_Articulo_Hijo;
use App\Models\Ventas\Tipotransaccion;
use App\Models\Ventas\Venta;
use App\Services\Stock\Articulo_MovimientoService;
use App\Support\Stock\FormulaArticuloFactorCosto;
use App\Support\Stock\FormulaArticuloGastronomia;
use App\Support\Ventas\GastronomiaDepositoConfigSupport;
use App\Support\Ventas\GastronomiaMovimientoStockSupport;
use App\Support\Ventas\TipotransaccionOperacionStockSupport;
use App\Support\Ventas\GastronomiaVentaDetalleSupport;
use App\Support\Ventas\GastronomiaFormulaOpcionalSeleccion;
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
        $tipo = Tipotransaccion::query()->find($tipotransaccionId);
        if ($tipo === null || ! TipotransaccionOperacionStockSupport::afectaStock($tipo->operacionstock)) {
            return;
        }

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
                $tipo,
                $venta,
                $linea,
                $ventaEmisionId,
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
                    $opcMap[(string) $k] = $v;
                }
            }

            $aggregados = [];
            $this->expandFormula($formulaId, (float) $linea->cantidad, $opcMap, $aggregados, 0);

            foreach ($aggregados as $ingArticuloId => $cantidad) {
                if ($cantidad <= 0) {
                    continue;
                }

                $this->persistirMovimientoStock($tipo, GastronomiaMovimientoStockSupport::normalizarPayloadMovimiento([
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
                ]));
            }
        }
    }

    /**
     * Insumos ERP tras factura del proceso cierre Waitry (sin CuentaGastronomia, como POS).
     */
    public function registrarMovimientosIngredientesDesdeVentaEmitida(
        Venta $venta,
        ConfiguracionPuntoventaGastronomia $cfg,
        int $tipotransaccionId,
        string $conceptoTipoNombre,
        string $fechaFactura,
        int $monedaId,
        ?string $fechaJornada = null,
    ): void {
        $tipo = Tipotransaccion::query()->find($tipotransaccionId);
        if ($tipo === null || ! TipotransaccionOperacionStockSupport::afectaStock($tipo->operacionstock)) {
            return;
        }

        $fechaJornada = $fechaJornada ?? $fechaFactura;
        $venta->loadMissing(['venta_emisiones.articulos']);

        $depositoVentaId = GastronomiaDepositoConfigSupport::depositoVentaId($cfg);
        $depositoInsumosId = GastronomiaDepositoConfigSupport::depositoInsumosId($cfg);

        foreach ($venta->venta_emisiones as $emision) {
            $articulo = $emision->articulos;
            if (! $articulo) {
                continue;
            }

            $ventaEmisionId = (int) $emision->id;
            $descuento = (float) ($emision->descuento ?? 0);
            $precioNet = (float) $emision->precio * (1 - $descuento / 100.);

            $this->persistirMovimientoStock($tipo, GastronomiaMovimientoStockSupport::normalizarPayloadMovimiento([
                'fecha' => $fechaFactura,
                'fechajornada' => $fechaJornada,
                'tipotransaccion_id' => $tipotransaccionId,
                'venta_id' => $venta->id,
                'venta_emision_id' => $ventaEmisionId,
                'articulo_id' => (int) $emision->articulo_id,
                'concepto' => $conceptoTipoNombre,
                'cantidad' => (float) $emision->cantidad,
                'precio' => (string) $precioNet,
                'costo' => 0,
                'moneda_id' => $monedaId,
                'incluyeimpuesto' => 1,
                'deposito_id' => $depositoVentaId,
            ]));

            if (! $articulo->formula) {
                continue;
            }

            $aggregados = [];
            $this->expandFormula((int) $articulo->formula, (float) $emision->cantidad, [], $aggregados, 0);

            foreach ($aggregados as $ingArticuloId => $cantidad) {
                if ($cantidad <= 0) {
                    continue;
                }

                $this->persistirMovimientoStock($tipo, GastronomiaMovimientoStockSupport::normalizarPayloadMovimiento([
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
                ]));
            }
        }
    }

    private function registrarMovimientoItemFacturado(
        Tipotransaccion $tipo,
        Venta $venta,
        CuentaGastronomiaLinea $linea,
        int $ventaEmisionId,
        string $conceptoTipoNombre,
        string $fechaFactura,
        int $monedaId,
        int $depositoId,
        string $fechaJornada,
    ): void {
        $pct = (float) $linea->descuento_linea_pct;
        $precioNet = (float) $linea->precio_unitario * (1 - $pct / 100);

        $this->persistirMovimientoStock($tipo, GastronomiaMovimientoStockSupport::normalizarPayloadMovimiento([
            'fecha' => $fechaFactura,
            'fechajornada' => $fechaJornada,
            'tipotransaccion_id' => $tipo->id,
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
        ]));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function persistirMovimientoStock(Tipotransaccion $tipo, array $data): void
    {
        $dataFirmado = TipotransaccionOperacionStockSupport::firmarPayloadDesdeTipotransaccion($data, $tipo);
        if ($dataFirmado === null) {
            return;
        }

        $this->articuloMovimientoService->guardaArticuloMovimiento('create', $dataFirmado, []);
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
                $decoded = GastronomiaFormulaOpcionalSeleccion::decodificar($chosen);
                if ($decoded === null) {
                    continue;
                }

                /** @var Formula_Articulo_Hijo|null $match */
                $match = $grupo->first(
                    fn (Formula_Articulo_Hijo $h) => GastronomiaFormulaOpcionalSeleccion::coincideConHijo($h, $decoded)
                );
                if (! $match) {
                    continue;
                }

                $this->procesarHijo($match, $multiplier, $aggregados, $depth);
            }
        }
    }

    /**
     * Insumos de fórmula para una cantidad vendida (sin movimiento de stock).
     *
     * @param  array<string, int|null>  $opcionalesPorOrden
     * @return array<int, float> articulo_id => cantidad
     */
    public function insumosAgregadosPorArticulo(int $articuloId, float $cantidadVendida, array $opcionalesPorOrden = []): array
    {
        $articulo = \App\Models\Stock\Articulo::query()->find($articuloId);
        if ($articulo === null || ! $articulo->formula || $cantidadVendida <= 0.) {
            return [];
        }

        $aggregados = [];
        $this->expandFormula((int) $articulo->formula, $cantidadVendida, $opcionalesPorOrden, $aggregados, 0);

        return $aggregados;
    }

    /**
     * @param  array<int, float>  $aggregados
     */
    /**
     * Revierte en articulo_movimiento las salidas de la factura origen según operacionstock del tipo NC.
     *
     * @throws \Throwable
     */
    public function revertirMovimientosStockDesdeFactura(
        Venta $ventaNc,
        Venta $ventaOrigen,
        ConfiguracionPuntoventaGastronomia $cfg,
        int $tipotransaccionNcId,
        string $conceptoTipoNombre,
        string $fechaComprobante,
        int $monedaId,
        ?string $fechaJornada = null,
    ): void {
        $tipoNc = Tipotransaccion::query()->find($tipotransaccionNcId);
        if ($tipoNc === null || ! TipotransaccionOperacionStockSupport::afectaStock($tipoNc->operacionstock)) {
            return;
        }

        $fechaJornada = $fechaJornada ?? $fechaComprobante;
        $ventaNc->loadMissing(['venta_emisiones']);
        $ventaOrigen->loadMissing(['venta_emisiones']);

        $emisionNcPorItem = $ventaNc->venta_emisiones->keyBy('numeroitem');
        $emisionOrigenPorId = $ventaOrigen->venta_emisiones->keyBy('id');

        $movimientosOrigen = Articulo_Movimiento::query()
            ->where('venta_id', $ventaOrigen->id)
            ->orderBy('id')
            ->get();

        foreach ($movimientosOrigen as $movOrigen) {
            $ventaEmisionOrigenId = (int) ($movOrigen->venta_emision_id ?? 0);
            $numeroItem = 0;
            if ($ventaEmisionOrigenId > 0 && isset($emisionOrigenPorId[$ventaEmisionOrigenId])) {
                $numeroItem = (int) $emisionOrigenPorId[$ventaEmisionOrigenId]->numeroitem;
            }

            $ventaEmisionNc = $numeroItem > 0 ? ($emisionNcPorItem[$numeroItem] ?? null) : null;
            $ventaEmisionNcId = $ventaEmisionNc?->id;

            $depositoId = (int) ($movOrigen->deposito_id ?? 0);
            if ($depositoId <= 0) {
                $depositoId = str_contains((string) $movOrigen->concepto, GastronomiaVentaDetalleSupport::SUFIJO_CONCEPTO_INSUMO)
                    ? GastronomiaDepositoConfigSupport::depositoInsumosId($cfg)
                    : GastronomiaDepositoConfigSupport::depositoVentaId($cfg);
            }

            $this->persistirMovimientoStock($tipoNc, GastronomiaMovimientoStockSupport::normalizarPayloadMovimiento([
                'fecha' => $fechaComprobante,
                'fechajornada' => $fechaJornada,
                'tipotransaccion_id' => $tipotransaccionNcId,
                'venta_id' => $ventaNc->id,
                'venta_emision_id' => $ventaEmisionNcId,
                'articulo_id' => (int) $movOrigen->articulo_id,
                'concepto' => $conceptoTipoNombre.(
                    str_contains((string) $movOrigen->concepto, GastronomiaVentaDetalleSupport::SUFIJO_CONCEPTO_INSUMO)
                        ? GastronomiaVentaDetalleSupport::SUFIJO_CONCEPTO_INSUMO
                        : ''
                ),
                'cantidad' => abs((float) $movOrigen->cantidad),
                'precio' => (string) $movOrigen->precio,
                'costo' => (float) ($movOrigen->costo ?? 0),
                'moneda_id' => $monedaId,
                'incluyeimpuesto' => $movOrigen->incluyeimpuesto ?? 1,
                'deposito_id' => $depositoId,
                'combinacion_id' => $movOrigen->combinacion_id,
                'loteimportacion_id' => $movOrigen->loteimportacion_id,
            ]));
        }
    }

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
