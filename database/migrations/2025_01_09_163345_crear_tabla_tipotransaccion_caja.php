<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CrearTablaTipotransaccionCaja extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('tipotransaccion_caja', function (Blueprint $table) {
            $table->bigIncrements('id');
        	$table->string('nombre', 255);
            $table->string('operacion', 1);
            $table->string('abreviatura', 5);
            $table->decimal('signo',1,0);
            $table->string('estado', 1);
            $table->timestamps();
			$table->softDeletes();
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
        Schema::dropIfExists('tipotransaccion_caja');    
    }
}
