<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CrearTablaCondicionpagocuota extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('condicionpagocuota', function (Blueprint $table) {
			$table->unsignedBigInteger('condicionpago_id');
			$table->foreign('condicionpago_id', 'fk_condicionpagocuota_condicionpago')->references('id')->on('condicionpago')->onDelete('cascade');
    		$table->integer('cuota');
    		$table->string('tipoplazo', 1);
    		$table->integer('plazo')->nullable();
            $table->date('fechavencimiento')->nullable();
			$table->float('porcentaje',5,2)->nullable();
			$table->float('interes',5,2)->nullable();
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
        Schema::dropIfExists('condicionpagocuota');
    }
}
