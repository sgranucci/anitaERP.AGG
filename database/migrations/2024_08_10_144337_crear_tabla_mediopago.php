<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CrearTablaMediopago extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('mediopago', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('nombre',255);
            $table->unsignedBigInteger('cuentacaja_id');
            $table->foreign('cuentacaja_id', 'fk_mediopago_cuentacaja')->references('id')->on('cuentacaja')->onDelete('restrict');
			$table->unsignedBigInteger('empresa_id')->nullable();
            $table->foreign('empresa_id', 'fk_mediopago_empresa')->references('id')->on('empresa')->onDelete('set null')->onUpdate('set null');
            $table->string('codigo',10);
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
        Schema::dropIfExists('mediopago');
    }
}
