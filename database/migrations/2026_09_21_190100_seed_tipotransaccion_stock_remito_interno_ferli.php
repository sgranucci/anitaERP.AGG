<?php

use App\Support\Configuracion\EntornoEmpresaSupport;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Tipostransacción stock remito interno: RINT (salida) y RINTR (reverso anulación).
 * Solo Calzados Ferli.
 */
return new class extends Migration
{
    /** @var list<array{nombre:string,abreviatura:string,signo:int,operacion:string}> */
    private const TIPOS = [
        [
            'nombre' => 'Remito interno local',
            'abreviatura' => 'RINT',
            'signo' => -1,
            'operacion' => 'S',
        ],
        [
            'nombre' => 'Remito interno — reverso anulación',
            'abreviatura' => 'RINTR',
            'signo' => 1,
            'operacion' => 'E',
        ],
    ];

    public function up(): void
    {
        if (! EntornoEmpresaSupport::esFerli()) {
            return;
        }

        if (! DB::getSchemaBuilder()->hasTable('tipotransaccion_stock')) {
            return;
        }

        foreach (self::TIPOS as $row) {
            $existeId = (int) (DB::table('tipotransaccion_stock')
                ->where('abreviatura', $row['abreviatura'])
                ->value('id') ?? 0);

            $payload = [
                'nombre' => $row['nombre'],
                'abreviatura' => $row['abreviatura'],
                'operacion' => $row['operacion'],
                'signo' => $row['signo'],
                'estado' => 'A',
                'requiere_aprobacion' => false,
                'maneja_contabilidad' => false,
                'updated_at' => now(),
            ];

            if ($existeId > 0) {
                DB::table('tipotransaccion_stock')->where('id', $existeId)->update(array_merge($payload, [
                    'deleted_at' => null,
                ]));
            } else {
                DB::table('tipotransaccion_stock')->insert(array_merge($payload, [
                    'created_at' => now(),
                ]));
            }
        }
    }

    public function down(): void
    {
        if (! EntornoEmpresaSupport::esFerli()) {
            return;
        }

        if (! DB::getSchemaBuilder()->hasTable('tipotransaccion_stock')) {
            return;
        }

        DB::table('tipotransaccion_stock')
            ->whereIn('abreviatura', array_column(self::TIPOS, 'abreviatura'))
            ->update(['deleted_at' => now(), 'updated_at' => now()]);
    }
};
