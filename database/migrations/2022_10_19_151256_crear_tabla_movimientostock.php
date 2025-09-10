<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CrearTablaMovimientostock extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
		Schema::create('movimientostock', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->date('fecha');
            $table->date('fechajornada');
            $table->unsignedBigInteger('tipotransaccion_id');
            $table->foreign('tipotransaccion_id', 'fk_movimientostock_tipotransaccion')->references('id')->on('tipotransaccion')->onDelete('restrict')->onUpdate('restrict');
            $table->unsignedBigInteger('mventa_id')->nullable();
            $table->foreign('mventa_id', 'fk_movimientostock_mventa')->references('id')->on('mventa')->onDelete('set null')->onUpdate('set null');
            $table->string('codigo',50);
            $table->string('leyenda',2048)->nullable();
            $table->string('estado', 1);
			$table->unsignedBigInteger('usuario_id')->nullable();
            $table->foreign('usuario_id', 'fk_movimientostock_usuario')->references('id')->on('usuario')->onDelete('set null')->onUpdate('set null');
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
        Schema::dropIfExists('movimientostock');
    }
}
