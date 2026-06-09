<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const MENU_URL = 'caja/estacionamiento/categoria-automovil';

    public function up(): void
    {
        DB::table('menu')->where('url', self::MENU_URL)->update([
            'nombre' => 'Categorías',
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('menu')->where('url', self::MENU_URL)->update([
            'nombre' => 'Categorías de automóviles',
            'updated_at' => now(),
        ]);
    }
};
