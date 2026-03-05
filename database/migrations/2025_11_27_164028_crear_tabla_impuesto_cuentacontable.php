<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CrearTablaImpuestoCuentacontable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('impuesto_cuentacontable', function (Blueprint $table) {
            $table->bigIncrements('id');
			$table->unsignedBigInteger('impuesto_id');
            $table->foreign('impuesto_id', 'fk_impuesto_cuentacontable_impuesto')->references('id')->on('impuesto')->onDelete('cascade')->onUpdate('cascade');
			$table->unsignedBigInteger('empresa_id');
            $table->foreign('empresa_id', 'fk_impuesto_cuentacontable_empresa')->references('id')->on('empresa')->onDelete('restrict')->onUpdate('restrict');
			$table->unsignedBigInteger('cuentacontable_id');
            $table->foreign('cuentacontable_id', 'fk_impuesto_cuentacontable_cuentacontable')->references('id')->on('cuentacontable')->onDelete('restrict')->onUpdate('restrict');
            $table->unsignedBigInteger('creousuario_id');
            $table->foreign('creousuario_id', 'fk_impuesto_cuentacontable_usuario')->references('id')->on('usuario')->onDelete('cascade')->onUpdate('cascade');
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
        Schema::dropIfExists('impuesto_cuentacontable');
    }
}
