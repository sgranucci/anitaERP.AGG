<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Tipos usados en ctamov Anita (Biyemas) que faltaban en ERP.
 * Cierre/inflación alineados a lo que aparece en l-mayor / ctamov real:
 * AJ (aj. inflación ABM), CIR/CIP/CIJ (cierres de ejercicio), SHO (show).
 */
return new class extends Migration
{
    /** @var list<array{abreviatura: string, nombre: string}> */
    private const TIPOS = [
        ['abreviatura' => 'AJ', 'nombre' => 'Ajuste por inflación'],
        ['abreviatura' => 'CIR', 'nombre' => 'Cierre de ejercicio'],
        ['abreviatura' => 'CIP', 'nombre' => 'Cierre de ejercicio (patrimonial)'],
        ['abreviatura' => 'CIJ', 'nombre' => 'Cierre ajuste por inflación'],
        ['abreviatura' => 'SHO', 'nombre' => 'Show'],
        // Alias legacy documentados en FILA_valida / MayorPlanoCuentaSupport
        ['abreviatura' => 'CIE', 'nombre' => 'Cierre (legacy)'],
        ['abreviatura' => 'CER', 'nombre' => 'Cierre (legacy CER)'],
        ['abreviatura' => 'CIER', 'nombre' => 'Cierre (legacy CIER)'],
        ['abreviatura' => 'INF', 'nombre' => 'Inflación (legacy INF)'],
        ['abreviatura' => 'AJI', 'nombre' => 'Ajuste inflación (legacy AJI)'],
        ['abreviatura' => 'AJU', 'nombre' => 'Ajuste (legacy AJU)'],
        ['abreviatura' => 'INFL', 'nombre' => 'Inflación (legacy INFL)'],
    ];

    public function up(): void
    {
        $now = now();
        foreach (self::TIPOS as $tipo) {
            $existe = DB::table('tipoasiento')
                ->where('abreviatura', $tipo['abreviatura'])
                ->exists();
            if ($existe) {
                continue;
            }

            DB::table('tipoasiento')->insert([
                'nombre' => $tipo['nombre'],
                'abreviatura' => $tipo['abreviatura'],
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        // No eliminar tipos referenciables.
    }
};
