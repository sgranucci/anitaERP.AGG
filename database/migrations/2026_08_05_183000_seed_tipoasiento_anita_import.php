<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /** @var list<array{abreviatura: string, nombre: string}> */
    private const TIPOS = [
        ['abreviatura' => 'GAS', 'nombre' => 'Gastos'],
        ['abreviatura' => 'PER', 'nombre' => 'Personal'],
        ['abreviatura' => 'APE', 'nombre' => 'Apertura'],
        ['abreviatura' => 'EGA', 'nombre' => 'Egreso automático'],
        ['abreviatura' => 'APJ', 'nombre' => 'Ajuste personal'],
        ['abreviatura' => 'AMO', 'nombre' => 'Amortización'],
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
        // No eliminar tipos que puedan estar referenciados por asientos.
    }
};
