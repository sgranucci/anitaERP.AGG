<?php

declare(strict_types=1);

namespace App\Support\Contable\IngresosBrutos;

/**
 * Archivo ASCII Rentas Río Negro (Anita p-ingbruto, aplicativo RIONEGRO = 3).
 *
 * Opción «Ret. Compras»: una línea CSV por retención, terminador LF.
 * Anita (fprintf):
 *   %5d,1,1,%012ld,%11.11s,%10.10s,%12.2lf,%5.2lf,%12.2lf,050,916\n
 *
 * Campos: renglón, tipo registro 1, tipo operación 1, nro. retención,
 * CUIT sujeto, fecha dd/mm/yyyy, base, alícuota, importe (base×alícuota/100
 * redondeado como rdn del C), régimen 050, jurisdicción AFIP 916.
 */
final class IngresosBrutosFormatoRioNegroSupport
{
    public const EOL = "\n";

    /** Código de régimen fijo del aplicativo Rentas RN. */
    public const CODIGO_REGIMEN = '050';

    /** Jurisdicción AFIP Río Negro. */
    public const JURISDICCION = '916';

    /**
     * @param  list<array<string, mixed>>  $registros
     */
    public static function generarArchivo(array $registros): string
    {
        $out = '';
        $renglon = 0;
        foreach ($registros as $reg) {
            // Devoluciones / créditos: el C usa ABS() y las sumaría como retención.
            // No se informan acá (mismo criterio que ARBA).
            if ((float) ($reg['importe'] ?? 0) < -0.001) {
                continue;
            }
            $out .= self::formatearLineaRetencion($reg, ++$renglon);
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $reg
     */
    public static function formatearLineaRetencion(array $reg, int $renglon): string
    {
        $nroRet = (int) ($reg['nro_cert'] ?? $reg['nro_comp'] ?? 0);
        $cuit = IngresosBrutosFormatoArbaSupport::normalizarCuit((string) ($reg['nro_documento'] ?? ''));
        $fecha = IngresosBrutosFormatoArbaSupport::fechaArba((string) ($reg['fecha_retencion'] ?? ''));
        $base = abs((float) ($reg['base_calculo'] ?? 0));
        $alicuota = (float) ($reg['alicuota'] ?? 0);
        // Anita: ABS(rdn(monto * alicuota / 100)).
        $importe = self::redondearAnita($base * $alicuota / 100.0);

        return sprintf(
            '%5d,1,1,%012d,%11s,%10s,%12.2f,%5.2f,%12.2f,%s,%s',
            max(1, $renglon),
            $nroRet,
            $cuit,
            $fecha,
            $base,
            $alicuota,
            $importe,
            self::CODIGO_REGIMEN,
            self::JURISDICCION,
        ).self::EOL;
    }

    public static function nombreArchivo(string $cuitAgente, string $periodoYm): string
    {
        $cuit = IngresosBrutosFormatoArbaSupport::normalizarCuit($cuitAgente);
        $periodo = preg_replace('/\D/', '', $periodoYm) ?: date('Ym');

        // Nombre comentado en el C Crown: R{cuit}_{YYYY}{MM}{nn}.txt (nn = presentación).
        return sprintf('R%s_%s01.txt', $cuit, $periodo);
    }

    /**
     * Equivalente práctico de rdn() Anita: redondeo a 2 decimales (half-up).
     */
    public static function redondearAnita(float $valor): float
    {
        return round(abs($valor), 2);
    }
}
