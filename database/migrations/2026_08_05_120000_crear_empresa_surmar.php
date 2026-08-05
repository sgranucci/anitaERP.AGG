<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Empresa Surmar (El Bierzo) — id fijo 3 para referencias de negocio/código.
 * No toca procesos AGG; habilita módulos stock/producción Surmar.
 */
return new class extends Migration
{
    private const EMPRESA_ID = 3;

    public function up(): void
    {
        $existe = DB::table('empresa')->where('id', self::EMPRESA_ID)->exists();
        if ($existe) {
            DB::table('empresa')->where('id', self::EMPRESA_ID)->update([
                'nombre' => 'Surmar',
                'codigo' => 3,
                'updated_at' => now(),
            ]);

            return;
        }

        $payload = [
            'id' => self::EMPRESA_ID,
            'nombre' => 'Surmar',
            'domicilio' => null,
            'nroinscripcion' => null,
            'codigo' => 3,
            'numeroiibb' => null,
            'fechainicioactividad' => '2026-01-01',
            'created_at' => now(),
            'updated_at' => now(),
        ];

        // Columnas opcionales agregadas en migraciones posteriores
        foreach (['pais_id', 'provincia_id', 'localidad_id', 'codigopostal'] as $col) {
            if (\Illuminate\Support\Facades\Schema::hasColumn('empresa', $col)) {
                $payload[$col] = null;
            }
        }

        DB::table('empresa')->insert($payload);
    }

    public function down(): void
    {
        DB::table('empresa')->where('id', self::EMPRESA_ID)->where('nombre', 'Surmar')->delete();
    }
};
