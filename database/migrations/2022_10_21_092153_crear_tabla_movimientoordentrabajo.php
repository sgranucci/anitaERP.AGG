<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CrearTablaMovimientoordentrabajo extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
		Schema::create('movimientoordentrabajo', function (Blueprint $table) {
            $table->bigIncrements('id');
			$table->unsignedBigInteger('ordentrabajo_id');
			$table->foreign('ordentrabajo_id', 'fk_movimientoordentrabajo_ordentrabajo')->references('id')->on('ordentrabajo')->onDelete('cascade');
			$table->unsignedBigInteger('ordentrabajo_tarea_id');
			$table->foreign('ordentrabajo_tarea_id', 'fk_movimientoordentrabajo_ordentrabajo_tarea')->references('id')->on('ordentrabajo_tarea')->onDelete('cascade');
			$table->unsignedBigInteger('tarea_id');
			$table->foreign('tarea_id', 'fk_movimientoordentrabajo_tarea')->references('id')->on('tarea')->onDelete('restrict');
			$table->unsignedBigInteger('operacion_id');
			$table->foreign('operacion_id', 'fk_movimientoordentrabajo_operacion')->references('id')->on('operacion')->onDelete('restrict');
			$table->unsignedBigInteger('empleado_id')->nullable();
			$table->foreign('empleado_id', 'fk_movimientoordentrabajo_tarea_empleado')->references('id')->on('empleado')->onDelete('set null')->onUpdate('set null');
            $table->dateTime('fecha');
            $table->string('estado', 1);
			$table->unsignedBigInteger('usuario_id')->nullable();
            $table->foreign('usuario_id', 'fk_movimientoordentrabajo_tarea_usuario')->references('id')->on('usuario')->onDelete('set null')->onUpdate('set null');
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
        Schema::dropIfExists('movimientoordentrabajo');
    }
}
