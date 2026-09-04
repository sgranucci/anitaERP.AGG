<?php

namespace App\Support\Caja\Flash;

use Carbon\Carbon;

/**
 * Perfiles de contenido del Flash Report AGG por suscripción.
 *
 * - completa: plantilla oficial (todas las hojas/columnas).
 * - finanzas: Excel acotado (drop, coin in, wins, bingo, parking, gastronomía, vending).
 */
final class FlashReporteAggPerfilVistaSupport
{
    public const COMPLETA = 'completa';

    public const FINANZAS = 'finanzas';

    /**
     * @return array<string, string>
     */
    public static function etiquetas(): array
    {
        return [
            self::COMPLETA => 'Completa',
            self::FINANZAS => 'Finanzas',
        ];
    }

    /**
     * Textos de ayuda para el selector de perfil (UI).
     *
     * @return array<string, string>
     */
    public static function descripciones(): array
    {
        return [
            self::COMPLETA => 'Plantilla oficial AGG: todas las hojas (Datos, presentación, Resumen, Tabla) y columnas del Flash.',
            self::FINANZAS => 'Excel acotado: coin in, drop, win online, win financiero, ventas bingo, parking, gastronomía y vending (por empresa + consolidado).',
        ];
    }

    public static function normalizar(?string $perfil): string
    {
        $perfil = trim((string) $perfil);
        if ($perfil === self::FINANZAS) {
            return self::FINANZAS;
        }

        return self::COMPLETA;
    }

    public static function esFinanzas(?string $perfil): bool
    {
        return self::normalizar($perfil) === self::FINANZAS;
    }

    /**
     * Encabezados del Excel acotado (orden de columnas).
     *
     * @return array<string, string> clave => título
     */
    public static function columnasFinanzas(): array
    {
        return [
            'fecha' => 'Fecha',
            'coin_in' => 'Coin in',
            'drop' => 'Drop',
            'win_online' => 'Win online',
            'win_financiero' => 'Win financiero',
            'ventas_bingo' => 'Ventas bingo',
            'ventas_parking' => 'Ventas parking',
            'ventas_gastronomia' => 'Ventas gastronomía',
            'ventas_vending' => 'Ventas vending',
        ];
    }

    /**
     * Extrae métricas de finanzas desde una fila de hoja Datos (mapeo A–BM).
     * Coin in / Drop = slots + electronic roulette (E+M / F+N).
     * Vending no está en la plantilla AGG (Anita lo suma a AyB): usar filaFinanzasDesdeMetricas.
     *
     * @param  array<string, float|int|string>  $filaDatos
     * @return array<string, float|int|string>
     */
    public static function filaFinanzasDesdeDatos(array $filaDatos, float $vending = 0.0): array
    {
        return [
            'fecha' => (string) ($filaDatos['B'] ?? ''),
            'coin_in' => round((float) ($filaDatos['E'] ?? 0) + (float) ($filaDatos['M'] ?? 0), 2),
            'drop' => round((float) ($filaDatos['F'] ?? 0) + (float) ($filaDatos['N'] ?? 0), 2),
            'win_online' => round((float) ($filaDatos['AD'] ?? 0), 2),
            'win_financiero' => round((float) ($filaDatos['AE'] ?? 0), 2),
            'ventas_bingo' => round((float) ($filaDatos['AH'] ?? 0), 2),
            'ventas_parking' => round((float) ($filaDatos['AN'] ?? 0), 2),
            'ventas_gastronomia' => round((float) ($filaDatos['AL'] ?? 0), 2),
            'ventas_vending' => round($vending, 2),
        ];
    }

    /**
     * Fila finanzas desde métricas enriquecidas del flash (incluye vending ERP separado).
     *
     * @param  array<string, mixed>  $metricas
     * @return array<string, float|int|string>
     */
    public static function filaFinanzasDesdeMetricas(array $metricas, Carbon $fecha): array
    {
        $datos = FlashReporteAggMapeoSupport::filaDatos($metricas, $fecha);

        return self::filaFinanzasDesdeDatos($datos, (float) ($metricas['vending'] ?? 0));
    }

    /**
     * @param  list<array<string, float|int|string>>  $filasFinanzas
     * @return array<string, float>
     */
    public static function totalesFinanzas(array $filasFinanzas): array
    {
        $totales = [
            'coin_in' => 0.0,
            'drop' => 0.0,
            'win_online' => 0.0,
            'win_financiero' => 0.0,
            'ventas_bingo' => 0.0,
            'ventas_parking' => 0.0,
            'ventas_gastronomia' => 0.0,
            'ventas_vending' => 0.0,
        ];
        foreach ($filasFinanzas as $fila) {
            foreach ($totales as $clave => $_) {
                $totales[$clave] += (float) ($fila[$clave] ?? 0);
            }
        }
        foreach ($totales as $clave => $valor) {
            $totales[$clave] = round($valor, 2);
        }

        return $totales;
    }
}
