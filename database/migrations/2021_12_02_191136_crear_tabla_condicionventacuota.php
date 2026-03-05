<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CrearTablaCondicionventaCuota extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('condicionventacuota', function (Blueprint $table) {
			$table->unsignedBigInteger('condicionventa_id');
			$table->foreign('condicionventa_id', 'fk_condicionventa_cuota_condicionventa')->references('id')->on('condicionventa')->onDelete('cascade');
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
        Schema::dropIfExists('condicionventacuota');
    }
}
