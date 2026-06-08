<?php

namespace App\Support\Stock;

use App\Models\Stock\Configuracion_RecepcionProveedorTolerancia;

class RecepcionProveedorToleranciaSupport
{
    /**
     * @return array{cantidad_pct: float, precio_pct: float, precio_abs: float}
     */
    public static function resolver(int $empresaId, ?int $centrocostoId = null): array
    {
        $default = [
            'cantidad_pct' => (float) config('recepcion_proveedor.tolerancia_default.cantidad_pct', 0),
            'precio_pct' => (float) config('recepcion_proveedor.tolerancia_default.precio_pct', 0),
            'precio_abs' => (float) config('recepcion_proveedor.tolerancia_default.precio_absoluto', 0),
        ];

        if ($empresaId <= 0) {
            return $default;
        }

        $base = Configuracion_RecepcionProveedorTolerancia::query()
            ->where('empresa_id', $empresaId)
            ->where('activo', true)
            ->whereNull('centrocosto_id')
            ->first();

        if ($base) {
            $default = self::desdeModelo($base, $default);
        }

        if ($centrocostoId && $centrocostoId > 0) {
            $esp = Configuracion_RecepcionProveedorTolerancia::query()
                ->where('empresa_id', $empresaId)
                ->where('centrocosto_id', $centrocostoId)
                ->where('activo', true)
                ->first();
            if ($esp) {
                return self::desdeModelo($esp, $default);
            }
        }

        return $default;
    }

    public static function cantidadDentroTolerancia(float $cantOc, float $cantRec, array $tol): bool
    {
        if ($cantOc <= 0) {
            return true;
        }
        $pct = (float) ($tol['cantidad_pct'] ?? 0);
        if ($pct <= 0) {
            return abs($cantRec - $cantOc) < 0.000001;
        }
        $diffPct = abs($cantRec - $cantOc) / $cantOc * 100;

        return $diffPct <= $pct;
    }

    public static function precioDentroTolerancia(float $precioOc, float $precioRec, array $tol): bool
    {
        $absTol = (float) ($tol['precio_abs'] ?? 0);
        if ($absTol > 0 && abs($precioRec - $precioOc) <= $absTol) {
            return true;
        }
        $pct = (float) ($tol['precio_pct'] ?? 0);
        if ($pct <= 0) {
            return abs($precioRec - $precioOc) < 0.0001;
        }
        if ($precioOc <= 0) {
            return abs($precioRec - $precioOc) < 0.0001;
        }
        $diffPct = abs($precioRec - $precioOc) / $precioOc * 100;

        return $diffPct <= $pct;
    }

    /** @param array{cantidad_pct: float, precio_pct: float, precio_abs: float} $fallback */
    private static function desdeModelo(Configuracion_RecepcionProveedorTolerancia $m, array $fallback): array
    {
        return [
            'cantidad_pct' => (float) ($m->tolerancia_cantidad_pct ?? $fallback['cantidad_pct']),
            'precio_pct' => (float) ($m->tolerancia_precio_pct ?? $fallback['precio_pct']),
            'precio_abs' => (float) ($m->tolerancia_precio_absoluto ?? $fallback['precio_abs']),
        ];
    }
}
