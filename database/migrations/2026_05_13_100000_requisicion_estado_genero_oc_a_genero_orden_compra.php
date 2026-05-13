<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Vuelve el nombre visible del estado O a "GENERO ORDEN COMPRA" (antes "GENERO OC").
     */
    public function up(): void
    {
        DB::table('requisicion')->where('estado', 'GENERO OC')->update(['estado' => 'GENERO ORDEN COMPRA']);
        DB::table('requisicion_estado')->where('estado', 'GENERO OC')->update(['estado' => 'GENERO ORDEN COMPRA']);
    }

    public function down(): void
    {
        DB::table('requisicion')->where('estado', 'GENERO ORDEN COMPRA')->update(['estado' => 'GENERO OC']);
        DB::table('requisicion_estado')->where('estado', 'GENERO ORDEN COMPRA')->update(['estado' => 'GENERO OC']);
    }
};
