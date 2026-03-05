<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CrearTablaTipotransaccionCompra extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('tipotransaccion_compra', function (Blueprint $table) {
            $table->bigIncrements('id');
        	$table->string('nombre', 255);
            $table->string('operacion', 1);
            $table->string('abreviatura', 5);
            $table->string('codigoafip', 50);
            $table->decimal('signo',1,0);
            $table->string('subdiario', 1);
            $table->string('asientocontable', 1);
            $table->string('retieneiva', 1);
            $table->string('retieneganancia', 1);
            $table->string('retieneIIBB', 1);
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
        Schema::dropIfExists('tipotransaccion_compra');
    }
}
