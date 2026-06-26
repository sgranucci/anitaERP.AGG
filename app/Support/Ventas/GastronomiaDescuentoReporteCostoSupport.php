<?php

namespace App\Support\Ventas;

use Illuminate\Support\Facades\Cache;

/**
 * Dispara el cierre mensual de costos catálogo si el reporte detecta artículos sin costo en lista 5000+mes.
 */
final class GastronomiaDescuentoReporteCostoSupport
{
    private const CACHE_KEY = 'gastronomia_descuento_reporte_costo_disparado';

    /**
     * @param  array<string, mixed>  $resultado
     */
    public static function contarFilasSinCosto(array $resultado): int
    {
        $sinCosto = 0;
        foreach ($resultado['bloques'] ?? [] as $bloque) {
            foreach ($bloque['filas'] ?? [] as $fila) {
                if ((float) ($fila['costo_unitario'] ?? 0) <= 0.0001) {
                    $sinCosto++;
                }
            }
        }

        return $sinCosto;
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    public static function dispararActualizacionCostoEnBackground(array $filtros): bool
    {
        $fecha = trim((string) ($filtros['fecha_hasta'] ?? ''));
        if ($fecha === '') {
            $fecha = date('Y-m-d');
        }

        $firma = $fecha.'|'.(int) ($filtros['empresa_id'] ?? 0);
        if (Cache::has(self::CACHE_KEY.':'.$firma)) {
            return false;
        }

        Cache::put(self::CACHE_KEY.':'.$firma, time(), now()->addHours(2));

        $log = storage_path('logs/costo_mensual_catalogo.log');
        $artisan = escapeshellarg(base_path('artisan'));
        $fechaEsc = escapeshellarg($fecha);
        $logEsc = escapeshellarg($log);
        $cmd = "php {$artisan} gastronomia:actualizar-costo-mensual-catalogo --fecha={$fechaEsc} --sin-anita >> {$logEsc} 2>&1 &";

        exec($cmd);

        return true;
    }
}
