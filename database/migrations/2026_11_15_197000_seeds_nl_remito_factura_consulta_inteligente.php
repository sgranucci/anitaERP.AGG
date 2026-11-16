<?php

use App\Support\ConsultaInteligente\ConsultaInteligenteNlDiccionarioSupport;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Seeds NL para remito y factura de venta (diccionario editable).
 */
return new class extends Migration
{
    /** @var list<array{frase: string, entidad: string, prioridad: int}> */
    private const SEEDS = [
        ['frase' => 'remitos del mes', 'entidad' => 'remito', 'prioridad' => 15],
        ['frase' => 'remitos de venta', 'entidad' => 'remito', 'prioridad' => 15],
        ['frase' => 'facturas de venta', 'entidad' => 'factura', 'prioridad' => 15],
        ['frase' => 'fc venta', 'entidad' => 'factura', 'prioridad' => 15],
        ['frase' => 'facturas sin remito', 'entidad' => 'factura', 'prioridad' => 12],
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
