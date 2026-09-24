<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Tipos de asiento estándar que en AGG/Bierzo venían de data inicial y en
 * Interforming/Ferli a veces faltan (p. ej. VTA → “no existe tipo de asiento de ventas”).
 * Idempotente: solo inserta abreviaturas ausentes.
 */
return new class extends Migration
{
    /** @var list<array{abreviatura: string, nombre: string}> */
    private const TIPOS = [
        ['abreviatura' => 'VTA', 'nombre' => 'Ventas'],
        ['abreviatura' => 'COM', 'nombre' => 'Compras'],
        ['abreviatura' => 'TES', 'nombre' => 'Tesorería'],
        ['abreviatura' => 'CONT', 'nombre' => 'Contable'],
        ['abreviatura' => 'STK', 'nombre' => 'Stock'],
        ['abreviatura' => 'PER', 'nombre' => 'Personal'],
        ['abreviatura' => 'GAS', 'nombre' => 'Gastos'],
        ['abreviatura' => 'APE', 'nombre' => 'Apertura'],
        ['abreviatura' => 'EGA', 'nombre' => 'Egreso automático'],
        ['abreviatura' => 'APJ', 'nombre' => 'Ajuste personal'],
        ['abreviatura' => 'AMO', 'nombre' => 'Amortización'],
        ['abreviatura' => 'AJ', 'nombre' => 'Ajuste por inflación'],
        ['abreviatura' => 'CIR', 'nombre' => 'Cierre de ejercicio'],
        ['abreviatura' => 'CIP', 'nombre' => 'Cierre de ejercicio (patrimonial)'],
        ['abreviatura' => 'CIJ', 'nombre' => 'Cierre ajuste por inflación'],
        ['abreviatura' => 'REM', 'nombre' => 'Remesa'],
        ['abreviatura' => 'SUEL', 'nombre' => 'Sueldos'],
        ['abreviatura' => 'BIN', 'nombre' => 'Bingo'],
        ['abreviatura' => 'MAQ', 'nombre' => 'Máquinas'],
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
        // No borrar: pueden tener asientos referenciados.
    }
};
