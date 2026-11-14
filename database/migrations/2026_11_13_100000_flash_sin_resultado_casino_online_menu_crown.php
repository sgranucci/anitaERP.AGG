<?php

use App\Support\Configuracion\EntornoEmpresaSupport;
use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Crown: Resultado online y Casino online diario siguen bajo Flash,
 * pero dejan de verse en el menú para roles de tesorería operativa.
 * Contaduría / cuentas a pagar / admin / sistemas siguen viéndolos.
 */
return new class extends Migration
{
    /** @var list<string> */
    private const MENU_URLS = [
        'caja/tesoreria-crown/resultado-online',
        'caja/tesoreria-crown/casino-online',
    ];

    /** Roles de tesorería: se les quita solo la hoja del menú Flash. */
    private const ROLES_TESORERIA = [
        'Enc-tesorería',
        'Enc-tesoreria',
        'Ger-Tesoreria',
        'Sup-tesoreria',
        'Sup-tesorería',
        'opflash-tesoreria',
    ];

    public function up(): void
    {
        if (! EntornoEmpresaSupport::esCrown()) {
            return;
        }

        $rolIds = DB::table('rol')
            ->whereIn('nombre', self::ROLES_TESORERIA)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
        if ($rolIds === []) {
            return;
        }

        $menuIds = DB::table('menu')
            ->whereIn('url', self::MENU_URLS)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
        if ($menuIds === []) {
            return;
        }

        DB::table('menu_rol')
            ->whereIn('menu_id', $menuIds)
            ->whereIn('rol_id', $rolIds)
            ->delete();

        SuitecrmPermiso::flushCachePermisos();
    }

    public function down(): void
    {
        if (! EntornoEmpresaSupport::esCrown()) {
            return;
        }

        $rolIds = DB::table('rol')
            ->whereIn('nombre', self::ROLES_TESORERIA)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
        $menuIds = DB::table('menu')
            ->whereIn('url', self::MENU_URLS)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
        if ($rolIds === [] || $menuIds === []) {
            return;
        }

        foreach ($menuIds as $menuId) {
            foreach ($rolIds as $rolId) {
                if (DB::table('menu_rol')->where('menu_id', $menuId)->where('rol_id', $rolId)->exists()) {
                    continue;
                }
                DB::table('menu_rol')->insert([
                    'menu_id' => $menuId,
                    'rol_id' => $rolId,
                ]);
            }
        }

        SuitecrmPermiso::flushCachePermisos();
    }
};
