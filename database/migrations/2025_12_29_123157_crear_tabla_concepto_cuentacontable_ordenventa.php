<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CrearTablaConceptoCuentacontableOrdenventa extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('concepto_cuentacontable_ordenventa', function (Blueprint $table) {
            $table->bigIncrements('id');
			$table->unsignedBigInteger('concepto_ordenventa_id');
            $table->foreign('concepto_ordenventa_id', 'fk_concepto_cuentacontable_ordenventa_concepto_ordenventa')->references('id')->on('concepto_ordenventa')->onDelete('cascade')->onUpdate('cascade');
			$table->unsignedBigInteger('empresa_id');
            $table->foreign('empresa_id', 'fk_concepto_cuentacontable_ordenventa_empresa')->references('id')->on('empresa')->onDelete('restrict')->onUpdate('restrict');
			$table->unsignedBigInteger('cuentacontable_id');
            $table->foreign('cuentacontable_id', 'fk_concepto_cuentacontable_ordenventa_cuentacontable')->references('id')->on('cuentacontable')->onDelete('restrict')->onUpdate('restrict');
            $table->unsignedBigInteger('creousuario_id');
            $table->foreign('creousuario_id', 'fk_concepto_cuentacontable_ordenventa_usuario')->references('id')->on('usuario')->onDelete('restrict')->onUpdate('restrict');
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
        Schema::dropIfExists('concepto_cuentacontable_ordenventa');
    }
}
