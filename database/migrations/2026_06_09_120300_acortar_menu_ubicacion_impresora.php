<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const MENU_URL = 'configuracion/ubicacion-impresora';

    private const NOMBRE_MENU = 'Ubic. impresoras';

    public function up(): void
    {
        DB::table('menu')
            ->where('url', self::MENU_URL)
            ->update([
                'nombre' => self::NOMBRE_MENU,
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        DB::table('menu')
            ->where('url', self::MENU_URL)
            ->update([
                'nombre' => 'Ubicaciones de impresora',
                'updated_at' => now(),
            ]);
    }
};
