<?php

namespace App\Support\Compras\PrecargaProveedor\FacturaPdfIa;

/**
 * Confianza [0..1] de una extracción PDF+IA, en base a completitud y coherencia.
 * Es un score observable (no lo emite el modelo): sirve para medir calidad y,
 * si algún día se sube el umbral, para decidir auto-aplicar.
 */
final class FacturaProveedorExtraccionScoreSupport
{
    /** Peso de cada señal; la suma de todos los pesos es 1.0. */
    private const PESO_CUIT_PROVEEDOR = 0.15;
    private const PESO_NUMERO_FACTURA = 0.15;
    private const PESO_FECHA_FACTURA = 0.10;
    private const PESO_TOTAL = 0.15;
    private const PESO_LINEAS = 0.15;
    private const PESO_COHERENCIA_TOTAL = 0.20;
    private const PESO_FUENTE_OLLAMA = 0.10;

    /** Tolerancia en pesos para considerar que las líneas cierran contra el total. */
    private const TOLERANCIA_TOTAL = 0.05;

    /**
     * @param  array<string, mixed>  $extraccion
     */
    public static function calcular(array $extraccion): float
    {
        $score = 0.0;

        if (self::tieneTexto($extraccion['cuit_proveedor'] ?? null)) {
            $score += self::PESO_CUIT_PROVEEDOR;
        }
        if (self::tieneNumero($extraccion['numero_factura'] ?? null)) {
            $score += self::PESO_NUMERO_FACTURA;
        }
        if (self::tieneTexto($extraccion['fecha_factura'] ?? null)) {
            $score += self::PESO_FECHA_FACTURA;
        }

        $total = round((float) ($extraccion['total'] ?? 0), 2);
        if ($total > 0) {
            $score += self::PESO_TOTAL;
        }

        $lineas = is_array($extraccion['lineas'] ?? null) ? $extraccion['lineas'] : [];
        if ($lineas !== []) {
            $score += self::PESO_LINEAS;
        }

        if ($total > 0 && $lineas !== []) {
            $suma = round(array_sum(array_map(
                static fn ($l) => (float) (is_array($l) ? ($l['importe'] ?? 0) : 0),
                $lineas
            )), 2);
            if (abs($suma - $total) <= self::TOLERANCIA_TOTAL) {
                $score += self::PESO_COHERENCIA_TOTAL;
            }
        }

        $fuentes = $extraccion['_meta']['fuentes'] ?? [];
        if (is_array($fuentes) && in_array('ollama', $fuentes, true)) {
            $score += self::PESO_FUENTE_OLLAMA;
        }

        return round(min(1.0, max(0.0, $score)), 4);
    }

    /**
     * Señales flojas de la extracción, para mostrar/loguear junto al score.
     *
     * @param  array<string, mixed>  $extraccion
     * @return list<string>
     */
    public static function advertencias(array $extraccion): array
    {
        $avisos = [];

        if (! self::tieneTexto($extraccion['cuit_proveedor'] ?? null)) {
            $avisos[] = 'No se detectó CUIT del proveedor en el PDF.';
        }
        if (! self::tieneNumero($extraccion['numero_factura'] ?? null)) {
            $avisos[] = 'No se detectó número de comprobante.';
        }
        if (! self::tieneTexto($extraccion['fecha_factura'] ?? null)) {
            $avisos[] = 'No se detectó fecha de factura.';
        }

        $fuentes = $extraccion['_meta']['fuentes'] ?? [];
        if (is_array($fuentes) && ! in_array('ollama', $fuentes, true)) {
            $avisos[] = 'Extracción solo heurística (modelo no disponible): revisar con atención.';
        }

        return $avisos;
    }

    private static function tieneTexto(mixed $v): bool
    {
        return is_scalar($v) && trim((string) $v) !== '';
    }

    private static function tieneNumero(mixed $v): bool
    {
        return is_numeric($v) && (float) $v > 0;
    }
}
