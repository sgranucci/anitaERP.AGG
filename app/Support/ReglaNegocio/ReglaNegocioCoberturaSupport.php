<?php

namespace App\Support\ReglaNegocio;

use App\Models\Configuracion\ReglaNegocio;
use Illuminate\Support\Facades\Schema;

/**
 * Panel de cobertura: catálogo de puntos vs reglas y cableado en código.
 */
final class ReglaNegocioCoberturaSupport
{
    /**
     * Puntos con Support/Service que ya consultan el BRE.
     *
     * @return array<string, string> punto => clase/support
     */
    public static function puntosCableados(): array
    {
        return (array) config('regla_negocio.puntos_cableados', []);
    }

    /**
     * @return list<array{
     *   punto: string,
     *   etiqueta: string,
     *   dominio: string,
     *   total: int,
     *   activas: int,
     *   cableado: bool,
     *   cableado_en: string|null,
     *   estado: string
     * }>
     */
    public static function resumen(): array
    {
        $catalogo = (array) config('regla_negocio_puntos.catalogo', []);
        $cableados = self::puntosCableados();
        $counts = [];
        $activas = [];

        try {
            if (Schema::hasTable('regla_negocio')) {
                foreach (ReglaNegocio::query()->get(['punto', 'activo']) as $row) {
                    $punto = (string) $row->punto;
                    $counts[$punto] = ($counts[$punto] ?? 0) + 1;
                    if ($row->activo) {
                        $activas[$punto] = ($activas[$punto] ?? 0) + 1;
                    }
                }
            }
        } catch (\Throwable) {
            // Sin driver/DB (tests PHPUnit): solo catálogo + cableado.
        }

        $out = [];
        foreach ($catalogo as $punto => $meta) {
            $total = $counts[$punto] ?? 0;
            $act = $activas[$punto] ?? 0;
            $cableado = array_key_exists($punto, $cableados);
            $estado = match (true) {
                $cableado && $act > 0 => 'operativo',
                $cableado && $total > 0 => 'cableado_sin_activas',
                $cableado => 'cableado_sin_reglas',
                $total > 0 => 'reglas_sin_cableado',
                default => 'catalogo',
            };
            $out[] = [
                'punto' => (string) $punto,
                'etiqueta' => (string) ($meta['etiqueta'] ?? $punto),
                'dominio' => (string) ($meta['dominio'] ?? ''),
                'total' => $total,
                'activas' => $act,
                'cableado' => $cableado,
                'cableado_en' => $cableado ? (string) $cableados[$punto] : null,
                'estado' => $estado,
            ];
        }

        return $out;
    }
}
