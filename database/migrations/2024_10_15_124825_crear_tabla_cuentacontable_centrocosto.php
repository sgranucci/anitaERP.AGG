<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CrearTablaCuentacontableCentrocosto extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('cuentacontable_centrocosto', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('cuentacontable_id');
            $table->foreign('cuentacontable_id', 'fk_cuentacontable_centrocosto_cuentacontable')->references('id')->on('cuentacontable')->onDelete('restrict')->onUpdate('restrict');
            $table->unsignedBigInteger('centrocosto_id');
            $table->foreign('centrocosto_id', 'fk_cuentacontable_centrocosto_centrocosto')->references('id')->on('centrocosto')->onDelete('restrict')->onUpdate('restrict');
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
        Schema::dropIfExists('cuentacontable_centrocosto');
    }
}
