<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Cierre FBI/FSL Rebisco: sucursal 14 (config bingo/máquinas).
 * Biyemas ya tiene 00039 y Kandiko 00026; faltaba el PV modo M de Rebisco.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (strtoupper((string) config('app.empresa')) !== 'AGG') {
            return;
        }

        if (! Schema::hasTable('puntoventa')) {
            return;
        }

        if (! DB::table('empresa')->where('id', 3)->exists()) {
            return;
        }

        $now = now();
        $atributos = [
            'nombre' => 'Bingo y Maquinas Rebisco',
            'codigo' => '00014',
            'empresa_id' => 3,
            'domicilio' => 'Monteagudo 3031',
            'localidad_id' => 108,
            'provincia_id' => 3,
            'pais_id' => 1,
            'codigopostal' => null,
            'email' => null,
            'telefono' => null,
            'leyenda' => '89',
            'modofacturacion' => 'M',
            'estado' => 'A',
            'webservice' => 'wsfev1',
            'pathafip' => '88',
            'actividad_arca_id' => null,
            'division' => null,
            'numeropoliza' => null,
            'puntoventa_remito' => null,
            'deleted_at' => null,
            'updated_at' => $now,
        ];

        $existente = DB::table('puntoventa')
            ->where('empresa_id', 3)
            ->where('codigo', '00014')
            ->first();

        if ($existente) {
            DB::table('puntoventa')->where('id', $existente->id)->update($atributos);

            return;
        }

        DB::table('puntoventa')->insert(array_merge($atributos, [
            'created_at' => $now,
        ]));
    }

    public function down(): void
    {
        // No revertir: el PV queda en uso para FBI/FSL de Rebisco.
    }
};
