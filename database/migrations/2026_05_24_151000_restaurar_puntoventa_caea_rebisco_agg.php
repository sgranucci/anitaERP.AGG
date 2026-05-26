<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Restaura el PV CAEA Rebisco (id 4, código 00030, empresa Rebisco) solo en entorno AGG.
     */
    public function up(): void
    {
        if (strtoupper((string) config('app.empresa')) !== 'AGG') {
            return;
        }

        if (! Schema::hasTable('puntoventa')) {
            return;
        }

        $now = now();
        $atributos = [
            'nombre' => 'CAEA Rebisco',
            'codigo' => '00030',
            'empresa_id' => 3,
            'domicilio' => 'Monteagudo 3031',
            'localidad_id' => 10852,
            'provincia_id' => 2,
            'pais_id' => 1,
            'codigopostal' => '1888',
            'email' => null,
            'telefono' => '4242-0202',
            'leyenda' => null,
            'modofacturacion' => 'A',
            'estado' => 'A',
            'webservice' => 'wsfe_v1',
            'pathafip' => 'afip.rsa',
            'actividad_arca_id' => null,
            'division' => null,
            'numeropoliza' => null,
            'puntoventa_remito' => null,
            'deleted_at' => null,
            'updated_at' => $now,
        ];

        $existente = DB::table('puntoventa')->where('id', 4)->first();
        if ($existente) {
            DB::table('puntoventa')->where('id', 4)->update($atributos);
        } else {
            DB::table('puntoventa')->insert(array_merge($atributos, [
                'id' => 4,
                'created_at' => $now,
            ]));
        }

        if (Schema::hasTable('configuracion_puntoventa_gastronomia')) {
            DB::table('configuracion_puntoventa_gastronomia')
                ->where('empresa_id', 3)
                ->update(['puntoventa_caea_id' => 4]);
        }
    }

    public function down(): void
    {
        // No revertir: el PV puede seguir en uso en AGG.
    }
};
