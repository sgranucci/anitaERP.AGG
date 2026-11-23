<?php

use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Permisos para editar entregas de indumentaria (cabecera y líneas) bajo el menú sueldos/entrega-prenda.
 *
 * Roles: administrador, Capital Humano (mismo patrón que 2026_07_19_122000) y cualquier rol
 * que ya tenga listar-entrega-prenda o anular-entrega-prenda.
 */
return new class extends Migration
{
    private const MENU_URL = 'sueldos/entrega-prenda';

    /** @var list<array{nombre:string, slug:string}> */
    private const PERMISOS = [
        ['nombre' => 'Editar cabecera de entrega de prendas', 'slug' => 'editar-entrega-prenda'],
        ['nombre' => 'Editar líneas de entrega de prendas', 'slug' => 'editar-lineas-entrega-prenda'],
    ];

    public function up(): void
    {
        $menuId = (int) (DB::table('menu')->where('url', self::MENU_URL)->value('id') ?? 0);
        if ($menuId === 0) {
            // Sin el menú de entregas no hay dónde colgar los permisos (corre antes 2026_07_19_122000).
            return;
        }

        $permisoIds = [];
        foreach (self::PERMISOS as $perm) {
            $permisoIds[] = $this->upsertPermiso($perm['nombre'], $perm['slug'], $menuId);
        }

        $rolIds = $this->resolverRolIds();
        foreach ($rolIds as $rolId) {
            foreach ($permisoIds as $permisoId) {
                if (! DB::table('permiso_rol')->where('permiso_id', $permisoId)->where('rol_id', $rolId)->exists()) {
                    DB::table('permiso_rol')->insert(['permiso_id' => $permisoId, 'rol_id' => $rolId]);
                }
            }
        }

        SuitecrmPermiso::flushCachePermisos();
    }

    public function down(): void
    {
        $slugs = array_map(static fn (array $p): string => $p['slug'], self::PERMISOS);
        $permisoIds = DB::table('permiso')->whereIn('slug', $slugs)->pluck('id');
        if ($permisoIds->isNotEmpty()) {
            DB::table('permiso_rol')->whereIn('permiso_id', $permisoIds)->delete();
            DB::table('permiso')->whereIn('id', $permisoIds)->delete();
        }

        SuitecrmPermiso::flushCachePermisos();
    }

    private function upsertPermiso(string $nombre, string $slug, int $menuId): int
    {
        $id = (int) (DB::table('permiso')->where('slug', $slug)->value('id') ?? 0);
        $payload = ['nombre' => $nombre, 'slug' => $slug, 'menu_id' => $menuId, 'updated_at' => now()];
        if ($id > 0) {
            DB::table('permiso')->where('id', $id)->update($payload);

            return $id;
        }

        return (int) DB::table('permiso')->insertGetId(array_merge($payload, ['created_at' => now()]));
    }

    /**
     * @return list<int>
     */
    private function resolverRolIds(): array
    {
        $porNombre = DB::table('rol')
            ->where('nombre', 'administrador')
            ->orWhere('nombre', 'like', '%apital%umano%')
            ->pluck('id');

        $permisosBase = DB::table('permiso')
            ->whereIn('slug', ['listar-entrega-prenda', 'anular-entrega-prenda'])
            ->pluck('id');
        $porPermiso = $permisosBase->isEmpty()
            ? collect()
            : DB::table('permiso_rol')->whereIn('permiso_id', $permisosBase)->pluck('rol_id');

        return $porNombre->merge($porPermiso)
            ->map(static fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }
};
