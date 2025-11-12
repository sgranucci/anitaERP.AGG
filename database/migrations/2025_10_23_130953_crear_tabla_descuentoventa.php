<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CrearTablaDescuentoventa extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('descuentoventa', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('nombre',255);
            $table->string('tipodescuento', 50);
            $table->decimal('porcentajedescuento', 5, 4)->nullable();
            $table->decimal('montodescuento', 22, 4)->nullable();
            $table->decimal('cantidadventa', 22, 4)->nullable();
            $table->decimal('cantidaddescuento', 22, 4)->nullable();
            $table->string('estado',50);
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
        Schema::dropIfExists('descuentoventa');
    }
}
