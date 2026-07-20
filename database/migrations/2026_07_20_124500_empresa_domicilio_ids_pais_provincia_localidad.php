<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Domicilio legal de empresa alineado a cliente/proveedor:
 * pais_id + provincia_id + localidad_id + codigopostal.
 * Reemplaza los campos texto agregados en 2026_07_20_123900.
 */
class EmpresaDomicilioIdsPaisProvinciaLocalidad extends Migration
{
    public function up()
    {
        Schema::table('empresa', function (Blueprint $table) {
            if (Schema::hasColumn('empresa', 'localidad')) {
                $table->dropColumn('localidad');
            }
            if (Schema::hasColumn('empresa', 'codigo_postal')) {
                $table->dropColumn('codigo_postal');
            }
            if (Schema::hasColumn('empresa', 'provincia')) {
                $table->dropColumn('provincia');
            }
        });

        Schema::table('empresa', function (Blueprint $table) {
            $table->unsignedBigInteger('pais_id')->nullable()->after('domicilio');
            $table->unsignedBigInteger('provincia_id')->nullable()->after('pais_id');
            $table->unsignedBigInteger('localidad_id')->nullable()->after('provincia_id');
            $table->string('codigopostal', 50)->nullable()->after('localidad_id');

            $table->foreign('pais_id', 'fk_empresa_pais')
                ->references('id')->on('pais')->onDelete('restrict')->onUpdate('restrict');
            $table->foreign('provincia_id', 'fk_empresa_provincia')
                ->references('id')->on('provincia')->onDelete('restrict')->onUpdate('restrict');
            $table->foreign('localidad_id', 'fk_empresa_localidad')
                ->references('id')->on('localidad')->onDelete('restrict')->onUpdate('restrict');
        });

        // Precarga empresas operativas (Argentina / Buenos Aires).
        $seeds = [
            1 => ['pais_id' => 1, 'provincia_id' => 2, 'localidad_id' => 860, 'codigopostal' => '1870'],   // Avellaneda
            2 => ['pais_id' => 1, 'provincia_id' => 2, 'localidad_id' => 865, 'codigopostal' => '1875'],   // Wilde
            3 => ['pais_id' => 1, 'provincia_id' => 2, 'localidad_id' => 10852, 'codigopostal' => '1888'], // Florencio Varela
        ];

        foreach ($seeds as $id => $datos) {
            DB::table('empresa')->where('id', $id)->update($datos);
        }
    }

    public function down()
    {
        Schema::table('empresa', function (Blueprint $table) {
            $table->dropForeign('fk_empresa_pais');
            $table->dropForeign('fk_empresa_provincia');
            $table->dropForeign('fk_empresa_localidad');
            $table->dropColumn(['pais_id', 'provincia_id', 'localidad_id', 'codigopostal']);
        });

        Schema::table('empresa', function (Blueprint $table) {
            $table->string('localidad', 100)->nullable()->after('domicilio');
            $table->string('codigo_postal', 20)->nullable()->after('localidad');
            $table->string('provincia', 100)->nullable()->after('codigo_postal');
        });
    }
}
