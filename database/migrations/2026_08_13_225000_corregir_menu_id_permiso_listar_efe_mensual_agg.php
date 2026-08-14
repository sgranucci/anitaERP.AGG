<?php

use App\Support\Configuracion\EntornoEmpresaSupport;
use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * AGG: corrige menu_id de listar-efe-mensual → menú Caja «Posición financiera».
 * (La 224000 lo había dejado apuntando al EFE de Contable.)
 */
return new class extends Migration
{
    private const PERMISO_SLUG = 'listar-efe-mensual';

    private const PERMISO_NOMBRE = 'Listar posición financiera';

    private const MENU_CAJA_NOMBRE = 'Posición financiera';

    private const MENU_PADRE_CAJA = 'Módulo de Caja';

    public function up(): void
    {
        if (! EntornoEmpresaSupport::esAgg()) {
            return;
        }

        $cajaPadreId = (int) (DB::table('menu')
            ->where('nombre', self::MENU_PADRE_CAJA)
            ->where('url', '#')
            ->orderBy('id')
            ->value('id') ?? 0);

        $menuCajaId = $cajaPadreId > 0
            ? (int) (DB::table('menu')
                ->where('menu_id', $cajaPadreId)
                ->where('nombre', self::MENU_CAJA_NOMBRE)
                ->value('id') ?? 0)
            : 0;

        if ($menuCajaId <= 0) {
            return;
        }

        $updated = DB::table('permiso')
            ->where('slug', self::PERMISO_SLUG)
            ->update([
                'nombre' => self::PERMISO_NOMBRE,
                'menu_id' => $menuCajaId,
                'updated_at' => now(),
            ]);

        if ($updated > 0) {
            SuitecrmPermiso::flushCachePermisos();
        }
    }

    public function down(): void
    {
        if (! EntornoEmpresaSupport::esAgg()) {
            return;
        }

        $menuEfeId = (int) (DB::table('menu')
            ->where('url', 'contable/efe-mensual')
            ->where('nombre', 'Estado de flujo (EFE)')
            ->value('id') ?? 0);

        if ($menuEfeId <= 0) {
            return;
        }

        DB::table('permiso')
            ->where('slug', self::PERMISO_SLUG)
            ->update([
                'nombre' => 'Listar EFE mensual',
                'menu_id' => $menuEfeId,
                'updated_at' => now(),
            ]);

        SuitecrmPermiso::flushCachePermisos();
    }
};
