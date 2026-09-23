<?php

use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Menú Caja → Historial depósitos CHT (reimpresión de boletas agrupadas).
 * Reutiliza permiso listar-cheque; hereda roles del menú/permiso de cheques.
 */
return new class extends Migration
{
    private const MENU_URL = 'caja/cheque/historial-depositos';

    private const MENU_NOMBRE = 'Historial depósitos CHT';

    private const MENU_CHEQUE_URL = 'caja/cheque';

    public function up(): void
    {
        $chequeMenu = DB::table('menu')->where('url', self::MENU_CHEQUE_URL)->first();
        if (! $chequeMenu) {
            return;
        }

        $padreId = (int) $chequeMenu->menu_id;
        if ($padreId <= 0) {
            return;
        }

        $ordenCheque = (int) ($chequeMenu->orden ?? 2);
        $orden = $ordenCheque + 1;

        // Desplazar hermanos con orden >= nuevo para insertar justo debajo de Cheques.
        DB::table('menu')
            ->where('menu_id', $padreId)
            ->where('orden', '>=', $orden)
            ->where('url', '!=', self::MENU_URL)
            ->update([
                'orden' => DB::raw('orden + 1'),
                'updated_at' => now(),
            ]);

        $menuId = (int) (DB::table('menu')
            ->where('url', self::MENU_URL)
            ->where('menu_id', $padreId)
            ->value('id') ?? 0);

        if ($menuId === 0) {
            $menuId = (int) DB::table('menu')->insertGetId([
                'menu_id' => $padreId,
                'nombre' => self::MENU_NOMBRE,
                'url' => self::MENU_URL,
                'orden' => $orden,
                'icono' => 'fa-university',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } else {
            DB::table('menu')->where('id', $menuId)->update([
                'nombre' => self::MENU_NOMBRE,
                'orden' => $orden,
                'icono' => 'fa-university',
                'updated_at' => now(),
            ]);
        }

        $rolIds = DB::table('menu_rol')
            ->where('menu_id', (int) $chequeMenu->id)
            ->pluck('rol_id')
            ->map(static fn ($id) => (int) $id)
            ->filter(static fn ($id) => $id > 0)
            ->unique()
            ->values();

        $listarId = (int) (DB::table('permiso')->where('slug', 'listar-cheque')->value('id') ?? 0);
        if ($listarId > 0) {
            $rolIds = $rolIds->merge(
                DB::table('permiso_rol')->where('permiso_id', $listarId)->pluck('rol_id')
            )->map(static fn ($id) => (int) $id)->filter(static fn ($id) => $id > 0)->unique()->values();
        }

        $adminId = (int) (DB::table('rol')->where('nombre', 'administrador')->value('id') ?? 0);
        if ($adminId > 0) {
            $rolIds = $rolIds->push($adminId)->unique()->values();
        }

        foreach ($rolIds as $rolId) {
            if (! DB::table('menu_rol')->where('menu_id', $menuId)->where('rol_id', $rolId)->exists()) {
                DB::table('menu_rol')->insert([
                    'menu_id' => $menuId,
                    'rol_id' => $rolId,
                ]);
            }
        }

        SuitecrmPermiso::flushCachePermisos();
    }

    public function down(): void
    {
        $ids = DB::table('menu')->where('url', self::MENU_URL)->pluck('id');
        foreach ($ids as $id) {
            DB::table('menu_rol')->where('menu_id', $id)->delete();
            DB::table('menu')->where('id', $id)->delete();
        }

        SuitecrmPermiso::flushCachePermisos();
    }
};
