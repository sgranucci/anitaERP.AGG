<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Elimina descuentos de gastronomía con código 110–117 (ya copiados a estacionamiento).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('descuento_gastronomia')) {
            return;
        }

        DB::table('descuento_gastronomia')
            ->whereRaw('CAST(codigo AS UNSIGNED) >= 110')
            ->whereRaw('CAST(codigo AS UNSIGNED) <= 117')
            ->delete();
    }

    public function down(): void
    {
        if (! Schema::hasTable('descuento_gastronomia') || ! Schema::hasTable('descuento_estacionamiento')) {
            return;
        }

        $filas = DB::table('descuento_estacionamiento')
            ->whereRaw('CAST(codigo AS UNSIGNED) >= 110')
            ->whereRaw('CAST(codigo AS UNSIGNED) <= 117')
            ->orderByRaw('CAST(codigo AS UNSIGNED)')
            ->get();

        foreach ($filas as $fila) {
            if (DB::table('descuento_gastronomia')->where('codigo', $fila->codigo)->exists()) {
                continue;
            }

            DB::table('descuento_gastronomia')->insert([
                'nombre' => $fila->nombre,
                'codigo' => $fila->codigo,
                'tipovalor' => $fila->tipovalor,
                'valor' => $fila->valor,
                'cliente_id' => $fila->cliente_id,
                'created_at' => $fila->created_at ?? now(),
                'updated_at' => $fila->updated_at ?? now(),
            ]);
        }
    }
};
