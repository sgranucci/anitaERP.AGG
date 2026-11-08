<?php

use App\Models\Admin\Menu;
use App\Support\Configuracion\EntornoEmpresaSupport;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Menú y permisos del ABM Fichas Crown (catálogo + conversión).
 */
return new class extends Migration
{
    private const URL = 'caja/tesoreria-crown/ficha';

    private const URL_TABLAS = '#tesoreria-crown-tablas';

    /** @var array<string, string> */
    private const PERMISOS = [
        'listar-tesoreria-crown-ficha' => 'Listar fichas de tesorería Crown',
        'crear-tesoreria-crown-ficha' => 'Crear fichas de tesorería Crown',
        'editar-tesoreria-crown-ficha' => 'Editar fichas de tesorería Crown',
        'actualizar-tesoreria-crown-ficha' => 'Actualizar fichas de tesorería Crown',
        'eliminar-tesoreria-crown-ficha' => 'Eliminar fichas de tesorería Crown',
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
            $menu->nombre = 'Fichas';
            $menu->url = self::URL;
            $menu->icono = 'fa-circle';
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
        if ($menuId) {
            DB::table('menu_rol')->where('menu_id', $menuId)->delete();
            DB::table('menu')->where('id', $menuId)->delete();
        }

        $permisoIds = DB::table('permiso')->whereIn('slug', array_keys(self::PERMISOS))->pluck('id');
        if ($permisoIds->isNotEmpty()) {
            DB::table('permiso_rol')->whereIn('permiso_id', $permisoIds)->delete();
            DB::table('permiso')->whereIn('id', $permisoIds)->delete();
        }

        if (class_exists(\App\Support\SuitecrmPermiso::class)) {
            \App\Support\SuitecrmPermiso::flushCachePermisos();
        }
    }
};
