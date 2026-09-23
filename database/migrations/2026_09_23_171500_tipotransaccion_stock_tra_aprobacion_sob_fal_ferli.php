<?php

use App\Support\Configuracion\EntornoEmpresaSupport;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Ferli: TRA con aprobación de recepción; tipos SOB/FAL; nombres ENT/SAL de negocio.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! EntornoEmpresaSupport::esFerli()) {
            return;
        }

        if (! Schema::hasTable('tipotransaccion_stock')) {
            return;
        }

        $now = now();

        // Solo TRA queda pendiente de recepción en el destino.
        DB::table('tipotransaccion_stock')
            ->where('abreviatura', 'TRA')
            ->whereNull('deleted_at')
            ->update([
                'requiere_aprobacion' => 1,
                'aviso_opcional' => 0,
                'updated_at' => $now,
            ]);

        DB::table('tipotransaccion_stock')
            ->where('abreviatura', '!=', 'TRA')
            ->where('requiere_aprobacion', 1)
            ->whereNull('deleted_at')
            ->update([
                'requiere_aprobacion' => 0,
                'updated_at' => $now,
            ]);

        DB::table('tipotransaccion_stock')
            ->where('abreviatura', 'ENT')
            ->whereNull('deleted_at')
            ->update([
                'nombre' => 'ENTRADA DE MERCADERIA',
                'updated_at' => $now,
            ]);

        DB::table('tipotransaccion_stock')
            ->where('abreviatura', 'SAL')
            ->whereNull('deleted_at')
            ->update([
                'nombre' => 'SALIDA DE MERCADERIA',
                'updated_at' => $now,
            ]);

        $this->asegurarTipo([
            'abreviatura' => 'SOB',
            'nombre' => 'SOBRANTE DE STOCK',
            'operacion' => 'E',
            'signo' => 1,
        ], $now);

        $this->asegurarTipo([
            'abreviatura' => 'FAL',
            'nombre' => 'FALTANTE DE STOCK',
            'operacion' => 'S',
            'signo' => -1,
        ], $now);
    }

    public function down(): void
    {
        if (! EntornoEmpresaSupport::esFerli()) {
            return;
        }

        if (! Schema::hasTable('tipotransaccion_stock')) {
            return;
        }

        $now = now();

        DB::table('tipotransaccion_stock')
            ->where('abreviatura', 'TRA')
            ->update([
                'requiere_aprobacion' => 0,
                'updated_at' => $now,
            ]);

        foreach (['SOB', 'FAL'] as $abrev) {
            DB::table('tipotransaccion_stock')
                ->where('abreviatura', $abrev)
                ->update([
                    'deleted_at' => $now,
                    'updated_at' => $now,
                ]);
        }
    }

    /**
     * @param  array{abreviatura:string,nombre:string,operacion:string,signo:int}  $row
     */
    private function asegurarTipo(array $row, $now): void
    {
        $existeId = (int) (DB::table('tipotransaccion_stock')
            ->where('abreviatura', $row['abreviatura'])
            ->value('id') ?? 0);

        $payload = [
            'nombre' => $row['nombre'],
            'abreviatura' => $row['abreviatura'],
            'operacion' => $row['operacion'],
            'signo' => $row['signo'],
            'estado' => 'A',
            'requiere_aprobacion' => 0,
            'aviso_opcional' => 0,
            'maneja_contabilidad' => 0,
            'updated_at' => $now,
        ];

        if ($existeId > 0) {
            DB::table('tipotransaccion_stock')->where('id', $existeId)->update(array_merge($payload, [
                'deleted_at' => null,
            ]));

            return;
        }

        DB::table('tipotransaccion_stock')->insert(array_merge($payload, [
            'created_at' => $now,
        ]));
    }
};
