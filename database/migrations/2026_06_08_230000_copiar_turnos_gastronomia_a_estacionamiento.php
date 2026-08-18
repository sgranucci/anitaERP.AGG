<?php

use App\Support\Database\MigrationDialectSupport;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Copia turnos maestros de gastronomía a estacionamiento.
 * Idempotente: omite filas ya existentes por id, nombre+empresa o código+empresa.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('turno_gastronomia') || ! Schema::hasTable('turno_estacionamiento')) {
            return;
        }

        $filas = DB::table('turno_gastronomia')->orderBy('id')->get();

        foreach ($filas as $fila) {
            if ($this->turnoEstacionamientoYaExiste($fila)) {
                continue;
            }

            DB::table('turno_estacionamiento')->insert([
                'id' => $fila->id,
                'empresa_id' => $fila->empresa_id,
                'nombre' => $fila->nombre,
                'codigo' => $fila->codigo,
                'hora_desde' => $fila->hora_desde,
                'hora_hasta' => $fila->hora_hasta,
                'orden' => $fila->orden,
                'activo' => $fila->activo,
                'created_at' => $fila->created_at,
                'updated_at' => $fila->updated_at,
            ]);
        }

        $maxId = (int) DB::table('turno_estacionamiento')->max('id');
        if ($maxId > 0) {
            MigrationDialectSupport::reiniciarAutoincrement('turno_estacionamiento', 'id', $maxId + 1);
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('turno_gastronomia') || ! Schema::hasTable('turno_estacionamiento')) {
            return;
        }

        $filas = DB::table('turno_gastronomia')->orderBy('id')->get();

        foreach ($filas as $fila) {
            DB::table('turno_estacionamiento')
                ->where('id', $fila->id)
                ->where('empresa_id', $fila->empresa_id)
                ->where('nombre', $fila->nombre)
                ->where('codigo', $fila->codigo)
                ->where('hora_desde', $fila->hora_desde)
                ->where('hora_hasta', $fila->hora_hasta)
                ->where('orden', $fila->orden)
                ->where('activo', $fila->activo)
                ->delete();
        }
    }

    private function turnoEstacionamientoYaExiste(object $fila): bool
    {
        if (DB::table('turno_estacionamiento')->where('id', $fila->id)->exists()) {
            return true;
        }

        if (DB::table('turno_estacionamiento')
            ->where('empresa_id', $fila->empresa_id)
            ->where('nombre', $fila->nombre)
            ->exists()) {
            return true;
        }

        if ($fila->codigo !== null && $fila->codigo !== ''
            && DB::table('turno_estacionamiento')
                ->where('empresa_id', $fila->empresa_id)
                ->where('codigo', $fila->codigo)
                ->exists()) {
            return true;
        }

        return false;
    }
};
