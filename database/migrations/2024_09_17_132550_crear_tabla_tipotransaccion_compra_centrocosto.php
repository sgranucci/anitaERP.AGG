<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CrearTablaTipotransaccionCompraCentrocosto extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('tipotransaccion_compra_centrocosto', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('tipotransaccion_compra_id');
            $table->foreign('tipotransaccion_compra_id', 'fk_tipotransaccion_compra_centrocosto_tipotransaccion_compra')->references('id')->on('tipotransaccion_compra')->onDelete('cascade')->onUpdate('cascade');
            $table->unsignedBigInteger('centrocosto_id');
            $table->foreign('centrocosto_id', 'fk_tipotransaccion_compra_centrocosto_centrocosto')->references('id')->on('centrocosto')->onDelete('restrict')->onUpdate('restrict');
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
        Schema::dropIfExists('tipotransaccion_compra_centrocosto');
    }
}
