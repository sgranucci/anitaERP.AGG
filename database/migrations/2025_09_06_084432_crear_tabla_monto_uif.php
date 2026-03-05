<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CrearTablaMontoUif extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('monto_uif', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->decimal('desdemonto',22,4);
            $table->decimal('hastamonto',22,4);
            $table->string('riesgo', 50);
            $table->unsignedBigInteger('puntaje');
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
        Schema::dropIfExists('monto_uif');
    }
}
