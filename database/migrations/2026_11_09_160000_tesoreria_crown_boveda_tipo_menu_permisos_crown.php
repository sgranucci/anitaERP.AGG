<?php

use App\Models\Admin\Menu;
use App\Support\Configuracion\EntornoEmpresaSupport;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * ABM de tipos de movimiento de bóveda (catálogo padre de subtipos).
 */
return new class extends Migration
{
    private const URL = 'caja/tesoreria-crown/boveda/tipos';

    private const URL_TABLAS = '#tesoreria-crown-tablas';

    /** @var array<string, string> */
    private const PERMISOS = [
        'listar-tesoreria-crown-boveda-tipo' => 'Listar tipos de movimiento Crown',
        'crear-tesoreria-crown-boveda-tipo' => 'Crear tipos de movimiento Crown',
        'editar-tesoreria-crown-boveda-tipo' => 'Editar tipos de movimiento Crown',
        'actualizar-tesoreria-crown-boveda-tipo' => 'Actualizar tipos de movimiento Crown',
        'eliminar-tesoreria-crown-boveda-tipo' => 'Eliminar tipos de movimiento Crown',
    ];

    /** @var list<string> */
    private const ROLES = [
        'administrador',
        'Enc-sistemas',
        'Enc-tesorería',
        'enc-Tesoreria Operativa',
        'Ger-Tesoreria',
        'Op-tesoreria',
        'op-Tesoreria Operativa',
        'opflash-tesoreria',
        'Sup-tesoreria',
    ];

    public function up(): void
    {
        if (! EntornoEmpresaSupport::esCrown()) {
            return;
        }

        $tablas = Menu::query()->where('url', self::URL_TABLAS)->first();
        if (! $tablas) {
            return;
        }

        $menu = Menu::query()->where('url', self::URL)->first();
        if (! $menu) {
            $menu = new Menu;
            $menu->nombre = 'Tipos de movimiento';
            $menu->url = self::URL;
            $menu->icono = 'fa-exchange';
            $menu->menu_id = $tablas->id;
            $menu->orden = 6;
            $menu->save();
        } else {
            $menu->menu_id = $tablas->id;
            $menu->orden = 6;
            $menu->save();
        }

        $rolIds = DB::table('rol')->whereIn('nombre', self::ROLES)->pluck('id');
        foreach ($rolIds as $rolId) {
            $existe = DB::table('menu_rol')
                ->where('menu_id', $menu->id)
                ->where('rol_id', $rolId)
                ->exists();
            if (! $existe) {
                DB::table('menu_rol')->insert([
                    'menu_id' => $menu->id,
                    'rol_id' => $rolId,
                ]);
            }
        }

        foreach (self::PERMISOS as $slug => $nombre) {
            $permisoId = DB::table('permiso')->where('slug', $slug)->value('id');
            if (! $permisoId) {
                $permisoId = DB::table('permiso')->insertGetId([
                    'nombre' => $nombre,
                    'slug' => $slug,
                    'menu_id' => $menu->id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            } else {
                DB::table('permiso')->where('id', $permisoId)->update([
                    'menu_id' => $menu->id,
                    'updated_at' => now(),
                ]);
            }
            foreach ($rolIds as $rolId) {
                $existe = DB::table('permiso_rol')
                    ->where('permiso_id', $permisoId)
                    ->where('rol_id', $rolId)
                    ->exists();
                if (! $existe) {
                    DB::table('permiso_rol')->insert([
                        'permiso_id' => $permisoId,
                        'rol_id' => $rolId,
                    ]);
                }
            }
        }

        if (class_exists(\App\Support\SuitecrmPermiso::class)) {
            \App\Support\SuitecrmPermiso::flushCachePermisos();
        }
    }

    public function down(): void
    {
        if (! EntornoEmpresaSupport::esCrown()) {
            return;
        }

        $menuId = DB::table('menu')->where('url', self::URL)->value('id');
        $permisoIds = DB::table('permiso')
            ->whereIn('slug', array_keys(self::PERMISOS))
            ->when($menuId, fn ($q) => $q->orWhere('menu_id', $menuId))
            ->pluck('id');

        if ($permisoIds->isNotEmpty()) {
            DB::table('permiso_rol')->whereIn('permiso_id', $permisoIds)->delete();
            DB::table('permiso')->whereIn('id', $permisoIds)->delete();
        }

        if ($menuId) {
            DB::table('menu_rol')->where('menu_id', $menuId)->delete();
            DB::table('menu')->where('id', $menuId)->delete();
        }

        if (class_exists(\App\Support\SuitecrmPermiso::class)) {
            \App\Support\SuitecrmPermiso::flushCachePermisos();
        }
    }
};
