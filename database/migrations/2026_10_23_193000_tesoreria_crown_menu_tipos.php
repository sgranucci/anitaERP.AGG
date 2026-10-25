<?php

use App\Models\Admin\Menu;
use App\Support\Configuracion\EntornoEmpresaSupport;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Tipos de valor y de depósito en su propia entrada de menú.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! EntornoEmpresaSupport::esCrown()) {
            return;
        }

        $padre = Menu::query()->where('url', '#tesoreria-crown')->first();
        if (! $padre) {
            return;
        }

        $hojas = [
            ['nombre' => 'Tipos de valor', 'url' => 'caja/tesoreria-crown/valor/tipos', 'icono' => 'fa-tags', 'orden' => 2],
            ['nombre' => 'Tipos de depósito', 'url' => 'caja/tesoreria-crown/deposito/tipos', 'icono' => 'fa-archive', 'orden' => 4],
        ];
        $roles = DB::table('menu_rol')->where('menu_id', $padre->id)->pluck('rol_id');
        foreach ($hojas as $hoja) {
            $menu = Menu::query()->where('url', $hoja['url'])->first();
            if (! $menu) {
                $menu = new Menu;
                $menu->nombre = $hoja['nombre'];
                $menu->url = $hoja['url'];
                $menu->icono = $hoja['icono'];
                $menu->menu_id = $padre->id;
                $menu->orden = $hoja['orden'];
                $menu->save();
            }
            foreach ($roles as $rolId) {
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
        }
    }

    public function down(): void
    {
        if (! EntornoEmpresaSupport::esCrown()) {
            return;
        }

        $ids = DB::table('menu')->whereIn('url', [
            'caja/tesoreria-crown/valor/tipos',
            'caja/tesoreria-crown/deposito/tipos',
        ])->pluck('id');
        if ($ids->isEmpty()) {
            return;
        }
        DB::table('menu_rol')->whereIn('menu_id', $ids)->delete();
        Menu::query()->whereIn('id', $ids)->get()->each(function (Menu $menu) {
            $menu->delete();
        });
    }
};
