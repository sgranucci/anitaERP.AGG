<?php

use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const MENU_URLS = [
        'stock/recepcion-proveedor',
        'configuracion/recepcion-proveedor',
        'stock/configuracion-recepcion-proveedor',
    ];

    public function up(): void
    {
        foreach (self::MENU_URLS as $url) {
            $menuId = (int) (DB::table('menu')->where('url', $url)->value('id') ?? 0);
            if ($menuId <= 0) {
                continue;
            }

            $rolIds = DB::table('menu_rol')
                ->where('menu_id', $menuId)
                ->pluck('rol_id')
                ->unique()
                ->all();

            $permisoIds = DB::table('permiso')
                ->where('menu_id', $menuId)
                ->pluck('id')
                ->all();

            foreach ($permisoIds as $permisoId) {
                $permisoId = (int) $permisoId;
                foreach ($rolIds as $rolId) {
                    $rolId = (int) $rolId;
                    if ($rolId <= 0) {
                        continue;
                    }
                    if (! DB::table('permiso_rol')->where('permiso_id', $permisoId)->where('rol_id', $rolId)->exists()) {
                        DB::table('permiso_rol')->insert([
                            'permiso_id' => $permisoId,
                            'rol_id' => $rolId,
                        ]);
                    }
                }
            }
        }

        SuitecrmPermiso::flushCachePermisos();
    }

    public function down(): void
    {
        $slugs = [
            'listar-recepcion-proveedor', 'crear-recepcion-proveedor', 'editar-recepcion-proveedor',
            'actualizar-recepcion-proveedor', 'confirmar-recepcion-proveedor', 'devolver-recepcion-proveedor',
            'anular-recepcion-proveedor', 'ocr-recepcion-proveedor',
            'editar-configuracion-recepcion-proveedor', 'actualizar-configuracion-recepcion-proveedor',
        ];

        $permisoIds = DB::table('permiso')->whereIn('slug', $slugs)->pluck('id')->all();
        if ($permisoIds !== []) {
            DB::table('permiso_rol')->whereIn('permiso_id', $permisoIds)->delete();
        }

        SuitecrmPermiso::flushCachePermisos();
    }
};
