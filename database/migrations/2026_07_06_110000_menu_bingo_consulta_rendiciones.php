<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('menu')
            ->where('url', 'caja/bingo/cierres-turno')
            ->update([
                'nombre' => 'Consulta rendiciones',
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        DB::table('menu')
            ->where('url', 'caja/bingo/cierres-turno')
            ->update([
                'nombre' => 'Cierres de turno',
                'updated_at' => now(),
            ]);
    }
};
