<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CrearTablaCuentacontable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('cuentacontable', function (Blueprint $table) {
            $table->bigIncrements('id');
			$table->unsignedBigInteger('empresa_id');
            $table->foreign('empresa_id', 'fk_cuentacontable_empresa')->references('id')->on('empresa')->onDelete('restrict')->onUpdate('restrict');
			$table->unsignedBigInteger('rubrocontable_id');
            $table->foreign('rubrocontable_id', 'fk_cuentacontable_rubrocontable')->references('id')->on('rubrocontable')->onDelete('restrict')->onUpdate('restrict');
            $table->unsignedInteger('cuentacontable_id')->default(0);
            $table->unsignedInteger('orden')->default(0);
            $table->unsignedInteger('nivel')->default(1);
            $table->string('nombre',100);
            $table->string('codigo',50);
            $table->string('tipocuenta',1);
            $table->string('monetaria',1);
            $table->string('manejaccosto',1);
            $table->timestamps();
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_spanish_ci';
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('cuentacontable');
    }
}
