<?php

namespace App\Support\Reportes;

use Illuminate\Support\Facades\Cache;

/**
 * Preferencias de filtros de reportes por usuario (cache vía generaKey).
 */
class ReportePreferenciasUsuario
{
    public static function clave(string $reporte, string $campo): string
    {
        return 'reporte-'.$reporte.'-'.$campo;
    }

    /**
     * @return list<int>|null
     */
    public static function leerEmpresaIds(string $reporte): ?array
    {
        $valor = cache()->get(generaKey(self::clave($reporte, 'empresa_ids')));
        if (! is_array($valor)) {
            return null;
        }

        return collect($valor)
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => $id > 0)
            ->unique()
            ->values()
            ->all();
    }

    public static function leerEmpresaId(string $reporte): ?int
    {
        $valor = cache()->get(generaKey(self::clave($reporte, 'empresa_id')));
        if ($valor === null || $valor === '') {
            return null;
        }

        $id = (int) $valor;

        return $id > 0 ? $id : null;
    }

    public static function leerBool(string $reporte, string $campo, bool $default = true): bool
    {
        $valor = cache()->get(generaKey(self::clave($reporte, $campo)));
        if ($valor === null) {
            return $default;
        }

        return (bool) $valor;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function persistir(string $reporte, array $data): void
    {
        if (isset($data['empresa_ids']) && is_array($data['empresa_ids'])) {
            $ids = collect($data['empresa_ids'])
                ->map(fn ($id) => (int) $id)
                ->filter(fn (int $id) => $id > 0)
                ->unique()
                ->values()
                ->all();
            Cache::forever(generaKey(self::clave($reporte, 'empresa_ids')), $ids);
        }

        if (isset($data['empresa_id']) && (int) $data['empresa_id'] > 0) {
            Cache::forever(generaKey(self::clave($reporte, 'empresa_id')), (int) $data['empresa_id']);
        }

        if (array_key_exists('consolidar_empresas', $data)) {
            Cache::forever(
                generaKey(self::clave($reporte, 'consolidar_empresas')),
                (bool) $data['consolidar_empresas'],
            );
        }
    }

    /**
     * @param  list<int>  $permitidos
     * @return list<int>
     */
    public static function filtrarEmpresaIdsPermitidas(array $ids, array $permitidos): array
    {
        $permitidosSet = collect($permitidos)->map(fn ($id) => (int) $id)->all();

        return collect($ids)
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => $id > 0 && in_array($id, $permitidosSet, true))
            ->unique()
            ->values()
            ->all();
    }
}
