<?php

namespace App\Services\Stock\Gastronomia;

use App\Models\Configuracion\Actividad_Arca;
use App\Models\Stock\CuentaGastronomia;
use App\Models\Stock\DescuentoGastronomia;
use App\Models\Stock\VentaGastronomiaEmision;
use App\Models\Ventas\Tipotransaccion;
use App\Models\Ventas\Venta;
use App\Services\Ventas\FacturacionService;
use App\Support\Stock\FormulaArticuloGastronomia;
use App\Support\Stock\GastronomiaIdentificadorPc;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Emisión de factura desde cuenta gastronómica usando {@see FacturacionService}
 * (WS ARCA según PV / modo CAE-CAEA ya configurado en punto de venta).
 */
final class GastronomiaFacturaEmisionService
{
    public function __construct(
        private readonly FacturacionService $facturacionService,
        private readonly GastronomiaFormulaOpcionalesService $opcionalesService,
        private readonly GastronomiaFormulaConsumoService $consumoFormulaService,
        private readonly GastronomiaCuentaService $cuentaService,
    ) {
    }

    /**
     * @return array{venta_id?:int,factura?:string,warn?:string,error?:string}
     */
    public function emitirFacturaDesdeCuenta(
        CuentaGastronomia $cuenta,
        int $monedaId,
        ?int $actividadArcaId = null,
        bool $forzarPvCaea = false
    ): array {
        $tipoFacturaId = config('gastronomia.tipotransaccion_factura_id');
        if (! $tipoFacturaId) {
            return ['error' => 'Configure GASTRONOMIA_TIPO_TRANSACCION_FACTURA_ID en el entorno (.env).'];
        }

        $cuenta->loadMissing([
            'lineas.articulo.formula_articulo.formula_articulo_hijos',
            'cliente',
            'descuentoGastronomia',
            'mesa',
            'configuracionPuntoventa',
        ]);

        if (! $cuenta->cliente_id) {
            return ['error' => 'Asigne un cliente antes de facturar.'];
        }

        if ($cuenta->lineas->isEmpty()) {
            return ['error' => 'La cuenta no tiene consumos cargados.'];
        }

        if ($cuenta->estado !== CuentaGastronomia::ESTADO_ABIERTA) {
            return ['error' => 'La cuenta no está abierta.'];
        }

        foreach ($cuenta->lineas as $linea) {
            $art = $linea->articulo;
            if (! $art) {
                return ['error' => 'Artículo inexistente en línea '.$linea->id.'.'];
            }

            if (FormulaArticuloGastronomia::opcionalesHabilitados()) {
                $grupos = $this->opcionalesService->gruposOpcionalesPorArticulo($art);
                if ($grupos !== []) {
                    $opcMap = [];
                    foreach (($linea->opcionales_json ?? []) as $k => $v) {
                        $opcMap[(string) $k] = $v !== null ? (int) $v : null;
                    }
                    try {
                        $this->opcionalesService->validarSeleccionOpcionales($art, $opcMap);
                    } catch (\InvalidArgumentException $e) {
                        return ['error' => $e->getMessage()];
                    }
                }
            }
        }

        $cfg = $cuenta->configuracionPuntoventa ?? $this->cuentaService->resolverConfiguracionPv((int) $cuenta->empresa_id);
        if (! $cfg) {
            return ['error' => 'No hay configuración de punto de venta gastronomía para este equipo ('.GastronomiaIdentificadorPc::resolver().').'];
        }

        $puntoventaId = $forzarPvCaea ? (int) $cfg->puntoventa_caea_id : (int) $cfg->puntoventa_cae_id;

        [$articuloIds, $cantidades, $precios, $descripciones] = $this->construirArraysFactura($cuenta);

        [$descuentopie, $descuentoimportepie] = $this->resolverDescuentosCabecera($cuenta);

        $leyenda = $cuenta->tipo === CuentaGastronomia::TIPO_MESA && $cuenta->mesa
            ? 'Mesa '.$cuenta->mesa->numeromesa.' '.$cuenta->mesa->nombre
            : 'Cuenta gastronomía #'.$cuenta->id;

        $payload = [
            'tipotransaccion_id' => (int) $tipoFacturaId,
            'puntoventa_id' => $puntoventaId,
            'fechafactura' => now()->format('Y-m-d'),
            'leyendafactura' => $leyenda,
            'actividad_arca_id' => $actividadArcaId ?? (int) (Actividad_Arca::query()->orderBy('id')->value('id') ?? 1),
            'cliente_id' => (int) $cuenta->cliente_id,
            'moneda_id' => $monedaId,
            'descuentopie' => $descuentopie,
            'descuentolinea' => 0.,
            'descuentoimportepie' => $descuentoimportepie,
            'articulo_ids' => $articuloIds,
            'cantidades' => $cantidades,
            'precios' => $precios,
            'descripcionarticulos' => $descripciones,
        ];

        try {
            $resultado = $this->facturacionService->generaComprobanteGeneral($payload);
        } catch (Throwable $e) {
            Log::warning('gastronomia.emitir_factura.excepcion', ['msg' => $e->getMessage()]);

            if ($this->debeReintentarConCaea($e->getMessage()) && ! $forzarPvCaea && filter_var(config('gastronomia.reintentar_caea_si_error_comunicacion'), FILTER_VALIDATE_BOOLEAN)) {
                return $this->emitirFacturaDesdeCuenta($cuenta, $monedaId, $actividadArcaId, true);
            }

            return ['error' => $e->getMessage()];
        }

        if (! is_array($resultado)) {
            return ['error' => 'Respuesta inesperada del servicio de facturación.'];
        }

        if (! empty($resultado['error'])) {
            $msg = (string) $resultado['error'];

            if ($this->debeReintentarConCaea($msg) && ! $forzarPvCaea && filter_var(config('gastronomia.reintentar_caea_si_error_comunicacion'), FILTER_VALIDATE_BOOLEAN)) {
                return $this->emitirFacturaDesdeCuenta($cuenta, $monedaId, $actividadArcaId, true);
            }

            return ['error' => $msg];
        }

        $facturaTxt = $resultado['factura'] ?? '';
        $venta = $this->resolverVentaPorEtiqueta($puntoventaId, $facturaTxt);

        if (! $venta) {
            Log::error('gastronomia.emitir_factura.no_resolve_venta', ['factura' => $facturaTxt, 'pv' => $puntoventaId]);

            return ['error' => 'Factura emitida pero no se pudo recuperar el ID interno de venta; revise ARCA y la tabla venta. Texto: '.$facturaTxt];
        }

        VentaGastronomiaEmision::updateOrCreate(
            ['venta_id' => $venta->id],
            [
                'cuenta_gastronomia_id' => $cuenta->id,
                'identificador_pc' => GastronomiaIdentificadorPc::resolver(),
                'configuracion_puntoventa_gastronomia_id' => $cfg->id,
            ]
        );

        $this->cuentaService->marcarFacturada($cuenta->fresh(), $venta->id);

        $warn = null;
        try {
            $tipo = Tipotransaccion::query()->find((int) $tipoFacturaId);

            $this->consumoFormulaService->registrarMovimientosIngredientes(
                $venta,
                $cuenta->fresh(['lineas.articulo']),
                (int) $tipoFacturaId,
                $tipo->nombre ?? 'Venta',
                $payload['fechafactura'],
                $monedaId
            );
        } catch (Throwable $e) {
            Log::error('gastronomia.ingredientes.fallo', ['venta_id' => $venta->id, 'msg' => $e->getMessage()]);
            $warn = 'Factura grabada pero hubo un problema al registrar ingredientes de fórmula: '.$e->getMessage();
        }

        return array_filter([
            'venta_id' => $venta->id,
            'factura' => $facturaTxt,
            'warn' => $warn,
        ], fn ($v) => $v !== null && $v !== '');
    }

    /**
     * @return array{0:list<int>,1:list<float|string>,2:list<float|string>,3:list<string>}
     */
    private function construirArraysFactura(CuentaGastronomia $cuenta): array
    {
        $articuloIds = [];
        $cantidades = [];
        $precios = [];
        $descripciones = [];

        foreach ($cuenta->lineas as $linea) {
            $pct = (float) $linea->descuento_linea_pct;
            $precioNet = (float) $linea->precio_unitario * (1 - $pct / 100);

            $articuloIds[] = (int) $linea->articulo_id;
            $cantidades[] = (float) $linea->cantidad;
            $precios[] = $precioNet;
            $descripciones[] = '';

            foreach (($linea->opcionales_json ?? []) as $aid) {
                if (! $aid) {
                    continue;
                }

                $articuloIds[] = (int) $aid;
                $cantidades[] = (float) $linea->cantidad;
                $precios[] = 0.;
                $descripciones[] = '';
            }
        }

        return [$articuloIds, $cantidades, $precios, $descripciones];
    }

    /**
     * @return array{0:float,1:float}
     */
    private function resolverDescuentosCabecera(CuentaGastronomia $cuenta): array
    {
        $d = $cuenta->descuentoGastronomia;
        if (! $d instanceof DescuentoGastronomia) {
            return [0., 0.];
        }

        if ($d->tipovalor === DescuentoGastronomia::TIPO_PORCENTAJE) {
            return [(float) $d->valor, 0.];
        }

        if ($d->tipovalor === DescuentoGastronomia::TIPO_IMPORTE) {
            return [0., (float) $d->valor];
        }

        return [0., 0.];
    }

    private function resolverVentaPorEtiqueta(int $puntoventaId, string $facturaTxt): ?Venta
    {
        $facturaTxt = trim($facturaTxt);
        if ($facturaTxt === '') {
            return null;
        }

        if (! preg_match('/^\S+\s+\S\s+(\d+)-(\d+)$/u', $facturaTxt, $m)) {
            return null;
        }

        $numero = (int) $m[2];

        return Venta::query()
            ->where('puntoventa_id', $puntoventaId)
            ->where('numerocomprobante', $numero)
            ->orderByDesc('id')
            ->first();
    }

    private function debeReintentarConCaea(?string $mensaje): bool
    {
        if ($mensaje === null || $mensaje === '') {
            return false;
        }

        $m = strtolower($mensaje);
        foreach (['soap', 'curl', 'timeout', 'connection', 'could not connect', 'network', 'errno', 'failed to connect', 'ssl'] as $needle) {
            if (str_contains($m, $needle)) {
                return true;
            }
        }

        return false;
    }
}
