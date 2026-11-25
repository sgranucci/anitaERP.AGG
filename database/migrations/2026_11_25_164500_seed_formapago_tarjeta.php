<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Medio de pago para facturas de tarjeta corporativa (TC CORP).
 * Anita no tiene letra propia: al sync sale como R (solo registrar).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('formapago')) {
            return;
        }

        $existe = DB::table('formapago')
            ->where('abreviatura', 'TC')
            ->orWhere('nombre', 'TARJETA')
            ->exists();

        if ($existe) {
            return;
        }

        $ahora = now();
        DB::table('formapago')->insert([
            'nombre' => 'TARJETA',
            'abreviatura' => 'TC',
            'created_at' => $ahora,
            'updated_at' => $ahora,
        ]);
    }

    public function down(): void
    {
        if (! Schema::hasTable('formapago')) {
            return;
        }

        DB::table('formapago')->where('abreviatura', 'TC')->where('nombre', 'TARJETA')->delete();
    }
};
