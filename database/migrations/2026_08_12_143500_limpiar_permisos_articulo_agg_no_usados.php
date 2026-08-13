<?php

use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Limpia permisos vinculados a menú Artículos (stock/articulo) que AGG no usa:
 * - Ferli / product (diseño, técnica, contaduría, combinaciones)
 * - filtrar-articulos (sin can() en código)
 * - fórmula plural legacy (editar/actualizar-formula-articulos); el CRUD real usa
 *   formula-articulo (singular, menú fórmulas)
 *
 * Antes de borrar fórmula plural, transfiere permiso_rol al singular equivalente.
 */
return new class extends Migration
{
    /** @var list<string> */
    private const SLUGS_ELIMINAR = [
        'filtrar-articulos',
        'actualizar-articulos-disenio',
        'actualizar-articulos-tecnica',
        'editar-articulos-combinaciones',
        'editar-articulos-contaduria',
        'editar-articulos-tecnica',
        'editar-formula-articulos',
        'actualizar-formula-articulos',
    ];

    /** @var array<string, string> slug plural => slug singular */
    private const FORMULA_PLURAL_A_SINGULAR = [
        'editar-formula-articulos' => 'editar-formula-articulo',
        'actualizar-formula-articulos' => 'actualizar-formula-articulo',
    ];

    /** @var array<string, array{nombre: string, slug: string}> */
    private const SNAPSHOT_DOWN = [
        'filtrar-articulos' => ['nombre' => 'Filtrar articulos', 'slug' => 'filtrar-articulos'],
        'actualizar-articulos-disenio' => ['nombre' => 'Actualizar articulos', 'slug' => 'actualizar-articulos-disenio'],
        'actualizar-articulos-tecnica' => ['nombre' => 'Actualizar articulos tecnica', 'slug' => 'actualizar-articulos-tecnica'],
        'editar-articulos-combinaciones' => ['nombre' => 'Editar articulos combinaciones', 'slug' => 'editar-articulos-combinaciones'],
        'editar-articulos-contaduria' => ['nombre' => 'Editar articulos contaduria', 'slug' => 'editar-articulos-contaduria'],
        'editar-articulos-tecnica' => ['nombre' => 'Editar articulos tecnica', 'slug' => 'editar-articulos-tecnica'],
        'editar-formula-articulos' => ['nombre' => 'Editar formula articulos', 'slug' => 'editar-formula-articulos'],
        'actualizar-formula-articulos' => ['nombre' => 'Actualizar formula articulos', 'slug' => 'actualizar-formula-articulos'],
    ];

    public function up(): void
    {
        foreach (self::FORMULA_PLURAL_A_SINGULAR as $plural => $singular) {
            $this->transferirRoles($plural, $singular);
        }

        $ids = DB::table('permiso')->whereIn('slug', self::SLUGS_ELIMINAR)->pluck('id');
        if ($ids->isNotEmpty()) {
            DB::table('permiso_rol')->whereIn('permiso_id', $ids)->delete();
            DB::table('permiso')->whereIn('id', $ids)->delete();
        }

        SuitecrmPermiso::flushCachePermisos();
    }

    public function down(): void
    {
        $menuId = (int) (DB::table('menu')->where('url', 'stock/articulo')->value('id') ?? 0);
        if ($menuId <= 0) {
            return;
        }

        $now = now();
        foreach (self::SNAPSHOT_DOWN as $slug => $meta) {
            if (DB::table('permiso')->where('slug', $slug)->exists()) {
                continue;
            }
            DB::table('permiso')->insert([
                'nombre' => $meta['nombre'],
                'slug' => $meta['slug'],
                'menu_id' => $menuId,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        SuitecrmPermiso::flushCachePermisos();
    }

    private function transferirRoles(string $slugOrigen, string $slugDestino): void
    {
        $origenId = (int) (DB::table('permiso')->where('slug', $slugOrigen)->value('id') ?? 0);
        $destinoId = (int) (DB::table('permiso')->where('slug', $slugDestino)->value('id') ?? 0);
        if ($origenId <= 0 || $destinoId <= 0) {
            return;
        }

        $rolIds = DB::table('permiso_rol')->where('permiso_id', $origenId)->pluck('rol_id');
        foreach ($rolIds as $rolId) {
            $rolId = (int) $rolId;
            $existe = DB::table('permiso_rol')
                ->where('permiso_id', $destinoId)
                ->where('rol_id', $rolId)
                ->exists();
            if (! $existe) {
                DB::table('permiso_rol')->insert([
                    'permiso_id' => $destinoId,
                    'rol_id' => $rolId,
                ]);
            }
        }
    }
};
