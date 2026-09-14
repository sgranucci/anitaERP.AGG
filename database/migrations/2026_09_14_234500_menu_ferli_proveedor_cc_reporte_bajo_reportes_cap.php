<?php

use App\Support\Configuracion\EntornoEmpresaSupport;
use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Ferli: quita submenu CAP → Cuenta corriente y deja Deuda/ficha bajo Reportes
 * (junto a Proyección de pagos / Pagos sábana).
 */
return new class extends Migration
{
    private const SUBMENU_URL = '#cuenta-corriente-cap';

    private const HOJA_URL = 'compras/proveedor-cuentacorriente-reporte';

    public function up(): void
    {
        if (! EntornoEmpresaSupport::esFerli()) {
            return;
        }

        $capId = $this->idModuloCuentasAPagar();
        $reportesId = $this->idReportesCap($capId);
        $hojaId = (int) (DB::table('menu')->where('url', self::HOJA_URL)->value('id') ?? 0);
        if ($capId <= 0 || $reportesId <= 0 || $hojaId <= 0) {
            return;
        }

        $orden = (int) (DB::table('menu')->where('menu_id', $reportesId)->max('orden') ?? 0) + 1;

        DB::table('menu')->where('id', $hojaId)->update([
            'menu_id' => $reportesId,
            'orden' => $orden,
            'updated_at' => now(),
        ]);

        // Roles que ven la hoja también deben ver Reportes
        $rolIds = DB::table('menu_rol')->where('menu_id', $hojaId)->pluck('rol_id');
        foreach ($rolIds as $rolId) {
            $rid = (int) $rolId;
            if ($rid <= 0) {
                continue;
            }
            if (! DB::table('menu_rol')->where('menu_id', $reportesId)->where('rol_id', $rid)->exists()) {
                DB::table('menu_rol')->insert(['menu_id' => $reportesId, 'rol_id' => $rid]);
            }
            if (! DB::table('menu_rol')->where('menu_id', $capId)->where('rol_id', $rid)->exists()) {
                DB::table('menu_rol')->insert(['menu_id' => $capId, 'rol_id' => $rid]);
            }
        }

        $submenuId = (int) (DB::table('menu')
            ->where('menu_id', $capId)
            ->where('url', self::SUBMENU_URL)
            ->value('id') ?? 0);

        if ($submenuId > 0 && ! DB::table('menu')->where('menu_id', $submenuId)->exists()) {
            DB::table('menu_rol')->where('menu_id', $submenuId)->delete();
            DB::table('menu')->where('id', $submenuId)->delete();
        }

        SuitecrmPermiso::flushCachePermisos();
    }

    public function down(): void
    {
        if (! EntornoEmpresaSupport::esFerli()) {
            return;
        }

        $capId = $this->idModuloCuentasAPagar();
        $hojaId = (int) (DB::table('menu')->where('url', self::HOJA_URL)->value('id') ?? 0);
        if ($capId <= 0 || $hojaId <= 0) {
            return;
        }

        $submenuId = (int) (DB::table('menu')
            ->where('menu_id', $capId)
            ->where('url', self::SUBMENU_URL)
            ->value('id') ?? 0);

        if ($submenuId <= 0) {
            $ordenReportes = (int) (DB::table('menu')
                ->where('menu_id', $capId)
                ->where('nombre', 'Reportes')
                ->value('orden') ?? 0);
            $orden = $ordenReportes > 0
                ? $ordenReportes + 1
                : ((int) (DB::table('menu')->where('menu_id', $capId)->max('orden') ?? 0) + 1);

            DB::table('menu')
                ->where('menu_id', $capId)
                ->where('orden', '>=', $orden)
                ->increment('orden');

            $submenuId = (int) DB::table('menu')->insertGetId([
                'menu_id' => $capId,
                'nombre' => 'Cuenta corriente',
                'url' => self::SUBMENU_URL,
                'orden' => $orden,
                'icono' => 'fa-folder-open',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        DB::table('menu')->where('id', $hojaId)->update([
            'menu_id' => $submenuId,
            'orden' => 1,
            'updated_at' => now(),
        ]);

        $rolIds = DB::table('menu_rol')->where('menu_id', $hojaId)->pluck('rol_id');
        foreach ($rolIds as $rolId) {
            $rid = (int) $rolId;
            if ($rid <= 0) {
                continue;
            }
            if (! DB::table('menu_rol')->where('menu_id', $submenuId)->where('rol_id', $rid)->exists()) {
                DB::table('menu_rol')->insert(['menu_id' => $submenuId, 'rol_id' => $rid]);
            }
        }

        SuitecrmPermiso::flushCachePermisos();
    }

    private function idModuloCuentasAPagar(): int
    {
        $id = (int) (DB::table('menu')
            ->where('menu_id', 0)
            ->where('url', '#')
            ->where('nombre', 'Cuentas a pagar')
            ->orderBy('id')
            ->value('id') ?? 0);

        if ($id > 0) {
            return $id;
        }

        return (int) (DB::table('menu')->where('url', 'compras/proyeccion-pagos')->value('menu_id') ?? 0);
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
            ->value('id') ?? 0);
    }
};
