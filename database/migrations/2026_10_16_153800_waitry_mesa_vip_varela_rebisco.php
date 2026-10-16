<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * VIP Mesa 1 de Varela (Rebisco, empresa 3). Waitry tableId 101695.
 * Wilde (Kandiko) y Avellaneda (Biyemas) ya estaban cargados.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('waitry_mesa_gastronomia')) {
            return;
        }

        if (! DB::table('empresa')->where('id', 3)->exists()) {
            return;
        }

        $existe = DB::table('waitry_mesa_gastronomia')
            ->where('empresa_id', 3)
            ->where('table_id', 101695)
            ->exists();
        if ($existe) {
            return;
        }

        DB::table('waitry_mesa_gastronomia')->insert([
            'empresa_id' => 3,
            'servicio' => 'VIP',
            'nombre' => 'Mesa 1',
            'table_id' => 101695,
            'activo' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        if (! Schema::hasTable('waitry_mesa_gastronomia')) {
            return;
        }

        DB::table('waitry_mesa_gastronomia')
            ->where('empresa_id', 3)
            ->where('servicio', 'VIP')
            ->where('table_id', 101695)
            ->delete();
    }
};
