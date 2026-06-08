<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Tipos de transacción de stock para recuentos de inventario.
 *
 *  - RCAJP: Ajuste positivo por recuento (sobrante).
 *  - RCAJN: Ajuste negativo por recuento (faltante).
 *  - RCAJR: Reverso de ajuste al anular cierre de recuento.
 */
return new class extends Migration
{
    /** @var array<int, array{nombre:string, abreviatura:string, signo:int, operacion:string}> */
    private array $tipos = [
        ['nombre' => 'Recuento - Ajuste positivo', 'abreviatura' => 'RCAJP', 'signo' => 1, 'operacion' => 'E'],
        ['nombre' => 'Recuento - Ajuste negativo', 'abreviatura' => 'RCAJN', 'signo' => -1, 'operacion' => 'S'],
        ['nombre' => 'Recuento - Reverso anulación cierre', 'abreviatura' => 'RCAJR', 'signo' => 1, 'operacion' => 'E'],
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
