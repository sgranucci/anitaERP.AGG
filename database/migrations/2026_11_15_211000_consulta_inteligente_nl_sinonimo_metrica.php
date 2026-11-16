<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Diccionario NL: sinónimos opcionales de métrica (aging, suma, promedio, conteo).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('consulta_inteligente_nl_sinonimo')) {
            return;
        }
        if (! Schema::hasColumn('consulta_inteligente_nl_sinonimo', 'metrica')) {
            Schema::table('consulta_inteligente_nl_sinonimo', function (Blueprint $table) {
                $table->string('metrica', 20)->nullable()->after('entidad');
                $table->index(['activo', 'metrica'], 'ci_nl_sinonimo_activo_metrica_idx');
            });
        }

        $seeds = [
            ['frase' => 'aging de cheques', 'entidad' => 'cheque', 'metrica' => 'aging', 'prioridad' => 40],
            ['frase' => 'aging de facturas', 'entidad' => 'comprobante_proveedor', 'metrica' => 'aging', 'prioridad' => 40],
            ['frase' => 'aging de cobranzas', 'entidad' => 'cobranza', 'metrica' => 'aging', 'prioridad' => 40],
            ['frase' => 'suma de cheques', 'entidad' => 'cheque', 'metrica' => 'suma', 'prioridad' => 50],
            ['frase' => 'promedio de facturas', 'entidad' => 'comprobante_proveedor', 'metrica' => 'promedio', 'prioridad' => 50],
        ];
        foreach ($seeds as $s) {
            $exists = DB::table('consulta_inteligente_nl_sinonimo')
                ->where('frase', $s['frase'])
                ->where('entidad', $s['entidad'])
                ->exists();
            if ($exists) {
                DB::table('consulta_inteligente_nl_sinonimo')
                    ->where('frase', $s['frase'])
                    ->where('entidad', $s['entidad'])
                    ->update(['metrica' => $s['metrica'], 'activo' => 1, 'updated_at' => now()]);
                continue;
            }
            DB::table('consulta_inteligente_nl_sinonimo')->insert([
                'frase' => $s['frase'],
                'entidad' => $s['entidad'],
                'metrica' => $s['metrica'],
                'prioridad' => $s['prioridad'],
                'activo' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('consulta_inteligente_nl_sinonimo')) {
            return;
        }
        if (Schema::hasColumn('consulta_inteligente_nl_sinonimo', 'metrica')) {
            Schema::table('consulta_inteligente_nl_sinonimo', function (Blueprint $table) {
                $table->dropIndex('ci_nl_sinonimo_activo_metrica_idx');
                $table->dropColumn('metrica');
            });
        }
    }
};
