<?php

use App\Support\Configuracion\EntornoEmpresaSupport;
use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Crown: bajo Cuentas a pagar, submenú Transferencias con
 * Solicitudes de retiro y Archivo pagos (ASCII) de Interbanking.
 * No toca el ítem homónimo que vive en API Interbanking.
 */
return new class extends Migration
{
    private const SUBMENU_URL = '#transferencias-cap';

    private const SUBMENU_NOMBRE = 'Transferencias';

    private const URL_SOLICITUD = 'compras/solicitud-retiro';

    private const URL_ASCII = 'caja/interbanking/archivo-pago';

    public function up(): void
    {
        if (! EntornoEmpresaSupport::esCrown()) {
            return;
        }

        $capId = $this->idModuloCuentasAPagar();
        if ($capId <= 0) {
            return;
        }

        $ascii = $this->hojaBajo($capId, self::URL_ASCII);
        $ordenSubmenu = $ascii !== null
            ? (int) $ascii->orden
            : (int) (DB::table('menu')->where('menu_id', $capId)->max('orden') ?? 0) + 1;

        $submenuId = $this->asegurarSubmenu($capId, $ordenSubmenu);
        $this->colocarHoja(self::URL_SOLICITUD, $capId, $submenuId, 1);
        $this->colocarHojaAscii($capId, $submenuId, 2);
        $this->copiarRolesAlSubmenu($submenuId, $capId);

        SuitecrmPermiso::flushCachePermisos();
    }

    public function down(): void
    {
        if (! EntornoEmpresaSupport::esCrown()) {
            return;
        }

        $capId = $this->idModuloCuentasAPagar();
        $submenuId = $this->idSubmenu($capId);
        if ($capId <= 0 || $submenuId <= 0) {
            return;
        }

        $this->devolverACap($submenuId, $capId, self::URL_SOLICITUD, 13);
        $this->devolverACap($submenuId, $capId, self::URL_ASCII, 10);

        $quedan = (int) DB::table('menu')->where('menu_id', $submenuId)->count();
        if ($quedan === 0) {
            DB::table('menu_rol')->where('menu_id', $submenuId)->delete();
            DB::table('menu')->where('id', $submenuId)->delete();
        }

        SuitecrmPermiso::flushCachePermisos();
    }

    private function asegurarSubmenu(int $capId, int $orden): int
    {
        $id = $this->idSubmenu($capId);
        if ($id > 0) {
            DB::table('menu')->where('id', $id)->update([
                'nombre' => self::SUBMENU_NOMBRE,
                'orden' => $orden,
                'icono' => 'fa-exchange',
                'updated_at' => now(),
            ]);

            return $id;
        }

        return (int) DB::table('menu')->insertGetId([
            'nombre' => self::SUBMENU_NOMBRE,
            'url' => self::SUBMENU_URL,
            'menu_id' => $capId,
            'orden' => $orden,
            'icono' => 'fa-exchange',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function colocarHoja(string $url, int $capId, int $submenuId, int $orden): void
    {
        $hoja = DB::table('menu')
            ->where('url', $url)
            ->whereIn('menu_id', [$capId, $submenuId])
            ->orderBy('id')
            ->first();
        if ($hoja === null) {
            return;
        }

        DB::table('menu')->where('id', (int) $hoja->id)->update([
            'menu_id' => $submenuId,
            'orden' => $orden,
            'updated_at' => now(),
        ]);
    }

    /**
     * Solo la copia de Cuentas a pagar. La de API Interbanking queda donde está.
     */
    private function colocarHojaAscii(int $capId, int $submenuId, int $orden): void
    {
        $hoja = $this->hojaBajo($capId, self::URL_ASCII)
            ?? $this->hojaBajo($submenuId, self::URL_ASCII);
        if ($hoja === null) {
            return;
        }

        DB::table('menu')->where('id', (int) $hoja->id)->update([
            'menu_id' => $submenuId,
            'orden' => $orden,
            'updated_at' => now(),
        ]);
    }

    private function copiarRolesAlSubmenu(int $submenuId, int $capId): void
    {
        $hojaIds = DB::table('menu')->where('menu_id', $submenuId)->pluck('id');
        $rolIds = DB::table('menu_rol')->whereIn('menu_id', $hojaIds)->pluck('rol_id')->unique();
        foreach ($rolIds as $rolId) {
            $rid = (int) $rolId;
            if ($rid <= 0) {
                continue;
            }
            foreach ([$submenuId, $capId] as $menuId) {
                if (! DB::table('menu_rol')->where('menu_id', $menuId)->where('rol_id', $rid)->exists()) {
                    DB::table('menu_rol')->insert(['menu_id' => $menuId, 'rol_id' => $rid]);
                }
            }
        }
    }

    private function devolverACap(int $submenuId, int $capId, string $url, int $orden): void
    {
        DB::table('menu')
            ->where('menu_id', $submenuId)
            ->where('url', $url)
            ->update([
                'menu_id' => $capId,
                'orden' => $orden,
                'updated_at' => now(),
            ]);
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

    private function idSubmenu(int $capId): int
    {
        if ($capId <= 0) {
            return 0;
        }

        return (int) (DB::table('menu')
            ->where('menu_id', $capId)
            ->where('url', self::SUBMENU_URL)
            ->value('id') ?? 0);
    }

    private function hojaBajo(int $padreId, string $url): ?object
    {
        if ($padreId <= 0) {
            return null;
        }

        return DB::table('menu')->where('menu_id', $padreId)->where('url', $url)->first();
    }
};
