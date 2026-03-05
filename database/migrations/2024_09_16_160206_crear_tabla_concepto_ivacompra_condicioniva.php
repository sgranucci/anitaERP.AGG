<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CrearTablaConceptoIvacompraCondicioniva extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('concepto_ivacompra_condicioniva', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('concepto_ivacompra_id');
            $table->foreign('concepto_ivacompra_id', 'fk_concepto_ivacompra_condicioniva_concepto_ivacompra')->references('id')->on('concepto_ivacompra')->onDelete('cascade')->onUpdate('cascade');
            $table->unsignedBigInteger('condicioniva_id');
            $table->foreign('condicioniva_id', 'fk_concepto_ivacompra_condicioniva_condicioniva')->references('id')->on('condicioniva')->onDelete('restrict')->onUpdate('restrict');
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
        Schema::dropIfExists('concepto_ivacompra_condicioniva');
    }
}
