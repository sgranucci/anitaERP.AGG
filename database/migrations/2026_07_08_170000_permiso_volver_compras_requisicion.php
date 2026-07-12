<?php

use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Devolver requisición de EN ARBOL APROBACION a EN COMPRAS (anula autorizaciones pendientes).
 */
return new class extends Migration
{
    private const MENU_URL = 'compras/requisicion';

    private const PERMISO = [
        'nombre' => 'Volver requisición a compras (anular envío al árbol)',
        'slug' => 'volver-compras-requisicion',
    ];

    public function up(): void
    {
        $menuId = (int) (DB::table('menu')->where('url', self::MENU_URL)->value('id') ?? 0);
        if ($menuId <= 0) {
            return;
        }

        $refPermisoId = (int) (DB::table('permiso')->where('slug', 'editar-requisicion')->value('id') ?? 0);
        $rolIds = [];
        if ($refPermisoId > 0) {
            $rolIds = DB::table('permiso_rol')
                ->where('permiso_id', $refPermisoId)
                ->pluck('rol_id')
                ->unique()
                ->all();
        }

        $permisoId = $this->upsertPermiso(self::PERMISO['nombre'], self::PERMISO['slug'], $menuId);
        foreach ($rolIds as $rolId) {
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
