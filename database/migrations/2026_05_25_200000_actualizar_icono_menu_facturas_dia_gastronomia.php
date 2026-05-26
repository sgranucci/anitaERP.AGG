<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const MENU_URL = 'ventas/gastronomia/facturas-dia';

    private const MENU_URL_LEGACY = 'stock/gastronomia/facturas-dia';

    private const ICONO_NUEVO = 'fa-receipt';

    private const ICONO_ANTERIOR = 'fa-file-text-o';

    public function up(): void
    {
        DB::table('menu')
            ->whereIn('url', [self::MENU_URL, self::MENU_URL_LEGACY])
            ->update([
                'icono' => self::ICONO_NUEVO,
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        DB::table('menu')
            ->whereIn('url', [self::MENU_URL, self::MENU_URL_LEGACY])
            ->update([
                'icono' => self::ICONO_ANTERIOR,
                'updated_at' => now(),
            ]);
    }
};
