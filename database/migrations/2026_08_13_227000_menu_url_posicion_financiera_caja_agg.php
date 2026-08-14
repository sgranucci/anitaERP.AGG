<?php

use App\Support\Configuracion\EntornoEmpresaSupport;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * AGG: menú Caja «Posición financiera» apunta a caja/posicion-financiera
 * (pantalla propia), no al EFE completo de Contable.
 */
return new class extends Migration
{
    private const MENU_NOMBRE = 'Posición financiera';

    private const MENU_PADRE = 'Módulo de Caja';

    private const URL_NUEVA = 'caja/posicion-financiera';

    private const URL_VIEJA = 'contable/efe-mensual';

    public function up(): void
    {
        if (! EntornoEmpresaSupport::esAgg()) {
            return;
        }

        $menuId = $this->resolverMenuCajaPosicionId();
        if ($menuId <= 0) {
            return;
        }

        DB::table('menu')->where('id', $menuId)->update([
            'url' => self::URL_NUEVA,
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        if (! EntornoEmpresaSupport::esAgg()) {
            return;
        }

        $menuId = $this->resolverMenuCajaPosicionId();
        if ($menuId <= 0) {
            return;
        }

        DB::table('menu')->where('id', $menuId)->update([
            'url' => self::URL_VIEJA,
            'updated_at' => now(),
        ]);
    }

    private function resolverMenuCajaPosicionId(): int
    {
        $padreId = (int) (DB::table('menu')->where('nombre', self::MENU_PADRE)->value('id') ?? 0);
        if ($padreId <= 0) {
            return 0;
        }

        return (int) (DB::table('menu')
            ->where('menu_id', $padreId)
            ->where('nombre', self::MENU_NOMBRE)
            ->value('id') ?? 0);
    }
};
