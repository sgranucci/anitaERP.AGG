<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CrearTablaPresupuestoEscenario extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('presupuesto_escenario', function (Blueprint $table) {
            $table->bigIncrements('id');
			$table->unsignedBigInteger('presupuesto_id');
            $table->foreign('presupuesto_id', 'fk_presupuesto_escenario_presupuesto')->references('id')->on('presupuesto')->onDelete('cascade')->onUpdate('cascade');
            $table->string('nombre', 255);
            $table->string('tipo', 50);
            $table->string('codigo', 50);
            $table->unsignedBigInteger('creousuario_id');
            $table->foreign('creousuario_id', 'fk_presupuesto_escenario_usuario')->references('id')->on('usuario')->onDelete('restrict')->onUpdate('restrict');
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
        Schema::dropIfExists('presupuesto_escenario');
    }
}
