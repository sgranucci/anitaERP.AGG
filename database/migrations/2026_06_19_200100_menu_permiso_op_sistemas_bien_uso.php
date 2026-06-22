<?php

use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const MENU_URL = 'contable/bien-uso';

    /** @var list<string> */
    private const SLUGS_PERMISO = [
        'listar-bien-uso',
        'crear-bien-uso',
        'editar-bien-uso',
        'actualizar-bien-uso',
        'borrar-bien-uso',
    ];

    /** @var list<string> */
    private const ROLES_SISTEMAS = [
        'Enc-sistemas',
        'Op-sistemas',
    ];

    public function up(): void
    {
        $menuId = (int) (DB::table('menu')->where('url', self::MENU_URL)->value('id') ?? 0);
        if ($menuId === 0) {
            return;
        }

        $padreId = (int) (DB::table('menu')->where('id', $menuId)->value('menu_id') ?? 0);
        $rolIds = DB::table('rol')
            ->whereIn('nombre', self::ROLES_SISTEMAS)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        foreach (array_unique(array_filter(array_merge([$menuId, $padreId], []))) as $mid) {
            if ($mid <= 0) {
                continue;
            }
            foreach ($rolIds as $rolId) {
                if (! DB::table('menu_rol')->where('menu_id', $mid)->where('rol_id', $rolId)->exists()) {
                    DB::table('menu_rol')->insert(['menu_id' => $mid, 'rol_id' => $rolId]);
                }
            }
        }

        $permisoIds = DB::table('permiso')
            ->whereIn('slug', self::SLUGS_PERMISO)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        foreach ($permisoIds as $permisoId) {
            foreach ($rolIds as $rolId) {
                if (! DB::table('permiso_rol')->where('permiso_id', $permisoId)->where('rol_id', $rolId)->exists()) {
                    DB::table('permiso_rol')->insert(['permiso_id' => $permisoId, 'rol_id' => $rolId]);
                }
            }
        }

        SuitecrmPermiso::flushCachePermisos();
    }

    public function down(): void
    {
        $menuId = (int) (DB::table('menu')->where('url', self::MENU_URL)->value('id') ?? 0);
        $rolIds = DB::table('rol')->whereIn('nombre', self::ROLES_SISTEMAS)->pluck('id')->all();
        $permisoIds = DB::table('permiso')->whereIn('slug', self::SLUGS_PERMISO)->pluck('id')->all();

        foreach ($permisoIds as $permisoId) {
            DB::table('permiso_rol')
                ->where('permiso_id', $permisoId)
                ->whereIn('rol_id', $rolIds)
                ->delete();
        }

        if ($menuId > 0) {
            DB::table('menu_rol')->where('menu_id', $menuId)->whereIn('rol_id', $rolIds)->delete();
        }

        SuitecrmPermiso::flushCachePermisos();
    }
};
