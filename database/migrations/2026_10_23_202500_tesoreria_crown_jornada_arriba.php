<?php

use App\Models\Admin\Menu;
use App\Support\Configuracion\EntornoEmpresaSupport;
use Illuminate\Database\Migrations\Migration;

/**
 * Jornada queda primera bajo Tesorería Crown, arriba de Tablas.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! EntornoEmpresaSupport::esCrown()) {
            return;
        }

        $this->ordenar('caja/tesoreria-crown/jornada', 1);
        $this->ordenar('#tesoreria-crown-tablas', 2);
    }

    public function down(): void
    {
        if (! EntornoEmpresaSupport::esCrown()) {
            return;
        }

        $this->ordenar('#tesoreria-crown-tablas', 1);
        $this->ordenar('caja/tesoreria-crown/jornada', 2);
    }

    private function ordenar(string $url, int $orden): void
    {
        $menu = Menu::query()->where('url', $url)->first();
        if (! $menu || (int) $menu->orden === $orden) {
            return;
        }
        $menu->orden = $orden;
        $menu->save();
    }
};
