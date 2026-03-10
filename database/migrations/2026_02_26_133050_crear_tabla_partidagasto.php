<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CrearTablaPartidagasto extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('partidagasto', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('empresa_id');
            $table->foreign('empresa_id', 'fk_partidagasto_empresa')->references('id')->on('empresa')->onDelete('restrict')->onUpdate('restrict');
			$table->unsignedBigInteger('presupuesto_id');
            $table->foreign('presupuesto_id', 'fk_partidagasto_presupuesto')->references('id')->on('presupuesto')->onDelete('cascade')->onUpdate('cascade');
			$table->unsignedBigInteger('presupuesto_escenario_id');
            $table->foreign('presupuesto_escenario_id', 'fk_partidagasto_presupuesto_escenario')->references('id')->on('presupuesto_escenario')->onDelete('cascade')->onUpdate('cascade');
            $table->unsignedBigInteger('centrocosto_id');
            $table->foreign('centrocosto_id', 'fk_partidagasto_centrocosto')->references('id')->on('centrocosto')->onDelete('restrict')->onUpdate('restrict');
            $table->unsignedBigInteger('moneda_id');
            $table->foreign('moneda_id', 'fk_partidagasto_moneda')->references('id')->on('moneda')->onDelete('restrict')->onUpdate('restrict');
			$table->unsignedBigInteger('cuentacontable_id')->nullable();
            $table->foreign('cuentacontable_id', 'fk_partidagasto_cuentacontable')->references('id')->on('cuentacontable')->onDelete('restrict')->onUpdate('restrict');
			$table->unsignedBigInteger('articulo_id')->nullable();
            $table->foreign('articulo_id', 'fk_partidagasto_articulo')->references('id')->on('articulo')->onDelete('cascade')->onUpdate('cascade');
			$table->unsignedBigInteger('proveedor_id')->nullable();
            $table->foreign('proveedor_id', 'fk_partidagasto_proveedor')->references('id')->on('proveedor')->onDelete('restrict')->onUpdate('restrict');
            $table->text('detalle')->nullable();
            $table->string('codigo', 50);
            $table->string('estado', 50);
            $table->unsignedBigInteger('creousuario_id');
            $table->foreign('creousuario_id', 'fk_partidagasto_usuario')->references('id')->on('usuario')->onDelete('restrict')->onUpdate('restrict');
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
        Schema::dropIfExists('partidagasto');
    }
}
