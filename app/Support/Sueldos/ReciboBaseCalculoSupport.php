<?php

namespace App\Support\Sueldos;

/**
 * Columna BASE del recibo Anexo III (Dto. 407).
 * Presentación únicamente: no altera el importe liquidado.
 *
 * Modos alineados a Onvio/Bejerman:
 *  - sin_valor (sugerido): usa valor unitario si hay; si no, importe/cantidad
 *  - siempre: siempre importe/cantidad (reemplaza valor)
 *  - no: no calcula
 *
 * Unidad %: base = importe / (cantidad/100).
 */
class ReciboBaseCalculoSupport
{
    public const MODO_SIN_VALOR = 'sin_valor';

    public const MODO_SIEMPRE = 'siempre';

    public const MODO_NO = 'no';

    /**
     * @param  bool  $tieneCantidadExplicita  formula_cantidad / novedad de cantidad (no el default 1)
     * @param  bool  $tieneValorExplicito     formula_valor o valor unitario informado
     */
    public static function derivar(
        float $importe,
        float $cantidad,
        float $valor,
        ?string $unidad = null,
        bool $tieneCantidadExplicita = true,
        bool $tieneValorExplicito = false,
        ?string $modo = null,
    ): ?float {
        $modo = $modo ?? (string) config('sueldos.recibo_base_modo', self::MODO_SIN_VALOR);
        if ($modo === self::MODO_NO) {
            return null;
        }

        $unidad = self::normalizarUnidad($unidad);
        $esPorcentaje = $unidad === '%';

        if ($modo === self::MODO_SIN_VALOR && $tieneValorExplicito && abs($valor) > 0.0000001 && ! $esPorcentaje) {
            return round($valor, 2);
        }

        if (! $tieneCantidadExplicita && ! $esPorcentaje) {
            // Sin cantidad real (default 1 del motor): no inventar BASE = importe.
            if ($modo === self::MODO_SIN_VALOR && $tieneValorExplicito && abs($valor) > 0.0000001) {
                return round($valor, 2);
            }

            return null;
        }

        if (abs($cantidad) < 0.0000001) {
            return null;
        }

        if ($esPorcentaje) {
            return round($importe / ($cantidad / 100.0), 2);
        }

        return round($importe / $cantidad, 2);
    }

    public static function normalizarUnidad(?string $unidad): string
    {
        $u = strtoupper(trim((string) $unidad));
        if ($u === '' || $u === 'EN BLANCO' || $u === '-') {
            return '';
        }
        // LSD: $ % A M Q S D H
        if (in_array($u, ['$', '%', 'A', 'M', 'Q', 'S', 'D', 'H'], true)) {
            return $u;
        }
        if ($u === 'PESOS' || $u === 'ARS') {
            return '$';
        }
        if (str_starts_with($u, '%')) {
            return '%';
        }

        return mb_substr($u, 0, 4);
    }

    /**
     * Infiere unidad LSD desde descripción / factor / tipo (para precarga).
     */
    public static function inferirUnidad(?string $descripcion, ?float $factor = null, ?string $tipo = null): ?string
    {
        $d = mb_strtoupper(trim((string) $descripcion), 'UTF-8');
        $d = strtr($d, ['Á' => 'A', 'É' => 'E', 'Í' => 'I', 'Ó' => 'O', 'Ú' => 'U']);

        if ($d === '') {
            return null;
        }

        // Horas/días antes que %: "HORAS EXTRAS 100%" es cantidad en H, no alícuota.
        if (preg_match('/\bHS\.?\b|HORAS?\b|HORA\b/', $d)) {
            return 'H';
        }

        if (preg_match('/\bDIAS?\b|D[IÍ]AS?\b|JORNALES?\b/', $d) && ! preg_match('/\bHS\.?\b|HORAS?\b/', $d)) {
            return 'D';
        }

        if (str_contains($d, '%') || preg_match('/\b\d{1,2}(\.\d+)?\s*%/', $d)) {
            return '%';
        }

        // Retenciones/aportes típicos con factor tipo alícuota (0.11 o 11)
        if (in_array($tipo, ['retencion', 'aporte', 'descuento', 'contribucion'], true) && $factor !== null) {
            $f = abs((float) $factor);
            if ($f > 0 && $f < 1) {
                return '%'; // 0.11
            }
            if ($f >= 1 && $f <= 100 && abs($f - round($f)) < 0.0001) {
                // 11, 3, 5… típico % en Anita factor
                if (preg_match('/JUBIL|LEY 19|OBRA SOC|PAMI|INSSJP|SIJP|SINDIC|CONTRIBUCION|APORTE/', $d)) {
                    return '%';
                }
            }
        }

        if (preg_match('/JUBILACION|LEY 19\.?032|OBRA SOCIAL \d|INSSJP|SIJP \d/', $d)) {
            return '%';
        }

        return null;
    }
}
