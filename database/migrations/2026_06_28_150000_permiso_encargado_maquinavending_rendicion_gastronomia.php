<?php

use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const MENU_URL = 'ventas/gastronomia/maquinas-vending/rendiciones';

    /** @var list<string> */
    private const ROLES_OBJETIVO = [
        'Enc-gastronomía',
    ];

    public function up(): void
    {
        $menuId = (int) (DB::table('menu')->where('url', self::MENU_URL)->value('id') ?? 0);
        if ($menuId === 0) {
            return;
        }

        $slug = 'actualizar-mv-rend-gastronomia-encargado';
        $permisoId = (int) (DB::table('permiso')->where('slug', $slug)->value('id') ?? 0);

        if ($permisoId === 0) {
            $permisoId = (int) DB::table('permiso')->insertGetId([
                'nombre' => 'Modificar rendición vending (jornada anterior)',
                'slug' => $slug,
                'menu_id' => $menuId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } else {
            DB::table('permiso')->where('id', $permisoId)->update([
                'menu_id' => $menuId,
                'nombre' => 'Modificar rendición vending (jornada anterior)',
                'updated_at' => now(),
            ]);
        }

        foreach ($this->resolverRolesObjetivo() as $rolId) {
            if (! DB::table('permiso_rol')->where('permiso_id', $permisoId)->where('rol_id', $rolId)->exists()) {
                DB::table('permiso_rol')->insert([
                    'permiso_id' => $permisoId,
                    'rol_id' => $rolId,
                ]);
            }
        }

        SuitecrmPermiso::flushCachePermisos();
    }

    /** @return list<int> */
    private function resolverRolesObjetivo(): array
    {
        $rolIds = [];
        foreach (self::ROLES_OBJETIVO as $nombre) {
            $id = (int) (DB::table('rol')->where('nombre', $nombre)->value('id') ?? 0);
            if ($id > 0) {
                $rolIds[] = $id;
            }
        }

        if ($rolIds !== []) {
            return array_values(array_unique($rolIds));
        }

        $encId = (int) (DB::table('rol')->where('nombre', 'like', 'Enc-gastronom%')->orderBy('id')->value('id') ?? 0);

        return $encId > 0 ? [$encId] : [];
    }

    public function down(): void
    {
        $slug = 'actualizar-mv-rend-gastronomia-encargado';
        $permisoId = (int) (DB::table('permiso')->where('slug', $slug)->value('id') ?? 0);
        if ($permisoId > 0) {
            DB::table('permiso_rol')->where('permiso_id', $permisoId)->delete();
            DB::table('permiso')->where('id', $permisoId)->delete();
        }

        SuitecrmPermiso::flushCachePermisos();
    }
};
