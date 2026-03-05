<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CrearTablaTalonariovoucher extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('talonariovoucher', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('nombre',255);
            $table->string('serie',10);
            $table->unsignedBigInteger('origenvoucher_id');
            $table->foreign('origenvoucher_id', 'fk_talonariovoucher_origenvoucher')->references('id')->on('origenvoucher')->onDelete('restrict')->onUpdate('restrict');
            $table->unsignedBigInteger('desdenumero');
            $table->unsignedBigInteger('hastanumero');
            $table->date('fechainicio')->nullable();
            $table->date('fechacierre')->nullable();
            $table->string('estado');
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
        Schema::dropIfExists('talonariovoucher');
    }
}
