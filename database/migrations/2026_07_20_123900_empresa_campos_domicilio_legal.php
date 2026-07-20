<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Datos legales del empleador para la constancia SRT / BSA de entrega de EPP:
 * (3) Dirección, (4) Localidad, (5) CP, (6) Provincia.
 * domicilio sigue siendo la calle/número; localidad/cp/provincia van separados.
 */
class EmpresaCamposDomicilioLegal extends Migration
{
    public function up()
    {
        Schema::table('empresa', function (Blueprint $table) {
            $table->string('localidad', 100)->nullable()->after('domicilio');
            $table->string('codigo_postal', 20)->nullable()->after('localidad');
            $table->string('provincia', 100)->nullable()->after('codigo_postal');
        });

        // Precarga de empresas operativas conocidas (no pisa domicilio histórico).
        $seeds = [
            1 => [
                'localidad' => 'Avellaneda',
                'codigo_postal' => '1870',
                'provincia' => 'Buenos Aires',
            ],
            2 => [
                'localidad' => 'Wilde',
                'codigo_postal' => '1875',
                'provincia' => 'Buenos Aires',
            ],
            3 => [
                'localidad' => 'Florencio Varela',
                'codigo_postal' => '1888',
                'provincia' => 'Buenos Aires',
            ],
        ];

        foreach ($seeds as $id => $datos) {
            DB::table('empresa')->where('id', $id)->update($datos);
        }
    }

    public function down()
    {
        Schema::table('empresa', function (Blueprint $table) {
            $table->dropColumn(['localidad', 'codigo_postal', 'provincia']);
        });
    }
}
