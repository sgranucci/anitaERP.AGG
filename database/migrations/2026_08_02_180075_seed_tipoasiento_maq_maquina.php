<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $abrev = (string) config('rendicion_maquina_anita.cierre_rendicion_contable.tipoasiento_abreviatura', 'MAQ');

        $existe = DB::table('tipoasiento')
            ->where('abreviatura', $abrev)
            ->exists();

        if ($existe) {
            return;
        }

        DB::table('tipoasiento')->insert([
            'nombre' => 'Máquinas',
            'abreviatura' => $abrev,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        // No eliminar tipos en uso por asientos históricos.
    }
};
