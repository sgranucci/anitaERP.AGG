<?php

namespace App\Support\Configuracion;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Catálogo de modelos/tablas auditables para el panel inteligente.
 */
class AuditoriaDatosCatalogoSupport
{
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

        $ttl = (int) config('auditoria_datos.catalogo_cache_segundos', 3600);

        /** @var list<array{type:string,eventos:int}> $desdeDb */
        $desdeDb = Cache::remember('auditoria_datos.catalogo_types', $ttl, static function () {
            return DB::table('audits')
                ->select('auditable_type', DB::raw('COUNT(*) as eventos'))
                ->groupBy('auditable_type')
                ->orderBy('auditable_type')
                ->get()
                ->map(static fn ($r) => [
                    'type' => (string) $r->auditable_type,
                    'eventos' => (int) $r->eventos,
                ])
                ->all();
        });

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

        // Sugeridos de config (sin chincheta del usuario) solo si aún no están.
        foreach ((array) config('auditoria_datos.favoritos', []) as $type => $meta) {
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
            $type = $row['type'];
            if ($type === '') {
                continue;
            }
            if (isset($porType[$type])) {
                $porType[$type]['eventos'] = $row['eventos'];
                continue;
            }
            $porType[$type] = [
                'type' => $type,
                'etiqueta' => class_basename($type),
                'tabla' => self::inferirTabla($type),
                'modulo' => self::inferirModulo($type),
                'favorito' => false,
                'eventos' => $row['eventos'],
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
        Cache::forget('auditoria_datos.catalogo_types');
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
