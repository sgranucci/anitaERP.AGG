<?php

use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * El CRUD caja/mediopago se retiró en 2026_05_15_110000; un restore de menú
 * (dump/seed) puede reinsertar la hoja y deja 404. Idempotente.
 */
return new class extends Migration
{
    private const MENU_URL = 'caja/mediopago';

    public function up(): void
    {
        $menuId = (int) (DB::table('menu')->where('url', self::MENU_URL)->value('id') ?? 0);
        if ($menuId > 0) {
            DB::table('menu_rol')->where('menu_id', $menuId)->delete();
            DB::table('menu')->where('id', $menuId)->delete();
        }

        SuitecrmPermiso::flushCachePermisos();
    }

    public function down(): void
    {
        // No restaurar: la pantalla ya no existe.
    }
};
