<?php

use App\Support\Configuracion\EntornoEmpresaSupport;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Crown: el ítem «Config. terminales gastronomía» (mismo lugar que en AGG,
 * Configuración → Configuración por módulo → Ventas) abre la pantalla Crown.
 */
return new class extends Migration
{
    private const URL_AGG = 'ventas/configuracion-puntoventa-gastronomia';

    private const URL_CROWN = 'ventas/gastronomia-crown/configuracion-pc';

    /** @var list<string> */
    private const URLS_WAITRY = [
        'ventas/totem-waitry-gastronomia',
        'caja/waitry-cierre-jornada',
    ];

    public function up(): void
    {
        if (! EntornoEmpresaSupport::esCrown()) {
            return;
        }

        $menu = DB::table('menu')->where('url', self::URL_AGG)->first();
        if ($menu === null) {
            $menu = DB::table('menu')->where('url', self::URL_CROWN)->first();
        }
        if ($menu !== null) {
            DB::table('menu')->where('id', $menu->id)->update([
                'url' => self::URL_CROWN,
                'updated_at' => now(),
            ]);
        }

        $idsWaitry = DB::table('menu')->whereIn('url', self::URLS_WAITRY)->pluck('id');
        if ($idsWaitry->isNotEmpty()) {
            DB::table('menu_rol')->whereIn('menu_id', $idsWaitry)->delete();
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

        DB::table('menu')->where('url', self::URL_CROWN)->update([
            'url' => self::URL_AGG,
            'updated_at' => now(),
        ]);

        $rolIds = DB::table('rol')->whereIn('nombre', ['administrador', 'Enc-sistemas'])->pluck('id');
        $idsWaitry = DB::table('menu')->whereIn('url', self::URLS_WAITRY)->pluck('id');
        foreach ($rolIds as $rolId) {
            foreach ($idsWaitry as $menuId) {
                $existe = DB::table('menu_rol')->where('rol_id', $rolId)->where('menu_id', $menuId)->exists();
                if (! $existe) {
                    DB::table('menu_rol')->insert([
                        'rol_id' => $rolId,
                        'menu_id' => $menuId,
                    ]);
                }
            }
        }

        if (class_exists(\App\Support\SuitecrmPermiso::class)) {
            \App\Support\SuitecrmPermiso::flushCachePermisos();
        }
    }
};
