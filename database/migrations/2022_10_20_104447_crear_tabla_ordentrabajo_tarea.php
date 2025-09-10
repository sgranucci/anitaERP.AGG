<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CrearTablaOrdentrabajoTarea extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
		Schema::create('ordentrabajo_tarea', function (Blueprint $table) {
            $table->bigIncrements('id');
			$table->unsignedBigInteger('ordentrabajo_id');
			$table->foreign('ordentrabajo_id', 'fk_ordentrabajo_tarea_ordentrabajo')->references('id')->on('ordentrabajo')->onDelete('cascade');
			$table->unsignedBigInteger('tarea_id');
			$table->foreign('tarea_id', 'fk_ordentrabajo_tarea_tarea')->references('id')->on('tarea')->onDelete('restrict');
			$table->unsignedBigInteger('empleado_id')->nullable();
			$table->foreign('empleado_id', 'fk_ordentrabajo_tarea_empleado')->references('id')->on('empleado')->onDelete('set null')->onUpdate('set null');
			$table->unsignedBigInteger('pedido_combinacion_id')->nullable();
			$table->foreign('pedido_combinacion_id', 'fk_ordentrabajo_tarea_pedido_combinacion')->references('id')->on('pedido_combinacion')->onDelete('set null')->onUpdate('set null');
            $table->date('desdefecha');
            $table->date('hastafecha')->nullable();
            $table->decimal('costo',20,6);
            $table->string('estado', 1);
			$table->unsignedBigInteger('usuario_id')->nullable();
            $table->foreign('usuario_id', 'fk_ordentrabajo_tarea_usuario')->references('id')->on('usuario')->onDelete('set null')->onUpdate('set null');
			$table->unsignedBigInteger('venta_id')->nullable();
            $table->foreign('venta_id', 'fk_ordentrabajo_tarea_venta')->references('id')->on('venta')->onDelete('restrict')->onUpdate('restrict');
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
        Schema::dropIfExists('ordentrabajo_tarea');
    }
}
