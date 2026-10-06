<?php

declare(strict_types=1);

namespace App\Support\Compras\IvaCompras;

use App\Models\Caja\Caja_Movimiento_Cuentacaja;
use App\Models\Compras\Comprobante_Proveedor;
use App\Support\Compras\ComprobanteProveedorMonedaMotor;
use App\Support\Configuracion\CotizacionVigenteSupport;

/**
 * Desglose monetario de un comprobante proveedor para columnas IVA compras
 * (equivalente calcula_concepto / concmov → colivacomp en l-compra.c).
 */
final class IvaComprasDesgloseSupport
{
    /**
     * @return array{
     *   columnas: array<string, float>,
     *   rubros: array{neto: float, iva: float, perc_iva: float, perc_iibb: float, otros: float}
     * }
     */
    public static function desgloseDesdeComprobante(Comprobante_Proveedor $cp, float $coefMoneda = 1.0): array
    {
        $montos = IvaComprasColumnasSupport::montosVacios();
        $rubros = [
            'neto' => 0.0,
            'iva' => 0.0,
            'perc_iva' => 0.0,
            'perc_iibb' => 0.0,
            'otros' => 0.0,
        ];
        $signo = self::signoComprobante($cp);
        $factor = $signo * $coefMoneda;

        foreach ($cp->comprobante_proveedor_conceptos ?? [] as $linea) {
            $concepto = $linea->concepto_ivacompras;
            if ($concepto === null) {
                continue;
            }
            $importe = round((float) ($linea->monto ?? 0) * $factor, 2);
            if (abs($importe) < 0.0001) {
                continue;
            }

            $columnaId = (int) ($concepto->columna_ivacompra_id ?? 0);
            if ($columnaId > 0) {
                $key = IvaComprasColumnasSupport::keyDesdeId($columnaId);
                if (array_key_exists($key, $montos)) {
                    $montos[$key] = round($montos[$key] + $importe, 2);
                }
            }

            $tipo = strtoupper(trim((string) ($concepto->tipoconcepto ?? '')));
            $bucket = match ($tipo) {
                'I' => 'iva',
                'G', 'N', 'E', '0' => 'neto',
                'P' => 'perc_iva',
                'B', 'S', 'M' => 'perc_iibb',
                default => 'otros',
            };
            $rubros[$bucket] = round($rubros[$bucket] + $importe, 2);
        }

        $montos[IvaComprasColumnasSupport::KEY_TOTAL] = round(
            (float) ($cp->total ?? 0) * $factor,
            2,
        );

        return [
            'columnas' => $montos,
            'rubros' => $rubros,
        ];
    }

    /**
     * @return array<string, float>
     */
    public static function columnasDesdeComprobante(Comprobante_Proveedor $cp, float $coefMoneda = 1.0): array
    {
        return self::desgloseDesdeComprobante($cp, $coefMoneda)['columnas'];
    }

    public static function signoComprobante(Comprobante_Proveedor $cp): float
    {
        $raw = (int) ($cp->tipotransaccion_compras?->getRawOriginal('signo') ?? 1);

        return $raw < 0 ? -1.0 : ($raw === 0 ? 0.0 : 1.0);
    }

    /**
     * Coeficiente para expresar el nominal del comprobante en la moneda del listado.
     *
     * Una cotización 0 ó 1 en moneda extranjera no convierte: se usa la del movimiento
     * de caja y, si no hay, la vigente. En la misma moneda el coeficiente es 1
     * (el listado en dólares no multiplica el nominal por el tipo de cambio).
     */
    public static function coeficienteMoneda(
        Comprobante_Proveedor $cp,
        int $monedaReporteId,
        bool $soloMonedaOrigen,
    ): ?float {
        $monedaDoc = (int) ($cp->moneda_id ?? 1);
        if ($monedaDoc === $monedaReporteId) {
            return 1.0;
        }

        $tasa = self::tasaParaConversion($cp, $soloMonedaOrigen);
        if ($tasa === null || $tasa <= 0) {
            return null;
        }

        $coef = calculaCoeficienteMoneda($monedaReporteId, $monedaDoc, $tasa);

        return $coef > 0 ? (float) $coef : null;
    }

    /**
     * Pesos por unidad de moneda extranjera. Null si hay que excluir el comprobante.
     */
    private static function tasaParaConversion(Comprobante_Proveedor $cp, bool $exigirValida): ?float
    {
        $monedaDoc = (int) ($cp->moneda_id ?? 1);
        if (! ComprobanteProveedorMonedaMotor::esMonedaExtranjera($monedaDoc)) {
            return 1.0;
        }

        $cot = (float) ($cp->cotizacion ?? 0);
        if ($cot > ComprobanteProveedorMonedaMotor::COTIZACION_MINIMA) {
            return $cot;
        }

        $deCaja = self::cotizacionMovimientoCaja($cp, $monedaDoc);
        if ($deCaja > ComprobanteProveedorMonedaMotor::COTIZACION_MINIMA) {
            return $deCaja;
        }

        $fecha = $cp->fechaiva?->format('Y-m-d')
            ?? $cp->fechacomprobante?->format('Y-m-d')
            ?? date('Y-m-d');
        $vigente = CotizacionVigenteSupport::ventaValor($fecha, $monedaDoc);
        if ($vigente > ComprobanteProveedorMonedaMotor::COTIZACION_MINIMA) {
            return $vigente;
        }

        if ($exigirValida) {
            return null;
        }

        $fallback = CotizacionVigenteSupport::ventaValorOUno($fecha, $monedaDoc);

        return $fallback > 0 ? $fallback : null;
    }

    /** @var array<string, float> */
    private static array $cotizacionCajaCache = [];

    private static function cotizacionMovimientoCaja(Comprobante_Proveedor $cp, int $monedaId): float
    {
        $cajaId = (int) ($cp->caja_movimiento_id ?? 0);
        if ($cajaId <= 0 || $monedaId <= 0) {
            return 0.0;
        }

        $clave = $cajaId.':'.$monedaId;
        if (! array_key_exists($clave, self::$cotizacionCajaCache)) {
            $valor = Caja_Movimiento_Cuentacaja::query()
                ->where('caja_movimiento_id', $cajaId)
                ->where('moneda_id', $monedaId)
                ->where('cotizacion', '>', ComprobanteProveedorMonedaMotor::COTIZACION_MINIMA)
                ->orderByDesc('id')
                ->value('cotizacion');
            self::$cotizacionCajaCache[$clave] = $valor !== null ? (float) $valor : 0.0;
        }

        return self::$cotizacionCajaCache[$clave];
    }

    /**
     * @return array{neto: float, iva: float, perc_iva: float, perc_iibb: float, otros: float}
     */
    public static function rubrosVacios(): array
    {
        return [
            'neto' => 0.0,
            'iva' => 0.0,
            'perc_iva' => 0.0,
            'perc_iibb' => 0.0,
            'otros' => 0.0,
        ];
    }
}
