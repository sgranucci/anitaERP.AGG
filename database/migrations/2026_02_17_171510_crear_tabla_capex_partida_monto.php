<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CrearTablaCapexPartidaMonto extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('capex_partida_monto', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('capex_partida_id');
            $table->foreign('capex_partida_id', 'fk_capex_partida_monto_capex_partida')->references('id')->on('capex_partida')->onDelete('cascade')->onUpdate('cascade');
            $table->unsignedBigInteger('capex_id');
            $table->foreign('capex_id', 'fk_capex_partida_monto_capex')->references('id')->on('capex')->onDelete('cascade')->onUpdate('cascade');
            $table->string('periodo', 7);
            $table->decimal('monto', 22, 4);
            $table->unsignedBigInteger('creousuario_id');
            $table->foreign('creousuario_id', 'fk_capex_partida_monto_usuario')->references('id')->on('usuario')->onDelete('restrict')->onUpdate('restrict');
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
        Schema::dropIfExists('capex_partida_monto');
    }
}
