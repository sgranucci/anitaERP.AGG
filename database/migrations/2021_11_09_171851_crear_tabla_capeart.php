<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CrearTablaCapeart extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('capeart', function (Blueprint $table) {
            $table->bigIncrements('id');
			$table->unsignedBigInteger('articulo_id');
            $table->foreign('articulo_id', 'fk_capeart_articulo')->references('id')->on('articulo')->onDelete('restrict')->onUpdate('restrict');
			$table->unsignedBigInteger('combinacion_id');
            $table->foreign('combinacion_id', 'fk_capeart_combinacion')->references('id')->on('combinacion')->onDelete('restrict')->onUpdate('restrict');
            $table->unsignedBigInteger('material_id')->nullable();
            $table->foreign('material_id', 'fk_capeart_material')->references('id')->on('material')->onDelete('restrict')->onUpdate('restrict');
            $table->unsignedBigInteger('color_id')->nullable();
            $table->foreign('color_id', 'fk_capeart_color')->references('id')->on('color')->onDelete('restrict')->onUpdate('restrict');
            $table->string('piezas', 255);
            $table->string('tipo', 1);
            $table->string('tipocalculo', 1);
			$table->decimal('consumo1',20,6);
			$table->decimal('consumo2',20,6);
			$table->decimal('consumo3',20,6);
			$table->decimal('consumo4',20,6);
            $table->unsignedBigInteger('usuarioultcambio_id')->nullable();
            $table->foreign('usuarioultcambio_id', 'fk_capeart_usuario')->references('id')->on('usuario')->onDelete('set null')->onUpdate('set null');
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
		Schema::dropIfExists('capeart');
    }
}
