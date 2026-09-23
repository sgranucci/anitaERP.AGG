<?php

declare(strict_types=1);

namespace App\Support\Compras\IvaCompras;

use App\Models\Compras\Comprobante_Proveedor;
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
     * Coeficiente a moneda de reporte (misma filosofía que IVA ventas).
     * Con solo_moneda_origen: convierte ME con cotización del comprobante; sin cotización válida excluye.
     */
    public static function coeficienteMoneda(
        Comprobante_Proveedor $cp,
        int $monedaReporteId,
        bool $soloMonedaOrigen,
    ): ?float {
        $monedaDoc = (int) ($cp->moneda_id ?? 1);
        $cotDoc = (float) ($cp->cotizacion ?? 0);

        if ($soloMonedaOrigen && $monedaDoc !== $monedaReporteId) {
            return $cotDoc > 0.01 ? $cotDoc : null;
        }

        if ($monedaDoc === $monedaReporteId) {
            return 1.0;
        }

        if ($cotDoc > 0.01) {
            return $cotDoc;
        }

        $fecha = $cp->fechaiva?->format('Y-m-d')
            ?? $cp->fechacomprobante?->format('Y-m-d')
            ?? date('Y-m-d');

        return CotizacionVigenteSupport::ventaValorOUno($fecha, $monedaDoc);
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
