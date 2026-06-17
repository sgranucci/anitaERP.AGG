<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Tipos de asiento requeridos al confirmar recepción de proveedores (ctamov COM / fallback STK).
 */
return new class extends Migration
{
    /** @var list<array{abreviatura: string, nombre: string}> */
    private const TIPOS = [
        ['abreviatura' => 'COM', 'nombre' => 'Compras'],
        ['abreviatura' => 'STK', 'nombre' => 'Stock'],
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
        // No eliminar tipos en uso por asientos históricos.
    }
};
