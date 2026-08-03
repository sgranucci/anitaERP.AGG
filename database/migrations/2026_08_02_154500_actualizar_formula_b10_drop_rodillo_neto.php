<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('rendicion_maquina_formula')) {
            return;
        }

        DB::table('rendicion_maquina_formula')->where('codigo', 'B10')->update([
            'expresion' => 'inputs.drop_billete',
            'detalle' => 'Drop billetes rodillo neto (paridad Anita dr_bill_rod)',
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        if (! Schema::hasTable('rendicion_maquina_formula')) {
            return;
        }

        DB::table('rendicion_maquina_formula')->where('codigo', 'B10')->update([
            'expresion' => 'inputs.drop_billete - inputs.impuesto_drop',
            'detalle' => 'Drop billetes rodillo neto de impuesto drop',
            'updated_at' => now(),
        ]);
    }
};
