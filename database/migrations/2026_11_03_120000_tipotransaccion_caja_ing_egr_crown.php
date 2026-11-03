<?php

use App\Support\Configuracion\EntornoEmpresaSupport;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Crown: tipos de tesorería ING (ingreso) y EGR (egreso) para Ingresos y Egresos.
 * En AGG/Ferli ya existían; en Crown solo estaban REM/RMI/TRA/OPA/COA/CANJE/OPP.
 */
return new class extends Migration
{
    /** @var list<array{abreviatura: string, nombre: string, operacion: string, signo: int}> */
    private const TIPOS = [
        [
            'abreviatura' => 'ING',
            'nombre' => 'Ingresos',
            'operacion' => 'I',
            'signo' => 1,
        ],
        [
            'abreviatura' => 'EGR',
            'nombre' => 'Egresos',
            'operacion' => 'E',
            'signo' => -1,
        ],
    ];

    public function up(): void
    {
        if (! EntornoEmpresaSupport::esCrown()) {
            return;
        }
        if (! DB::getSchemaBuilder()->hasTable('tipotransaccion_caja')) {
            return;
        }

        $ahora = now();
        foreach (self::TIPOS as $tipo) {
            $existente = DB::table('tipotransaccion_caja')
                ->whereRaw('UPPER(TRIM(abreviatura)) = ?', [$tipo['abreviatura']])
                ->whereNull('deleted_at')
                ->first();

            if ($existente) {
                DB::table('tipotransaccion_caja')->where('id', $existente->id)->update([
                    'nombre' => $tipo['nombre'],
                    'operacion' => $tipo['operacion'],
                    'signo' => $tipo['signo'],
                    'estado' => 'A',
                    'updated_at' => $ahora,
                ]);

                continue;
            }

            DB::table('tipotransaccion_caja')->insert([
                'nombre' => $tipo['nombre'],
                'operacion' => $tipo['operacion'],
                'abreviatura' => $tipo['abreviatura'],
                'signo' => $tipo['signo'],
                'estado' => 'A',
                'created_at' => $ahora,
                'updated_at' => $ahora,
            ]);
        }
    }

    public function down(): void
    {
        if (! EntornoEmpresaSupport::esCrown()) {
            return;
        }

        // No borra ING/EGR: pueden tener movimientos.
    }
};
