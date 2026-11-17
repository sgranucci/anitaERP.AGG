<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Plantilla inactiva: umbral doble aprobación del árbol (5M).
 */
return new class extends Migration
{
    private const CODIGO = 'piloto_arbol_umbral_5m';

    public function up(): void
    {
        if (! Schema::hasTable('regla_negocio')) {
            return;
        }
        if (DB::table('regla_negocio')->where('codigo', self::CODIGO)->exists()) {
            return;
        }

        $now = now();
        DB::table('regla_negocio')->insert([
            'codigo' => self::CODIGO,
            'nombre' => 'Piloto árbol: umbral nivel alto 5.000.000',
            'descripcion' => 'Igual al hardcode histórico. Activar motor+regla para gobernar umbral_nivel_alto desde BRE.',
            'dominio' => 'configuracion',
            'punto' => 'configuracion.arbol.umbral_doble_aprobacion',
            'activo' => false,
            'prioridad' => 50,
            'stop_on_match' => true,
            'condiciones' => json_encode(['logic' => 'and', 'rules' => []], JSON_UNESCAPED_UNICODE),
            'acciones' => json_encode([
                ['tipo' => 'set_dato', 'clave' => 'umbral_nivel_alto', 'valor' => 5000000],
            ], JSON_UNESCAPED_UNICODE),
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    public function down(): void
    {
        if (! Schema::hasTable('regla_negocio')) {
            return;
        }
        DB::table('regla_negocio')->where('codigo', self::CODIGO)->delete();
    }
};
