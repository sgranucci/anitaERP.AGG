<?php

use App\Models\Admin\Menu;
use App\Support\Configuracion\EntornoEmpresaSupport;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Agrupa los maestros de Tesorería Crown bajo el submenú Tablas.
 */
return new class extends Migration
{
    private const URL_MODULO = '#tesoreria-crown';

    private const URL_TABLAS = '#tesoreria-crown-tablas';

    /** @var array<string, int> */
    private const HOJAS = [
        'caja/tesoreria-crown/valor' => 1,
        'caja/tesoreria-crown/valor/tipos' => 2,
        'caja/tesoreria-crown/deposito' => 3,
        'caja/tesoreria-crown/deposito/tipos' => 4,
        'caja/tesoreria-crown/cotizacion' => 5,
    ];

    public function up(): void
    {
        if (! EntornoEmpresaSupport::esCrown()) {
            return;
        }

        $modulo = Menu::query()->where('url', self::URL_MODULO)->first();
        if (! $modulo) {
            return;
        }

        $tablas = Menu::query()->where('url', self::URL_TABLAS)->first();
        if (! $tablas) {
            $tablas = new Menu;
            $tablas->nombre = 'Tablas';
            $tablas->url = self::URL_TABLAS;
            $tablas->icono = 'fa-table';
            $tablas->menu_id = $modulo->id;
            $tablas->orden = 1;
            $tablas->save();
        }

        $roles = DB::table('menu_rol')->where('menu_id', $modulo->id)->pluck('rol_id');
        foreach ($roles as $rolId) {
            $existe = DB::table('menu_rol')
                ->where('menu_id', $tablas->id)
                ->where('rol_id', $rolId)
                ->exists();
            if (! $existe) {
                DB::table('menu_rol')->insert([
                    'menu_id' => $tablas->id,
                    'rol_id' => $rolId,
                ]);
            }
        }

        foreach (self::HOJAS as $url => $orden) {
            $hoja = Menu::query()->where('url', $url)->first();
            if (! $hoja) {
                continue;
            }
            $hoja->menu_id = $tablas->id;
            $hoja->orden = $orden;
            $hoja->save();
        }
    }

    public function down(): void
    {
        if (! EntornoEmpresaSupport::esCrown()) {
            return;
        }

        $modulo = Menu::query()->where('url', self::URL_MODULO)->first();
        $tablas = Menu::query()->where('url', self::URL_TABLAS)->first();
        if (! $modulo || ! $tablas) {
            return;
        }

        foreach (self::HOJAS as $url => $orden) {
            $hoja = Menu::query()->where('url', $url)->first();
            if (! $hoja) {
                continue;
            }
            $hoja->menu_id = $modulo->id;
            $hoja->orden = $orden;
            $hoja->save();
        }

        DB::table('menu_rol')->where('menu_id', $tablas->id)->delete();
        $tablas->delete();
    }
};
