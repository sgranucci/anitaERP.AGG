<?php

use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * La migración de Clientes VIP reutilizó menu id=8 (Administración) al buscar url='#'.
 * Restaura Administración en la raíz y crea un menú Canjes propio bajo Stock.
 */
return new class extends Migration
{
    private const MENU_ADMIN_ID = 8;

    private const MENU_STOCK_ID = 10;

    private const MENU_CLIENTE_VIP_URL = 'ventas/gastronomia/canjes/cliente-vip';

    /** @var list<int> */
    private const MENU_ADMIN_HIJOS = [1, 2, 3, 4, 5, 9];

    public function up(): void
    {
        $admin = DB::table('menu')->where('id', self::MENU_ADMIN_ID)->first();
        if ($admin === null || $admin->nombre !== 'Canjes') {
            return;
        }

        $ordenCanjes = (int) ($admin->orden ?? 26);

        $canjesMenuId = (int) DB::table('menu')->insertGetId([
            'menu_id' => self::MENU_STOCK_ID,
            'nombre' => 'Canjes',
            'url' => '#',
            'orden' => $ordenCanjes,
            'icono' => 'fa-gift',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $hijosCanjes = DB::table('menu')
            ->where('menu_id', self::MENU_ADMIN_ID)
            ->whereNotIn('id', self::MENU_ADMIN_HIJOS)
            ->pluck('id');

        foreach ($hijosCanjes as $hijoId) {
            DB::table('menu')->where('id', $hijoId)->update([
                'menu_id' => $canjesMenuId,
                'updated_at' => now(),
            ]);
        }

        $adminRolId = (int) (DB::table('rol')->where('nombre', 'administrador')->value('id') ?? 1);
        $rolesCanjes = array_values(array_filter(
            DB::table('menu_rol')->where('menu_id', self::MENU_ADMIN_ID)->pluck('rol_id')->unique()->all(),
            fn (int $rolId) => $rolId !== $adminRolId
        ));

        foreach ($rolesCanjes as $rolId) {
            if (! DB::table('menu_rol')->where('menu_id', $canjesMenuId)->where('rol_id', $rolId)->exists()) {
                DB::table('menu_rol')->insert([
                    'menu_id' => $canjesMenuId,
                    'rol_id' => $rolId,
                ]);
            }
            DB::table('menu_rol')
                ->where('menu_id', self::MENU_ADMIN_ID)
                ->where('rol_id', $rolId)
                ->delete();
        }

        $ordenAdmin = (int) (DB::table('menu')->where('menu_id', 0)->max('orden') ?? 0) + 1;

        DB::table('menu')->where('id', self::MENU_ADMIN_ID)->update([
            'menu_id' => 0,
            'nombre' => 'Administración',
            'url' => '#',
            'orden' => $ordenAdmin,
            'icono' => 'fa-user-shield',
            'updated_at' => now(),
        ]);

        SuitecrmPermiso::flushCachePermisos();
    }

    public function down(): void
    {
        // Corrección de datos en producción; no revertir automáticamente.
    }
};
