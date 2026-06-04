<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const MENU_URL = 'ventas/tipoempresa';

    private const PARENT_MENU_URL = '#';

    private const PARENT_MENU_NAME = 'Tablas de ventas';

    /** @var array<int, string> */
    private const PERMISO_SLUGS = [
        'crear-tipo-de-empresa',
        'listar-tipo-de-empresa',
        'editar-tipo-de-empresa',
        'actualizar-tipo-de-empresa',
        'borrar-tipo-de-empresa',
    ];

    /** @var array<int, string> */
    private const ROLES_IMPUESTOS = [
        'Enc-impuestos',
        'Op-impuestos',
    ];

    public function up(): void
    {
        $parentMenuId = (int) (DB::table('menu')
            ->where('nombre', self::PARENT_MENU_NAME)
            ->where('url', self::PARENT_MENU_URL)
            ->value('id') ?? 53);

        $orden = (int) (DB::table('menu')->where('menu_id', $parentMenuId)->max('orden') ?? 0) + 1;

        $menuId = (int) (DB::table('menu')->where('url', self::MENU_URL)->value('id') ?? 0);
        if ($menuId === 0) {
            $menuId = (int) DB::table('menu')->insertGetId([
                'menu_id' => $parentMenuId,
                'nombre' => 'Tipos de empresa',
                'url' => self::MENU_URL,
                'orden' => $orden,
                'icono' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } else {
            DB::table('menu')->where('id', $menuId)->update([
                'menu_id' => $parentMenuId,
                'nombre' => 'Tipos de empresa',
                'orden' => $orden,
                'updated_at' => now(),
            ]);
        }

        $permisoIds = [];
        foreach (self::PERMISO_SLUGS as $slug) {
            $permisoId = (int) (DB::table('permiso')->where('slug', $slug)->value('id') ?? 0);
            if ($permisoId > 0) {
                DB::table('permiso')->where('id', $permisoId)->update([
                    'menu_id' => $menuId,
                    'updated_at' => now(),
                ]);
                $permisoIds[] = $permisoId;
            }
        }

        $rolIds = DB::table('rol')->whereIn('nombre', self::ROLES_IMPUESTOS)->pluck('id')->all();
        foreach ($rolIds as $rolId) {
            $rid = (int) $rolId;
            foreach ($permisoIds as $permisoId) {
                if (! DB::table('permiso_rol')->where('permiso_id', $permisoId)->where('rol_id', $rid)->exists()) {
                    DB::table('permiso_rol')->insert(['permiso_id' => $permisoId, 'rol_id' => $rid]);
                }
            }
            if (! DB::table('menu_rol')->where('menu_id', $menuId)->where('rol_id', $rid)->exists()) {
                DB::table('menu_rol')->insert(['menu_id' => $menuId, 'rol_id' => $rid]);
            }
        }
    }

    public function down(): void
    {
        $menuId = (int) (DB::table('menu')->where('url', self::MENU_URL)->value('id') ?? 0);
        if ($menuId === 0) {
            return;
        }

        $permisoIds = DB::table('permiso')->whereIn('slug', self::PERMISO_SLUGS)->pluck('id')->all();
        $rolIds = DB::table('rol')->whereIn('nombre', self::ROLES_IMPUESTOS)->pluck('id')->all();

        foreach ($permisoIds as $permisoId) {
            DB::table('permiso_rol')
                ->where('permiso_id', $permisoId)
                ->whereIn('rol_id', $rolIds)
                ->delete();
            DB::table('permiso')->where('id', $permisoId)->update(['menu_id' => null, 'updated_at' => now()]);
        }

        DB::table('menu_rol')->where('menu_id', $menuId)->whereIn('rol_id', $rolIds)->delete();
        DB::table('menu')->where('id', $menuId)->delete();
    }
};
