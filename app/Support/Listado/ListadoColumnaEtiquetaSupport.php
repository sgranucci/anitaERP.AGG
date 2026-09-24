<?php

declare(strict_types=1);

namespace App\Support\Listado;

use App\Models\Listado\ListadoColumnaEtiqueta;
use App\Support\Database\EloquentAuditDeleteSupport;
use Illuminate\Support\Facades\Schema;

/**
 * Etiquetas de columnas por recurso (instalación).
 * Ej.: "transporte" → "reparto" / "expreso" según el cliente.
 */
final class ListadoColumnaEtiquetaSupport
{
    /** @var array<string, array<string, string>> */
    private static array $cacheMemoria = [];

    /**
     * @param  array<string, array{label: string, ...}>  $columnasCatalogo
     * @return array<string, string> key => etiqueta efectiva
     */
    public static function etiquetasEfectivas(string $recurso, array $columnasCatalogo): array
    {
        $custom = self::mapaCustom($recurso);
        $out = [];
        foreach ($columnasCatalogo as $key => $meta) {
            $out[$key] = $custom[$key] ?? (string) ($meta['label'] ?? $key);
        }

        return $out;
    }

    /**
     * @return array<string, string>
     */
    public static function mapaCustom(string $recurso): array
    {
        if (isset(self::$cacheMemoria[$recurso])) {
            return self::$cacheMemoria[$recurso];
        }

        if (! Schema::hasTable('listado_columna_etiqueta')) {
            return self::$cacheMemoria[$recurso] = [];
        }

        $mapa = ListadoColumnaEtiqueta::query()
            ->where('recurso', $recurso)
            ->pluck('etiqueta', 'columna_key')
            ->map(static fn ($e) => trim((string) $e))
            ->filter(static fn ($e) => $e !== '')
            ->all();

        return self::$cacheMemoria[$recurso] = $mapa;
    }

    /**
     * @param  array<string, string>  $etiquetas key => etiqueta (vacío = restaurar default)
     */
    public static function guardar(string $recurso, array $etiquetas, array $keysPermitidas): void
    {
        if (! Schema::hasTable('listado_columna_etiqueta')) {
            return;
        }

        self::$cacheMemoria = [];

        foreach ($etiquetas as $key => $etiqueta) {
            $key = (string) $key;
            if (! in_array($key, $keysPermitidas, true)) {
                continue;
            }
            $etiqueta = trim((string) $etiqueta);
            if ($etiqueta === '') {
                EloquentAuditDeleteSupport::each(
                    ListadoColumnaEtiqueta::query()
                        ->where('recurso', $recurso)
                        ->where('columna_key', $key)
                );
                continue;
            }
            ListadoColumnaEtiqueta::query()->updateOrCreate(
                ['recurso' => $recurso, 'columna_key' => $key],
                ['etiqueta' => mb_substr($etiqueta, 0, 120)]
            );
        }
    }
}
