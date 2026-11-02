<?php

use App\Models\Admin\Menu;
use App\Support\Configuracion\EntornoEmpresaSupport;
use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Poder del público de fichas, calculado como l-stockfichm.c.
 * El día valorizado es la línea que el anexo I llama "Fichas poder publico".
 */
return new class extends Migration
{
    private const SLUG = 'listar-tesoreria-crown-poder-publico';

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
                'nombre' => 'Listar poder del público de tesorería Crown',
                'slug' => self::SLUG,
                'ayuda' => 'Ver, por ficha, la reserva, la conversión y el poder del público del día. El importe del día es el que entra al anexo. No graba en Anita.',
                'created_at' => $ahora,
                'updated_at' => $ahora,
            ]);
        }
        $menu = Menu::query()->where('url', 'caja/tesoreria-crown/poder')->first();
        if (! $menu) {
            $menu = new Menu;
            $menu->nombre = 'Poder público';
            $menu->url = 'caja/tesoreria-crown/poder';
            $menu->icono = 'fa-users';
            $menu->menu_id = $modulo->id;
            $menu->orden = 7;
            $menu->save();
        }
        DB::table('permiso')->where('id', $permisoId)->update([
            'menu_id' => $menu->id,
            'ayuda' => 'Ver, por ficha, la reserva, la conversión y el poder del público del día. El importe del día es el que entra al anexo. No graba en Anita.',
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

        $menu = Menu::query()->where('url', 'caja/tesoreria-crown/poder')->first();
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
