<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CrearTablaRendicionreceptivo extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('rendicionreceptivo', function (Blueprint $table) {
            $table->id();
            $table->date('fecha');
            $table->unsignedBigInteger('empresa_id');
            $table->foreign('empresa_id', 'fk_rendicionreceptivo_empresa')->references('id')->on('empresa')->onDelete('restrict')->onUpdate('restrict');
            $table->unsignedBigInteger('caja_id')->nullable();
            $table->foreign('caja_id', 'fk_rendicionreceptivo_caja')->references('id')->on('caja')->onDelete('set null')->onUpdate('set null');
            $table->unsignedBigInteger('numerotalonario');
            $table->unsignedBigInteger('guia_id');
            $table->foreign('guia_id', 'fk_rendicionreceptivo_guia')->references('id')->on('guia')->onDelete('restrict')->onUpdate('restrict');
            $table->unsignedBigInteger('movil_id');
            $table->foreign('movil_id', 'fk_rendicionreceptivo_movil')->references('id')->on('movil')->onDelete('restrict')->onUpdate('restrict');
            $table->unsignedBigInteger('ordenservicio_id');
            $table->unsignedBigInteger('desdeKm');
            $table->unsignedBigInteger('hastaKm');
            $table->string('observacion', 255)->nullable();
            $table->unsignedBigInteger('usuario_id')->nullable();
            $table->foreign('usuario_id', 'fk_rendicionreceptivo_usuario')->references('id')->on('usuario')->onDelete('set null')->onUpdate('set null');
            $table->softDeletes();
            $table->timestamps();
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_spanish_ci';
            $table->index(['empresa_id', 'numerotalonario']);
        });    
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('rendicionreceptivo');
    }
}
