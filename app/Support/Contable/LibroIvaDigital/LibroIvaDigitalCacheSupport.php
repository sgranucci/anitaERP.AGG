<?php

declare(strict_types=1);

namespace App\Support\Contable\LibroIvaDigital;

use Illuminate\Support\Facades\Cache;

/**
 * Cache del Libro IVA Digital por empresa + período + opciones.
 * Evita regenerar (ERP + Anita) al descargar el ZIP después de consultar.
 */
final class LibroIvaDigitalCacheSupport
{
    private const TTL_MINUTES = 45;

    private const VERSION = 'v2';

    /**
     * @param  array{
     *     por_fecha_jornada?: bool,
     *     prorrateo_cf_global?: bool,
     *     completar_compras_anita?: bool,
     *     completar_fsl_anita?: bool
     * }  $opciones
     */
    public static function firma(int $empresaId, int $anio, int $mes, array $opciones): string
    {
        return hash('sha256', (string) json_encode([
            self::VERSION,
            $empresaId,
            $anio,
            $mes,
            (int) (bool) ($opciones['por_fecha_jornada'] ?? false),
            (int) (bool) ($opciones['prorrateo_cf_global'] ?? false),
            (int) (bool) ($opciones['completar_compras_anita'] ?? true),
            (int) (bool) ($opciones['completar_fsl_anita'] ?? true),
        ]));
    }

    public static function cacheKey(string $firma): string
    {
        return 'libro_iva_digital:'.self::VERSION.':'.$firma;
    }

    public static function lockKey(string $firma): string
    {
        return 'libro_iva_digital_lock:'.self::VERSION.':'.$firma;
    }

    /**
     * Quita arreglos internos (registros / detalle) que no van a pantalla ni al ZIP.
     *
     * @param  array<string, mixed>  $resultado
     * @return array<string, mixed>
     */
    public static function compactar(array $resultado): array
    {
        unset(
            $resultado['ventas']['registros'],
            $resultado['compras']['registros'],
            $resultado['importaciones']['registros'],
            $resultado['iva_simple']['detalle_debito'],
            $resultado['iva_simple']['detalle_restitucion_debito'],
            $resultado['iva_simple']['detalle_credito'],
            $resultado['iva_simple']['detalle_restitucion_credito'],
        );

        return $resultado;
    }

    /**
     * @param  array<string, mixed>  $resultado
     */
    public static function guardar(string $firma, array $resultado): void
    {
        Cache::put(
            self::cacheKey($firma),
            self::compactar($resultado),
            now()->addMinutes(self::TTL_MINUTES),
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function leer(string $firma): ?array
    {
        $cached = Cache::get(self::cacheKey($firma));

        return is_array($cached) ? $cached : null;
    }
}
