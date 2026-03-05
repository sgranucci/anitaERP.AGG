<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CrearTablaLote extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('lote', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('numerodespacho',255);
			$table->unsignedBigInteger('pais_id');
			$table->date('fechaingreso');
            $table->foreign('pais_id', 'fk_lote_pais')->references('id')->on('pais')->onDelete('restrict')->onUpdate('restrict');
			$table->unsignedBigInteger('usuario_id')->nullable();
            $table->foreign('usuario_id', 'fk_lote_usuario')->references('id')->on('usuario')->onDelete('set null')->onUpdate('set null');
            $table->timestamps();
			$table->softDeletes();
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
        Schema::dropIfExists('lote');
    }
}
