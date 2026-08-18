<?php

use App\Support\Database\MigrationDialectSupport;
use App\Support\Database\SqlDialectSupport;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Copia descuentos de gastronomía con código > 100 y <= 117 a estacionamiento.
 * Conserva codigo; asigna id secuencial desde 1 ordenado por código.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('descuento_gastronomia') || ! Schema::hasTable('descuento_estacionamiento')) {
            return;
        }

        $castCodigo = SqlDialectSupport::castEntero('codigo');
        $filas = DB::table('descuento_gastronomia')
            ->whereRaw($castCodigo.' > 100')
            ->whereRaw($castCodigo.' <= 117')
            ->orderByRaw($castCodigo)
            ->get();

        $nuevoId = 1;

        foreach ($filas as $fila) {
            if (DB::table('descuento_estacionamiento')->where('codigo', $fila->codigo)->exists()) {
                continue;
            }

            while (DB::table('descuento_estacionamiento')->where('id', $nuevoId)->exists()) {
                $nuevoId++;
            }

            DB::table('descuento_estacionamiento')->insert([
                'id' => $nuevoId,
                'nombre' => $fila->nombre,
                'codigo' => $fila->codigo,
                'tipovalor' => $fila->tipovalor,
                'valor' => $fila->valor,
                'cliente_id' => $fila->cliente_id,
                'created_at' => $fila->created_at ?? now(),
                'updated_at' => $fila->updated_at ?? now(),
            ]);

            $nuevoId++;
        }

        $maxId = (int) DB::table('descuento_estacionamiento')->max('id');
        if ($maxId > 0) {
            MigrationDialectSupport::reiniciarAutoincrement('descuento_estacionamiento', 'id', $maxId + 1);
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('descuento_gastronomia') || ! Schema::hasTable('descuento_estacionamiento')) {
            return;
        }

        $castCodigo = SqlDialectSupport::castEntero('codigo');
        $codigos = DB::table('descuento_gastronomia')
            ->whereRaw($castCodigo.' > 100')
            ->whereRaw($castCodigo.' <= 117')
            ->pluck('codigo')
            ->all();

        if ($codigos !== []) {
            DB::table('descuento_estacionamiento')->whereIn('codigo', $codigos)->delete();
        }

        $maxId = (int) DB::table('descuento_estacionamiento')->max('id');
        $next = $maxId > 0 ? $maxId + 1 : 1;
        MigrationDialectSupport::reiniciarAutoincrement('descuento_estacionamiento', 'id', $next);
    }
};
