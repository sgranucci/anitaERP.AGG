<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const MENU_URL = 'stock/transferencia-mercaderia';

    private const NOMBRE = 'Transferencia';

    public function up(): void
    {
        DB::table('menu')
            ->where('url', self::MENU_URL)
            ->update([
                'nombre' => self::NOMBRE,
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        DB::table('menu')
            ->where('url', self::MENU_URL)
            ->update([
                'nombre' => 'Transferencia mercadería',
                'updated_at' => now(),
            ]);
    }
};
