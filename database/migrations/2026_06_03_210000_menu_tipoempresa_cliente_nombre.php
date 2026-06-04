<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('menu')
            ->where('url', 'ventas/tipoempresa-cliente')
            ->update([
                'nombre' => 'Tipos de empresa',
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        DB::table('menu')
            ->where('url', 'ventas/tipoempresa-cliente')
            ->update([
                'nombre' => 'Tipos de empresa (clientes)',
                'updated_at' => now(),
            ]);
    }
};
