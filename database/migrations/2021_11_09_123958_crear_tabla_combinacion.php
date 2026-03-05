<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CrearTablaCombinacion extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('combinacion', function (Blueprint $table) {
            $table->bigIncrements('id');
			$table->unsignedBigInteger('articulo_id');
            $table->foreign('articulo_id', 'fk_combinacion_articulo')->references('id')->on('articulo')->onDelete('restrict')->onUpdate('restrict');
            $table->string('codigo', 6);
            $table->string('nombre', 100);
            $table->string('observacion', 255)->nullable();
            $table->unsignedBigInteger('forro_id')->nullable();
            $table->foreign('forro_id', 'fk_combinacion_forro')->references('id')->on('forro')->onDelete('set null')->onUpdate('set null');
            $table->unsignedBigInteger('colorforro_id')->nullable();
            $table->foreign('colorforro_id', 'fk_combinacion_colorforro_color')->references('id')->on('color')->onDelete('set null')->onUpdate('set null');
            $table->unsignedBigInteger('plvista_id')->nullable();
            $table->foreign('plvista_id', 'fk_combinacion_plvista')->references('id')->on('plvista')->onDelete('set null')->onUpdate('set null');
            $table->unsignedBigInteger('plarmado_id')->nullable();
            $table->foreign('plarmado_id', 'fk_combinacion_plarmado')->references('id')->on('plarmado')->onDelete('set null')->onUpdate('set null');
            $table->unsignedBigInteger('fondo_id')->nullable();
            $table->foreign('fondo_id', 'fk_combinacion_fondo')->references('id')->on('fondo')->onDelete('set null')->onUpdate('set null');
            $table->unsignedBigInteger('colorfondo_id')->nullable();
            $table->foreign('colorfondo_id', 'fk_combinacion_colorfondo_color')->references('id')->on('color')->onDelete('set null')->onUpdate('set null');
            $table->unsignedBigInteger('horma_id')->nullable();
            $table->foreign('horma_id', 'fk_combinacion_horma')->references('id')->on('horma')->onDelete('set null')->onUpdate('set null');
            $table->unsignedBigInteger('serigrafia_id')->nullable();
            $table->foreign('serigrafia_id', 'fk_combinacion_serigrafia')->references('id')->on('serigrafia')->onDelete('set null')->onUpdate('set null');
            $table->string('estado', 1);
			$table->decimal('plvista_16_26',20,6)->nullable;
			$table->decimal('plvista_27_33',20,6)->nullable;
			$table->decimal('plvista_34_40',20,6)->nullable;
			$table->decimal('plvista_41_45',20,6)->nullable;
            $table->unsignedBigInteger('usuarioultcambio_id')->nullable();
            $table->foreign('usuarioultcambio_id', 'fk_combinacion_usuario')->references('id')->on('usuario')->onDelete('set null')->onUpdate('set null');
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
		Schema::dropIfExists('combinacion');
    }
}
