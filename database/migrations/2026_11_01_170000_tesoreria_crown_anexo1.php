<?php

use App\Models\Admin\Menu;
use App\Support\Configuracion\EntornoEmpresaSupport;
use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Anexo I de tesorería Crown. Lee lo que Anita ya grabó y, si el día
 * no tiene detalle, calcula efectivo, bóveda y poder público. No escribe en Anita.
 */
return new class extends Migration
{
    private const SLUG = 'listar-tesoreria-crown-anexo';

    public function up(): void
    {
        if (! EntornoEmpresaSupport::esCrown()) {
            return;
        }

        $modulo = Menu::query()->where('url', '#tesoreria-crown')->first();
        if (! $modulo) {
            return;
        }
        $ahora = now();
        $permisoId = DB::table('permiso')->where('slug', self::SLUG)->value('id');
        if (! $permisoId) {
            $permisoId = DB::table('permiso')->insertGetId([
                'nombre' => 'Listar anexo I de tesorería Crown',
                'slug' => self::SLUG,
                'ayuda' => 'Ver el anexo I del día, con mes y anual. Si Anita ya lo cerró, muestra ese detalle. Si no, muestra efectivo, bóveda y poder público. No graba en Anita.',
                'created_at' => $ahora,
                'updated_at' => $ahora,
            ]);
        }
        $menu = Menu::query()->where('url', 'caja/tesoreria-crown/anexo')->first();
        if (! $menu) {
            $menu = new Menu;
            $menu->nombre = 'Anexo I';
            $menu->url = 'caja/tesoreria-crown/anexo';
            $menu->icono = 'fa-list-alt';
            $menu->menu_id = $modulo->id;
            $menu->orden = 8;
            $menu->save();
        }
        DB::table('permiso')->where('id', $permisoId)->update([
            'menu_id' => $menu->id,
            'ayuda' => 'Ver el anexo I del día, con mes y anual. Si Anita ya lo cerró, muestra ese detalle. Si no, muestra efectivo, bóveda y poder público. No graba en Anita.',
            'updated_at' => $ahora,
        ]);
        $roles = DB::table('menu_rol')->where('menu_id', $modulo->id)->pluck('rol_id');
        foreach ($roles as $rolId) {
            if (! DB::table('menu_rol')->where('menu_id', $menu->id)->where('rol_id', $rolId)->exists()) {
                DB::table('menu_rol')->insert(['menu_id' => $menu->id, 'rol_id' => $rolId]);
            }
            if (! DB::table('permiso_rol')->where('rol_id', $rolId)->where('permiso_id', $permisoId)->exists()) {
                DB::table('permiso_rol')->insert(['rol_id' => $rolId, 'permiso_id' => $permisoId]);
            }
        }
        SuitecrmPermiso::flushCachePermisos();
    }

    public function down(): void
    {
        if (! EntornoEmpresaSupport::esCrown()) {
            return;
        }

        $menu = Menu::query()->where('url', 'caja/tesoreria-crown/anexo')->first();
        if ($menu) {
            DB::table('menu_rol')->where('menu_id', $menu->id)->delete();
            $menu->delete();
        }
        $permisoIds = DB::table('permiso')->where('slug', self::SLUG)->pluck('id');
        if ($permisoIds->isNotEmpty()) {
            DB::table('permiso_rol')->whereIn('permiso_id', $permisoIds)->delete();
            DB::table('permiso')->whereIn('id', $permisoIds)->delete();
        }
        SuitecrmPermiso::flushCachePermisos();
    }
};
