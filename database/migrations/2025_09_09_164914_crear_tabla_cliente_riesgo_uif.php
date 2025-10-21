<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CrearTablaClienteRiesgoUif extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('cliente_riesgo_uif', function (Blueprint $table) {
            $table->bigIncrements('id');
			$table->unsignedBigInteger('cliente_uif_id');
            $table->foreign('cliente_uif_id', 'fk_cliente_riesgo_uif_cliente_uif')->references('id')->on('cliente_uif')->onDelete('cascade')->onUpdate('cascade');
            $table->string('periodo', 10);
			$table->unsignedBigInteger('inusualidad_uif_id')->nullable();
            $table->foreign('inusualidad_uif_id', 'fk_cliente_riesgo_uif_inusualidad_uif')->references('id')->on('inusualidad_uif')->onDelete('restrict')->onUpdate('restrict');
            $table->string('riesgo', 10)->nullable();
            $table->unsignedBigInteger('creousuario_id');
            $table->foreign('creousuario_id', 'fk_cliente_riesgo_uif_usuario')->references('id')->on('usuario')->onDelete('restrict')->onUpdate('restrict');
            $table->softDeletes();
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
        Schema::dropIfExists('cliente_riesgo_uif');
    }
}
