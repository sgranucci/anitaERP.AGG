<?php

namespace App\Support\Configuracion;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;

/**
 * Catálogo de modelos/tablas auditables para el panel inteligente.
 *
 * El GROUP BY sobre audits (~millones) NO corre en el request web:
 * se usa cache si existe; si no, se arma desde config/favoritos y se
 * calienta el cache al terminar la respuesta (FPM).
 */
class AuditoriaDatosCatalogoSupport
{
    private const CACHE_TYPES = 'auditoria_datos.catalogo_types';

    private const CACHE_WARMING = 'auditoria_datos.catalogo_types_warming';

    /**
     * @return list<array{
     *   type: string,
     *   etiqueta: string,
     *   tabla: string,
     *   modulo: string,
     *   favorito: bool,
     *   eventos: int|null
     * }>
     */
    public static function catalogo(): array
    {
        $ancladosMeta = [];
        if (Schema::hasTable('usuario_auditoria_favorito') && auth()->check()) {
            foreach (AuditoriaDatosFavoritoSupport::listar() as $fav) {
                $ancladosMeta[$fav['auditable_type']] = $fav;
            }
        }

        if (! Schema::hasTable('audits')) {
            return self::desdeSoloFavoritosUsuario($ancladosMeta);
        }

        /** @var list<array{type:string,eventos:int}>|null $desdeDb */
        $desdeDb = Cache::get(self::CACHE_TYPES);
        if (! is_array($desdeDb)) {
            $desdeDb = [];
            self::programarCalentamientoCatalogo();
        }

        $porType = [];

        foreach ($ancladosMeta as $type => $fav) {
            $porType[$type] = [
                'type' => $type,
                'etiqueta' => (string) $fav['etiqueta'],
                'tabla' => (string) $fav['tabla'],
                'modulo' => (string) $fav['modulo'],
                'favorito' => true,
                'eventos' => null,
            ];
        }

        // Semilla config + ABM + columnas de búsqueda (sin tocar audits).
        foreach (self::tiposDesdeConfig() as $type => $meta) {
            if (isset($porType[$type])) {
                continue;
            }
            $porType[$type] = [
                'type' => $type,
                'etiqueta' => (string) ($meta['etiqueta'] ?? class_basename($type)),
                'tabla' => (string) ($meta['tabla'] ?? self::inferirTabla($type)),
                'modulo' => (string) ($meta['modulo'] ?? self::inferirModulo($type)),
                'favorito' => false,
                'eventos' => null,
            ];
        }

        foreach ($desdeDb as $row) {
            $type = (string) ($row['type'] ?? '');
            if ($type === '') {
                continue;
            }
            if (isset($porType[$type])) {
                $porType[$type]['eventos'] = (int) ($row['eventos'] ?? 0);
                continue;
            }
            $porType[$type] = [
                'type' => $type,
                'etiqueta' => class_basename($type),
                'tabla' => self::inferirTabla($type),
                'modulo' => self::inferirModulo($type),
                'favorito' => false,
                'eventos' => (int) ($row['eventos'] ?? 0),
            ];
        }

        $lista = array_values($porType);
        usort($lista, static function (array $a, array $b) {
            if ($a['favorito'] !== $b['favorito']) {
                return $a['favorito'] ? -1 : 1;
            }
            $cmp = strcasecmp($a['modulo'], $b['modulo']);
            if ($cmp !== 0) {
                return $cmp;
            }

            return strcasecmp($a['etiqueta'], $b['etiqueta']);
        });

        return $lista;
    }

    /**
     * Tipos conocidos sin consultar audits (config del panel).
     *
     * @return array<string, array{etiqueta?:string,tabla?:string,modulo?:string}>
     */
    public static function tiposDesdeConfig(): array
    {
        $out = [];

        foreach ((array) config('auditoria_datos.favoritos', []) as $type => $meta) {
            if (is_string($type) && $type !== '') {
                $out[$type] = is_array($meta) ? $meta : [];
            }
        }

        foreach (array_keys((array) config('auditoria_datos.abm_consulta', [])) as $type) {
            if (! is_string($type) || isset($out[$type])) {
                continue;
            }
            $out[$type] = [
                'etiqueta' => class_basename($type),
                'tabla' => self::inferirTabla($type),
                'modulo' => self::inferirModulo($type),
            ];
        }

        foreach (array_keys((array) config('auditoria_datos.busqueda_registro', [])) as $type) {
            if (! is_string($type) || isset($out[$type])) {
                continue;
            }
            $out[$type] = [
                'etiqueta' => class_basename($type),
                'tabla' => self::inferirTabla($type),
                'modulo' => self::inferirModulo($type),
            ];
        }

        return $out;
    }

    public static function etiquetaTipo(string $type): string
    {
        $fav = config('auditoria_datos.favoritos.'.$type);
        if (is_array($fav) && ! empty($fav['etiqueta'])) {
            return (string) $fav['etiqueta'];
        }

        return class_basename($type) ?: $type;
    }

    public static function invalidarCacheCatalogo(): void
    {
        Cache::forget(self::CACHE_TYPES);
        Cache::forget(self::CACHE_WARMING);
    }

    /**
     * Recalcula contadores por auditable_type (pesado: usar en warm/schedule).
     *
     * @return list<array{type:string,eventos:int}>
     */
    public static function recalcularCatalogoTypes(): array
    {
        if (! Schema::hasTable('audits')) {
            return [];
        }

        $ttl = (int) config('auditoria_datos.catalogo_cache_segundos', 3600);
        $lista = DB::table('audits')
            ->select('auditable_type', DB::raw('COUNT(*) as eventos'))
            ->groupBy('auditable_type')
            ->orderBy('auditable_type')
            ->get()
            ->map(static fn ($r) => [
                'type' => (string) $r->auditable_type,
                'eventos' => (int) $r->eventos,
            ])
            ->all();

        Cache::put(self::CACHE_TYPES, $lista, $ttl);

        return $lista;
    }

    public static function inferirTablaPublica(string $type): string
    {
        return self::inferirTabla($type);
    }

    public static function inferirModuloPublico(string $type): string
    {
        return self::inferirModulo($type);
    }

    /**
     * ¿Tipo válido para filtrar? Sin escanear audits.
     */
    public static function tipoConocido(string $type): bool
    {
        if ($type === '' || ! str_starts_with($type, 'App\\Models\\')) {
            return false;
        }
        if (isset(self::tiposDesdeConfig()[$type])) {
            return true;
        }
        if (Schema::hasTable('usuario_auditoria_favorito') && auth()->check()) {
            foreach (AuditoriaDatosFavoritoSupport::listar() as $fav) {
                if (($fav['auditable_type'] ?? '') === $type) {
                    return true;
                }
            }
        }
        $cached = Cache::get(self::CACHE_TYPES);
        if (is_array($cached)) {
            foreach ($cached as $row) {
                if (($row['type'] ?? '') === $type) {
                    return true;
                }
            }
        }
        if (class_exists($type) && is_subclass_of($type, AuditableContract::class)) {
            return true;
        }

        return false;
    }

    /**
     * @param  array<string, array{auditable_type:string,etiqueta:string,tabla:string,modulo:string}>  $ancladosMeta
     * @return list<array{type:string,etiqueta:string,tabla:string,modulo:string,favorito:bool,eventos:null}>
     */
    private static function desdeSoloFavoritosUsuario(array $ancladosMeta): array
    {
        $out = [];
        foreach ($ancladosMeta as $type => $fav) {
            $out[] = [
                'type' => $type,
                'etiqueta' => (string) $fav['etiqueta'],
                'tabla' => (string) $fav['tabla'],
                'modulo' => (string) $fav['modulo'],
                'favorito' => true,
                'eventos' => null,
            ];
        }

        return $out;
    }

    private static function programarCalentamientoCatalogo(): void
    {
        if (Cache::has(self::CACHE_WARMING)) {
            return;
        }
        Cache::put(self::CACHE_WARMING, 1, 180);

        app()->terminating(static function () {
            try {
                if (Cache::has(self::CACHE_TYPES)) {
                    return;
                }
                self::recalcularCatalogoTypes();
            } catch (\Throwable) {
                // No tumbar el response si el warm falla.
            } finally {
                Cache::forget(self::CACHE_WARMING);
            }
        });
    }

    private static function inferirTabla(string $type): string
    {
        if (! class_exists($type)) {
            return Str::snake(class_basename($type));
        }
        try {
            return (string) (new $type)->getTable();
        } catch (\Throwable) {
            return Str::snake(class_basename($type));
        }
    }

    private static function inferirModulo(string $type): string
    {
        if (preg_match('#\\\\Models\\\\([^\\\\]+)\\\\#', $type, $m)) {
            return $m[1];
        }

        return 'Otros';
    }
}
