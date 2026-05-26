<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Crea las transacciones de stock que usa el módulo de préstamos.
 *
 *  - PRSAL: Préstamo - salida del depósito origen (signo Resta).
 *  - PRING: Préstamo - ingreso al depósito destino tras aprobación (signo Suma).
 *  - PRRCH: Préstamo - reverso de salida tras rechazo (signo Suma).
 *  - PRDSL: Préstamo - devolución, salida del destino (signo Resta).
 *  - PRDIN: Préstamo - devolución, ingreso al origen (signo Suma).
 */
return new class extends Migration
{
    /** @var array<int, array{nombre:string, abreviatura:string, signo:int, operacion:string}> */
    private array $tipos = [
        ['nombre' => 'Préstamo - Salida origen', 'abreviatura' => 'PRSAL', 'signo' => -1, 'operacion' => 'S'],
        ['nombre' => 'Préstamo - Ingreso destino', 'abreviatura' => 'PRING', 'signo' => 1, 'operacion' => 'E'],
        ['nombre' => 'Préstamo - Reverso por rechazo', 'abreviatura' => 'PRRCH', 'signo' => 1, 'operacion' => 'E'],
        ['nombre' => 'Préstamo - Devolución salida destino', 'abreviatura' => 'PRDSL', 'signo' => -1, 'operacion' => 'S'],
        ['nombre' => 'Préstamo - Devolución ingreso origen', 'abreviatura' => 'PRDIN', 'signo' => 1, 'operacion' => 'E'],
    ];

    public function up(): void
    {
        foreach ($this->tipos as $row) {
            $existeId = DB::table('tipotransaccion_stock')
                ->where('abreviatura', $row['abreviatura'])
                ->value('id');

            if ($existeId) {
                DB::table('tipotransaccion_stock')->where('id', $existeId)->update([
                    'nombre' => $row['nombre'],
                    'operacion' => $row['operacion'],
                    'signo' => $row['signo'],
                    'estado' => 'A',
                    'deleted_at' => null,
                    'updated_at' => now(),
                ]);
            } else {
                DB::table('tipotransaccion_stock')->insert([
                    'nombre' => $row['nombre'],
                    'abreviatura' => $row['abreviatura'],
                    'operacion' => $row['operacion'],
                    'signo' => $row['signo'],
                    'estado' => 'A',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        DB::table('tipotransaccion_stock')
            ->whereIn('abreviatura', array_column($this->tipos, 'abreviatura'))
            ->update(['deleted_at' => now()]);
    }
};
