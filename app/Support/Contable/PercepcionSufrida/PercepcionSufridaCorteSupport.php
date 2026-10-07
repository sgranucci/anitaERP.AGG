<?php

declare(strict_types=1);

namespace App\Support\Contable\PercepcionSufrida;

/**
 * Hasta la fecha límite inclusive el mayor se lee de Anita.
 * Desde el día siguiente, solo del ERP.
 */
final class PercepcionSufridaCorteSupport
{
    public const TIPO_IIBB = 'iibb';

    public const TIPO_IVA = 'iva';

    public static function fechaLimiteIso(): string
    {
        $raw = trim((string) config('contable.percepcion_sufrida.fecha_limite_anita', '2026-09-30'));
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $raw) !== 1) {
            return '2026-09-30';
        }

        return $raw;
    }

    public static function fechaLimiteDma(): string
    {
        $iso = self::fechaLimiteIso();

        return substr($iso, 8, 2).'/'.substr($iso, 5, 2).'/'.substr($iso, 0, 4);
    }

    public static function cuentaIibb(): int
    {
        return max(0, (int) config('contable.percepcion_sufrida.cuenta_iibb', 214010004));
    }

    public static function cuentaIva(): int
    {
        return max(0, (int) config('contable.percepcion_sufrida.cuenta_iva', 114010009));
    }

    public static function cuentaDeTipo(string $tipo): int
    {
        return $tipo === self::TIPO_IVA ? self::cuentaIva() : self::cuentaIibb();
    }

    public static function regimenPercepcionIva(): int
    {
        return max(0, (int) config('contable.percepcion_sufrida.regimen_percepcion_iva', 493));
    }

    /**
     * @return array{
     *     anita_desde: string,
     *     anita_hasta: string,
     *     erp_desde: string,
     *     erp_hasta: string
     * }
     */
    public static function partirRango(string $fechaDesde, string $fechaHasta): array
    {
        $limite = self::fechaLimiteIso();
        $siguiente = self::diaSiguiente($limite);
        $vacio = ['anita_desde' => '', 'anita_hasta' => '', 'erp_desde' => '', 'erp_hasta' => ''];

        if ($fechaDesde === '' || $fechaHasta === '' || $fechaDesde > $fechaHasta) {
            return $vacio;
        }

        if ($fechaHasta <= $limite) {
            $vacio['anita_desde'] = $fechaDesde;
            $vacio['anita_hasta'] = $fechaHasta;

            return $vacio;
        }

        if ($fechaDesde > $limite) {
            $vacio['erp_desde'] = $fechaDesde;
            $vacio['erp_hasta'] = $fechaHasta;

            return $vacio;
        }

        $vacio['anita_desde'] = $fechaDesde;
        $vacio['anita_hasta'] = $limite;
        $vacio['erp_desde'] = $siguiente;
        $vacio['erp_hasta'] = $fechaHasta;

        return $vacio;
    }

    public static function diaSiguiente(string $iso): string
    {
        $dt = \DateTimeImmutable::createFromFormat('Y-m-d', $iso);
        if (! $dt) {
            return $iso;
        }

        return $dt->modify('+1 day')->format('Y-m-d');
    }
}
