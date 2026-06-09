<?php

use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const MENU_URL = 'caja/estacionamiento/lista-precio';

    private const MENU_PADRE_NOMBRE = 'Estacionamiento';

    private const ROLES_TESORERIA = [
        'Op-tesoreria',
        'op-Tesoreria Operativa',
        'Enc-tesorería',
        'Enc-tesoreria',
        'enc-Tesoreria Operativa',
        'Ger-Tesoreria',
    ];

    private const SLUGS = [
        'listar-estacionamiento-lista-precio',
        'crear-estacionamiento-lista-precio',
        'editar-estacionamiento-lista-precio',
        'actualizar-estacionamiento-lista-precio',
        'borrar-estacionamiento-lista-precio',
    ];

    public function up(): void
    {
        $menuId = (int) (DB::table('menu')->where('url', self::MENU_URL)->value('id') ?? 0);
        $padreId = (int) (DB::table('menu')
            ->where('nombre', self::MENU_PADRE_NOMBRE)
            ->where('url', '#')
            ->value('id') ?? 0);

        if ($menuId === 0) {
            return;
        }

        $rolIds = DB::table('rol')->whereIn('nombre', self::ROLES_TESORERIA)->pluck('id')->all();

        foreach ($rolIds as $rolId) {
            $rid = (int) $rolId;
            if ($padreId > 0 && ! DB::table('menu_rol')->where('menu_id', $padreId)->where('rol_id', $rid)->exists()) {
                DB::table('menu_rol')->insert(['menu_id' => $padreId, 'rol_id' => $rid]);
            }
            if (! DB::table('menu_rol')->where('menu_id', $menuId)->where('rol_id', $rid)->exists()) {
                DB::table('menu_rol')->insert(['menu_id' => $menuId, 'rol_id' => $rid]);
            }

            foreach (self::SLUGS as $slug) {
                $permisoId = (int) (DB::table('permiso')->where('slug', $slug)->value('id') ?? 0);
                if ($permisoId > 0 && ! DB::table('permiso_rol')->where('permiso_id', $permisoId)->where('rol_id', $rid)->exists()) {
                    DB::table('permiso_rol')->insert(['permiso_id' => $permisoId, 'rol_id' => $rid]);
                }
            }
        }

        SuitecrmPermiso::flushCachePermisos();
    }

    public function down(): void
    {
        // Sin rollback de roles: la migración de menú elimina permisos.
    }
};
