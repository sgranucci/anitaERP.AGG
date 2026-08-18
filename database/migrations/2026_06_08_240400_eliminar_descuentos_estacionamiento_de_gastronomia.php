<?php

use App\Support\Database\MigrationDialectSupport;
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

        $cast = MigrationDialectSupport::castEntero('codigo');

        DB::table('descuento_gastronomia')
            ->whereRaw($cast.' >= 110')
            ->whereRaw($cast.' <= 117')
            ->delete();
    }

    public function down(): void
    {
        if (! Schema::hasTable('descuento_gastronomia') || ! Schema::hasTable('descuento_estacionamiento')) {
            return;
        }

        $cast = MigrationDialectSupport::castEntero('codigo');

        $filas = DB::table('descuento_estacionamiento')
            ->whereRaw($cast.' >= 110')
            ->whereRaw($cast.' <= 117')
            ->orderByRaw($cast)
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
