<?php

use App\Support\Configuracion\EntornoEmpresaSupport;
use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * CROWN: Proyección de pagos pasa a Cuentas a pagar (módulo raíz) → Reportes,
 * junto a Pagos (sábana) y Deuda/ficha. Antes quedaba solo bajo
 * Compras → Cuentas a pagar → Reportes y no se veía en el menú operativo de CAP.
 */
return new class extends Migration
{
    private const HOJA_URL = 'compras/proyeccion-pagos';

    public function up(): void
    {
        if (! EntornoEmpresaSupport::esCrown()) {
            return;
        }

        $capId = $this->idModuloCuentasAPagar();
        $reportesId = $this->idReportesCap($capId);
        $hojaId = (int) (DB::table('menu')->where('url', self::HOJA_URL)->value('id') ?? 0);
        if ($capId <= 0 || $reportesId <= 0 || $hojaId <= 0) {
            return;
        }

        // Ya está bajo Reportes del módulo CAP raíz.
        if ((int) DB::table('menu')->where('id', $hojaId)->value('menu_id') === $reportesId) {
            $this->sincronizarRolesPadres($hojaId, $reportesId, $capId);
            SuitecrmPermiso::flushCachePermisos();

            return;
        }

        $orden = (int) (DB::table('menu')->where('menu_id', $reportesId)->max('orden') ?? 0) + 1;

        DB::table('menu')->where('id', $hojaId)->update([
            'menu_id' => $reportesId,
            'orden' => $orden,
            'updated_at' => now(),
        ]);

        $this->sincronizarRolesPadres($hojaId, $reportesId, $capId);
        SuitecrmPermiso::flushCachePermisos();
    }

    public function down(): void
    {
        if (! EntornoEmpresaSupport::esCrown()) {
            return;
        }

        $hojaId = (int) (DB::table('menu')->where('url', self::HOJA_URL)->value('id') ?? 0);
        $reportesComprasId = $this->idReportesCapBajoCompras();
        if ($hojaId <= 0 || $reportesComprasId <= 0) {
            return;
        }

        $orden = (int) (DB::table('menu')->where('menu_id', $reportesComprasId)->max('orden') ?? 0) + 1;

        DB::table('menu')->where('id', $hojaId)->update([
            'menu_id' => $reportesComprasId,
            'orden' => $orden,
            'updated_at' => now(),
        ]);

        SuitecrmPermiso::flushCachePermisos();
    }

    private function idModuloCuentasAPagar(): int
    {
        return (int) (DB::table('menu')
            ->where('menu_id', 0)
            ->where('url', '#')
            ->where('nombre', 'Cuentas a pagar')
            ->orderBy('id')
            ->value('id') ?? 0);
    }

    private function idReportesCap(int $capId): int
    {
        if ($capId <= 0) {
            return 0;
        }

        return (int) (DB::table('menu')
            ->where('menu_id', $capId)
            ->where('nombre', 'Reportes')
            ->where(function ($q) {
                $q->where('url', '#')->orWhere('url', 'like', '#%');
            })
            ->orderBy('id')
            ->value('id') ?? 0);
    }

    /**
     * Reportes bajo Compras → Cuentas a pagar (ubicación previa de la proyección).
     */
    private function idReportesCapBajoCompras(): int
    {
        $comprasId = (int) (DB::table('menu')
            ->where('menu_id', 0)
            ->where('nombre', 'like', '%Compras%')
            ->orderBy('id')
            ->value('id') ?? 0);

        if ($comprasId <= 0) {
            return 0;
        }

        $capComprasId = (int) (DB::table('menu')
            ->where('menu_id', $comprasId)
            ->where('nombre', 'Cuentas a pagar')
            ->where('url', '#')
            ->orderBy('id')
            ->value('id') ?? 0);

        return $this->idReportesCap($capComprasId);
    }

    private function sincronizarRolesPadres(int $hojaId, int $reportesId, int $capId): void
    {
        $rolIds = DB::table('menu_rol')->where('menu_id', $hojaId)->pluck('rol_id');
        foreach ($rolIds as $rolId) {
            $rid = (int) $rolId;
            if ($rid <= 0) {
                continue;
            }
            foreach ([$reportesId, $capId] as $menuId) {
                if (! DB::table('menu_rol')->where('menu_id', $menuId)->where('rol_id', $rid)->exists()) {
                    DB::table('menu_rol')->insert(['menu_id' => $menuId, 'rol_id' => $rid]);
                }
            }
        }
    }
};
