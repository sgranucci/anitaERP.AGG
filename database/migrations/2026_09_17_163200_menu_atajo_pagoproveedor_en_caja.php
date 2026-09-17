<?php

use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Atajo operativo: Pago a proveedores también bajo Módulo de Caja.
 * Misma URL canónica (compras/pagoproveedor); no duplica rutas ni controllers.
 */
return new class extends Migration
{
    private const MENU_URL = 'compras/pagoproveedor';

    private const MENU_NOMBRE = 'Pago a proveedores';

    private const CAJA_ROOT_NOMBRE = 'Módulo de Caja';

    public function up(): void
    {
        $cajaRootId = (int) (DB::table('menu')
            ->where('nombre', self::CAJA_ROOT_NOMBRE)
            ->where('menu_id', 0)
            ->value('id') ?? 0);
        if ($cajaRootId <= 0) {
            return;
        }

        $canonicoId = (int) (DB::table('menu')
            ->where('url', self::MENU_URL)
            ->where('menu_id', '<>', $cajaRootId)
            ->orderBy('id')
            ->value('id') ?? 0);

        $atajoId = (int) (DB::table('menu')
            ->where('url', self::MENU_URL)
            ->where('menu_id', $cajaRootId)
            ->value('id') ?? 0);

        $ordenRef = (int) (DB::table('menu')
            ->where('menu_id', $cajaRootId)
            ->where('url', 'caja/cobranza')
            ->value('orden') ?? 0);
        $orden = $ordenRef > 0
            ? $ordenRef + 1
            : ((int) (DB::table('menu')->where('menu_id', $cajaRootId)->max('orden') ?? 0) + 1);

        if ($atajoId <= 0) {
            $atajoId = (int) DB::table('menu')->insertGetId([
                'nombre' => self::MENU_NOMBRE,
                'url' => self::MENU_URL,
                'menu_id' => $cajaRootId,
                'orden' => $orden,
                'icono' => 'fa-money',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } else {
            DB::table('menu')->where('id', $atajoId)->update([
                'nombre' => self::MENU_NOMBRE,
                'orden' => $orden,
                'icono' => 'fa-money',
                'updated_at' => now(),
            ]);
        }

        $rolIds = [];
        if ($canonicoId > 0) {
            $rolIds = DB::table('menu_rol')
                ->where('menu_id', $canonicoId)
                ->pluck('rol_id')
                ->map(fn ($id) => (int) $id)
                ->all();
        }
        if ($rolIds === []) {
            $adminId = (int) (DB::table('rol')->where('nombre', 'administrador')->value('id') ?? 0);
            if ($adminId > 0) {
                $rolIds = [$adminId];
            }
        }

        DB::table('menu_rol')->where('menu_id', $atajoId)->delete();
        foreach ($rolIds as $rolId) {
            DB::table('menu_rol')->insert([
                'menu_id' => $atajoId,
                'rol_id' => $rolId,
            ]);
            // Padre Caja visible para quienes ven el atajo
            if (! DB::table('menu_rol')->where('menu_id', $cajaRootId)->where('rol_id', $rolId)->exists()) {
                DB::table('menu_rol')->insert([
                    'menu_id' => $cajaRootId,
                    'rol_id' => $rolId,
                ]);
            }
        }

        SuitecrmPermiso::flushCachePermisos();
    }

    public function down(): void
    {
        $cajaRootId = (int) (DB::table('menu')
            ->where('nombre', self::CAJA_ROOT_NOMBRE)
            ->where('menu_id', 0)
            ->value('id') ?? 0);
        if ($cajaRootId <= 0) {
            return;
        }

        $atajos = DB::table('menu')
            ->where('menu_id', $cajaRootId)
            ->where('url', self::MENU_URL)
            ->pluck('id');

        foreach ($atajos as $atajoId) {
            DB::table('menu_rol')->where('menu_id', $atajoId)->delete();
            DB::table('menu')->where('id', $atajoId)->delete();
        }

        SuitecrmPermiso::flushCachePermisos();
    }
};
