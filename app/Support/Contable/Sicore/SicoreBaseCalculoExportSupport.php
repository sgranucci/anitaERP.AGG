<?php

declare(strict_types=1);

namespace App\Support\Contable\Sicore;

/**
 * Base de cálculo para el TXT SICORE (tanda A+B).
 *
 * - NC (03): base = retención anulada / diferencia (no la base de la operación).
 * - ERP ganancias acumuladas (078/094/escalas): base del pago = retenible actual − retenible previo.
 * - Ret.GC Anita: neto sin IVA − MNI − lo ya sujeto del mes.
 * - OP anuladas: preferir detalle ERP (incluye REVERTIDA/BAJA); Anita sin seed puede restar MNI de más.
 */
final class SicoreBaseCalculoExportSupport
{
    /** Coeficiente IVA compras estándar: Ret.GC trae el bruto con IVA. */
    public const COEF_IVA_COMPRAS = 1.21;

    /**
     * Base a exportar desde una retención del ERP (pagoproveedor_retencion).
     *
     * @param  array<string, mixed>  $detalleCalculo
     */
    public static function baseDesdeRetencionErp(
        float $importe,
        float $baseAlmacenada,
        array $detalleCalculo = [],
    ): float {
        if ($importe < -0.0005 || ! empty($detalleCalculo['devolucion'])) {
            return round(abs($importe), 2);
        }

        return self::basePagoDesdeDetalleAcumulado($baseAlmacenada, $detalleCalculo);
    }

    /**
     * Base del pago = base retenible del período − base retenible previa del mes.
     *
     * @param  array<string, mixed>  $detalle
     */
    public static function basePagoDesdeDetalleAcumulado(float $baseAlmacenada, array $detalle): float
    {
        $baseRetenible = (float) ($detalle['base_retenible'] ?? 0);
        if ($baseRetenible <= 0.0005) {
            $baseRetenible = abs($baseAlmacenada);
        }
        if ($baseRetenible <= 0.0005) {
            return 0.0;
        }

        $netoPrevio = round((float) ($detalle['neto_acumulado_previo'] ?? 0), 2);
        if ($netoPrevio <= 0.0005) {
            return round($baseRetenible, 2);
        }

        $forma = strtoupper(trim((string) ($detalle['forma_calculo'] ?? '')));
        $inscripto = (bool) ($detalle['inscripto'] ?? true);
        $montoExc = round((float) ($detalle['monto_excedente'] ?? 0), 2);
        $restaExcedente = $forma !== 'E' && $inscripto && $montoExc > 0.0005;

        $basePrevia = $restaExcedente
            ? max(0.0, round($netoPrevio - $montoExc, 2))
            : $netoPrevio;

        return round(max(0.0, $baseRetenible - $basePrevia), 2);
    }

    /**
     * Base del pago desde bruto Anita (Ret.GC), actualizando el neto acumulado del mes.
     *
     * @param  array<string, float>  $netoAcumuladoPorClave  clave => neto gravado acumulado del mes (se muta)
     */
    public static function baseDesdeBrutoAnita(
        float $brutoConIva,
        float $montoExcedente,
        bool $restaExcedente,
        string $claveAcum,
        array &$netoAcumuladoPorClave,
    ): float {
        $neto = self::netoSinIvaDesdeBruto($brutoConIva);
        if ($neto <= 0.0005) {
            return 0.0;
        }

        $netoPrevio = round((float) ($netoAcumuladoPorClave[$claveAcum] ?? 0), 2);
        $netoNuevo = round($netoPrevio + $neto, 2);
        $netoAcumuladoPorClave[$claveAcum] = $netoNuevo;

        $mni = ($restaExcedente && $montoExcedente > 0.0005) ? round($montoExcedente, 2) : 0.0;
        $basePrevia = max(0.0, round($netoPrevio - $mni, 2));
        $baseNueva = max(0.0, round($netoNuevo - $mni, 2));

        return round(max(0.0, $baseNueva - $basePrevia), 2);
    }

    public static function netoSinIvaDesdeBruto(float $brutoConIva): float
    {
        $bruto = abs($brutoConIva);
        if ($bruto <= 0.0005) {
            return 0.0;
        }

        return round($bruto / self::COEF_IVA_COMPRAS, 2);
    }

    /**
     * Certificado de la NC: original; si falta, número de OP/asiento (plan B).
     */
    public static function nroCertificadoNc(int $nroCert, int $nroCompOriginalOActual): int
    {
        if ($nroCert > 0) {
            return $nroCert;
        }

        return max(0, $nroCompOriginalOActual);
    }

    public static function claveAcumMes(string $cuit, int $codRegimen, string $fechaIso): string
    {
        $cuit = SicoreFormatoV8Support::normalizarCuit($cuit);
        $ym = strlen($fechaIso) >= 7 ? substr($fechaIso, 0, 7) : '';

        return $cuit.'|'.$codRegimen.'|'.$ym;
    }
}
