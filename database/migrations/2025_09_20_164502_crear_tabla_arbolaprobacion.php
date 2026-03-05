<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CrearTablaArbolaprobacion extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('arbolaprobacion', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('nombre', 255);
            $table->string('tipoarbol', 50);
            $table->unsignedBigInteger('empresa_id');
            $table->foreign('empresa_id', 'fk_arbolaprobacion_empresa')->references('id')->on('empresa')->onDelete('restrict')->onUpdate('restrict');
            $table->string('recordatorio', 1);
            $table->unsignedBigInteger('diasinrespuesta');
            $table->unsignedBigInteger('diavencimientorecordatorio');
            $table->string('estado', 50);
            $table->softDeletes();
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
        Schema::dropIfExists('arbolaprobacion');
    }
}
