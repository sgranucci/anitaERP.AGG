<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CrearTablaCapex extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('capex', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('empresa_id');
            $table->foreign('empresa_id', 'fk_capex_empresa')->references('id')->on('empresa')->onDelete('restrict')->onUpdate('restrict');
            $table->unsignedBigInteger('centrocosto_id');
            $table->foreign('centrocosto_id', 'fk_capex_centrocosto')->references('id')->on('centrocosto')->onDelete('restrict')->onUpdate('restrict');
			$table->unsignedBigInteger('presupuesto_id');
            $table->foreign('presupuesto_id', 'fk_capex_presupuesto')->references('id')->on('presupuesto')->onDelete('cascade')->onUpdate('cascade');
            $table->string('nombre', 255);
            $table->text('detalle')->nullable();
            $table->string('codigoproyecto', 255);
            $table->string('estado', 50);
            $table->string('codigo', 50);
            $table->unsignedBigInteger('creousuario_id');
            $table->foreign('creousuario_id', 'fk_capex_usuario')->references('id')->on('usuario')->onDelete('restrict')->onUpdate('restrict');
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
        Schema::dropIfExists('capex');
    }
}
