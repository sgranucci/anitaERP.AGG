<?php

use App\Support\ConsultaInteligente\ConsultaInteligenteNlDiccionarioSupport;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Seeds NL para transferencias de mercadería.
 */
return new class extends Migration
{
    /** @var list<array{frase: string, entidad: string, prioridad: int}> */
    private const SEEDS = [
        ['frase' => 'transferencias de mercaderia', 'entidad' => 'transferencia_mercaderia', 'prioridad' => 15],
        ['frase' => 'transferencia entre depositos', 'entidad' => 'transferencia_mercaderia', 'prioridad' => 14],
        ['frase' => 'tm pendientes', 'entidad' => 'transferencia_mercaderia', 'prioridad' => 12],
    ];

    public function up(): void
    {
        if (! Schema::hasTable('consulta_inteligente_nl_sinonimo')) {
            return;
        }

        foreach (self::SEEDS as $seed) {
            $exists = DB::table('consulta_inteligente_nl_sinonimo')
                ->where('frase', $seed['frase'])
                ->where('entidad', $seed['entidad'])
                ->exists();
            if ($exists) {
                continue;
            }
            DB::table('consulta_inteligente_nl_sinonimo')->insert([
                'frase' => $seed['frase'],
                'entidad' => $seed['entidad'],
                'prioridad' => $seed['prioridad'],
                'activo' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
        ConsultaInteligenteNlDiccionarioSupport::olvidarCache();
    }

    public function down(): void
    {
        if (! Schema::hasTable('consulta_inteligente_nl_sinonimo')) {
            return;
        }
        foreach (self::SEEDS as $seed) {
            DB::table('consulta_inteligente_nl_sinonimo')
                ->where('frase', $seed['frase'])
                ->where('entidad', $seed['entidad'])
                ->delete();
        }
        ConsultaInteligenteNlDiccionarioSupport::olvidarCache();
    }
};
