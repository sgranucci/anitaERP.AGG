<?php

use App\Models\Admin\Menu;
use App\Support\Configuracion\EntornoEmpresaSupport;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Tesorería Crown queda como módulo de la barra, no como último ítem de Módulo de Caja.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! EntornoEmpresaSupport::esCrown()) {
            return;
        }

        $menu = Menu::query()->where('url', '#tesoreria-crown')->first();
        if (! $menu || (int) $menu->menu_id === 0) {
            return;
        }

        $orden = (int) DB::table('menu')->where('menu_id', 0)->max('orden');
        $menu->menu_id = 0;
        $menu->nombre = 'Tesorería Crown';
        $menu->orden = $orden + 1;
        $menu->icono = 'fa-university';
        $menu->save();
    }

    public function down(): void
    {
        if (! EntornoEmpresaSupport::esCrown()) {
            return;
        }

        $cajaId = (int) (DB::table('menu')->where('id', 104)->value('id') ?: 0);
        $menu = Menu::query()->where('url', '#tesoreria-crown')->first();
        if (! $menu || $cajaId <= 0) {
            return;
        }

        $menu->menu_id = $cajaId;
        $menu->nombre = 'Tesorería';
        $menu->orden = 25;
        $menu->save();
    }
};
