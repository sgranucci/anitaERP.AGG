<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CrearTablaEstadoChequeBanco extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('estadocheque_banco', function (Blueprint $table) {
            $table->bigIncrements('id');
        	$table->string('nombre', 255);
            $table->string('abreviatura', 5);
            $table->string('codigoexterno', 50);
            $table->unsignedBigInteger('banco_id');
            $table->foreign('banco_id', 'fk_estadocheque_banco_banco')->references('id')->on('banco')->onDelete('cascade')->onUpdate('cascade');
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
        Schema::dropIfExists('estadocheque_banco');  
    }
}
