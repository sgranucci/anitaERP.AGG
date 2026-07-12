<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('turno_estacionamiento') || ! Schema::hasTable('turno_bingo')) {
            return;
        }

        $origen = DB::table('turno_estacionamiento')->orderBy('empresa_id')->orderBy('orden')->get();

        foreach ($origen as $row) {
            $existe = DB::table('turno_bingo')
                ->where('empresa_id', $row->empresa_id)
                ->where('nombre', $row->nombre)
                ->exists();

            if ($existe) {
                continue;
            }

            DB::table('turno_bingo')->insert([
                'empresa_id' => $row->empresa_id,
                'nombre' => $row->nombre,
                'codigo' => $row->codigo,
                'hora_desde' => $row->hora_desde,
                'hora_hasta' => $row->hora_hasta,
                'orden' => $row->orden,
                'activo' => $row->activo,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        // No revertir: los turnos bingo pueden haber sido editados manualmente.
    }
};
