<?php

use App\Support\Configuracion\EntornoEmpresaSupport;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Menú y permiso del POS Crown (solo instalación CROWN).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! EntornoEmpresaSupport::esCrown()) {
            return;
        }

        $ahora = now();
        $permisoSlug = 'usar-pos-gastronomia-crown';
        $permisoId = DB::table('permiso')->where('slug', $permisoSlug)->value('id');
        if (! $permisoId) {
            $permisoId = DB::table('permiso')->insertGetId([
                'nombre' => 'Usar POS gastronomía Crown',
                'slug' => $permisoSlug,
                'created_at' => $ahora,
                'updated_at' => $ahora,
            ]);
        }

        $menuPadreId = (int) (DB::table('menu')->where('id', 236)->value('id')
            ?? DB::table('menu')->where('nombre', 'Gastronomía')->where('url', '#')->value('id')
            ?? 0);
        if ($menuPadreId <= 0) {
            return;
        }

        $url = 'ventas/gastronomia-crown/pos';
        $menuId = DB::table('menu')->where('url', $url)->value('id');
        if (! $menuId) {
            $orden = (int) (DB::table('menu')->where('menu_id', $menuPadreId)->max('orden') ?? 0) + 1;
            $menuId = DB::table('menu')->insertGetId([
                'menu_id' => $menuPadreId,
                'nombre' => 'POS Crown',
                'url' => $url,
                'orden' => $orden,
                'icono' => 'fa-cutlery',
                'created_at' => $ahora,
                'updated_at' => $ahora,
            ]);
        }

        $rolIds = DB::table('rol')
            ->whereIn('nombre', ['administrador', 'Enc-sistemas'])
            ->pluck('id');

        foreach ($rolIds as $rolId) {
            $existePerm = DB::table('permiso_rol')
                ->where('rol_id', $rolId)
                ->where('permiso_id', $permisoId)
                ->exists();
            if (! $existePerm) {
                DB::table('permiso_rol')->insert([
                    'rol_id' => $rolId,
                    'permiso_id' => $permisoId,
                ]);
            }

            $existeMenu = DB::table('menu_rol')
                ->where('rol_id', $rolId)
                ->where('menu_id', $menuId)
                ->exists();
            if (! $existeMenu) {
                DB::table('menu_rol')->insert([
                    'rol_id' => $rolId,
                    'menu_id' => $menuId,
                ]);
            }

            // Visibilidad del padre Gastronomía
            $existePadre = DB::table('menu_rol')
                ->where('rol_id', $rolId)
                ->where('menu_id', $menuPadreId)
                ->exists();
            if (! $existePadre) {
                DB::table('menu_rol')->insert([
                    'rol_id' => $rolId,
                    'menu_id' => $menuPadreId,
                ]);
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

        $url = 'ventas/gastronomia-crown/pos';
        $menuId = DB::table('menu')->where('url', $url)->value('id');
        if ($menuId) {
            DB::table('menu_rol')->where('menu_id', $menuId)->delete();
            DB::table('menu')->where('id', $menuId)->delete();
        }

        $permisoId = DB::table('permiso')->where('slug', 'usar-pos-gastronomia-crown')->value('id');
        if ($permisoId) {
            DB::table('permiso_rol')->where('permiso_id', $permisoId)->delete();
            DB::table('permiso')->where('id', $permisoId)->delete();
        }
    }
};
