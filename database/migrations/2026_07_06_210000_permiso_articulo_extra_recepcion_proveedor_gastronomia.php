<?php

use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Gastronomía (CC 85): agregar artículos fuera de la OC en recepción de proveedor.
 */
return new class extends Migration
{
    private const PERMISO = [
        'menu_url' => 'stock/recepcion-proveedor',
        'nombre' => 'Agregar artículo extra en recepción de proveedor',
        'slug' => 'agregar-articulo-extra-recepcion-proveedor',
    ];

    /** Roles operativos de gastronomía (centro de costo 85). */
    private const ROLES = [
        'Enc-gastronomía',
        'Sup-Gastronomia',
        'Op-Gastronomia',
        'Ger-Gastronomia',
    ];

    public function up(): void
    {
        $menuId = (int) (DB::table('menu')->where('url', self::PERMISO['menu_url'])->value('id') ?? 0);
        if ($menuId <= 0) {
            return;
        }

        $permisoId = $this->upsertPermiso(self::PERMISO['nombre'], self::PERMISO['slug'], $menuId);

        foreach ($this->resolverRolIds() as $rolId) {
            if (! DB::table('permiso_rol')->where('permiso_id', $permisoId)->where('rol_id', $rolId)->exists()) {
                DB::table('permiso_rol')->insert([
                    'permiso_id' => $permisoId,
                    'rol_id' => $rolId,
                ]);
            }
        }

        SuitecrmPermiso::flushCachePermisos();
    }

    public function down(): void
    {
        $permisoId = (int) (DB::table('permiso')->where('slug', self::PERMISO['slug'])->value('id') ?? 0);
        if ($permisoId > 0) {
            DB::table('permiso_rol')->where('permiso_id', $permisoId)->delete();
            DB::table('permiso')->where('id', $permisoId)->delete();
        }

        SuitecrmPermiso::flushCachePermisos();
    }

    /** @return list<int> */
    private function resolverRolIds(): array
    {
        $ids = [];
        foreach (self::ROLES as $nombre) {
            $id = (int) (DB::table('rol')->where('nombre', $nombre)->value('id') ?? 0);
            if ($id <= 0 && $nombre === 'Enc-gastronomía') {
                $id = (int) (DB::table('rol')->where('nombre', 'like', 'Enc-gastronom%')->orderBy('id')->value('id') ?? 0);
            }
            if ($id > 0) {
                $ids[] = $id;
            }
        }

        return array_values(array_unique($ids));
    }

    private function upsertPermiso(string $nombre, string $slug, int $menuId): int
    {
        $id = (int) (DB::table('permiso')->where('slug', $slug)->value('id') ?? 0);

        if ($id === 0) {
            return (int) DB::table('permiso')->insertGetId([
                'nombre' => $nombre,
                'slug' => $slug,
                'menu_id' => $menuId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        DB::table('permiso')->where('id', $id)->update([
            'nombre' => $nombre,
            'menu_id' => $menuId,
            'updated_at' => now(),
        ]);

        return $id;
    }
};
