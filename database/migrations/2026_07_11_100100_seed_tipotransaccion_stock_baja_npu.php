<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Baja de NPU por rotura o no funcionamiento (egreso de stock + baja del número).
 */
return new class extends Migration
{
    /** @var array{nombre:string, abreviatura:string, signo:int, operacion:string} */
    private array $tipo = [
        'nombre' => 'Baja NPU - Rotura/No funcional',
        'abreviatura' => 'NPUBJ',
        'signo' => 'R',
        'operacion' => 'S',
    ];

    public function up(): void
    {
        $row = $this->tipo;
        $existeId = DB::table('tipotransaccion_stock')
            ->where('abreviatura', $row['abreviatura'])
            ->value('id');

        $payload = [
            'nombre' => $row['nombre'],
            'operacion' => $row['operacion'],
            'signo' => $row['signo'],
            'estado' => 'A',
            'maneja_contabilidad' => 0,
            'requiere_aprobacion' => 0,
            'destino_bien_uso' => 0,
            'origen_bien_uso' => 0,
            'deleted_at' => null,
            'updated_at' => now(),
        ];

        if ($existeId) {
            DB::table('tipotransaccion_stock')->where('id', $existeId)->update($payload);
        } else {
            DB::table('tipotransaccion_stock')->insert(array_merge($payload, [
                'abreviatura' => $row['abreviatura'],
                'created_at' => now(),
            ]));
        }
    }

    public function down(): void
    {
        DB::table('tipotransaccion_stock')
            ->where('abreviatura', $this->tipo['abreviatura'])
            ->update(['deleted_at' => now()]);
    }
};
