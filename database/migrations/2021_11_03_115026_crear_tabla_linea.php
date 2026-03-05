<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CrearTablaLinea extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
		Schema::create('linea', function (Blueprint $table) {
            $table->bigIncrements('id');
    		$table->string('nombre', 100);
    		$table->string('codigo', 50);
			$table->unsignedBigInteger('tiponumeracion_id')->nullable()->default(NULL);
            $table->foreign('tiponumeracion_id', 'fk_linea_tiponumeracion')->references('id')->on('tiponumeracion')->onDelete('restrict')->onUpdate('restrict');
    		$table->unsignedInteger('maxhorma');
			$table->unsignedBigInteger('numeracion_id')->nullable()->default(NULL);
            $table->foreign('numeracion_id', 'fk_linea_numeracion')->references('id')->on('numeracion')->onDelete('set null')->onUpdate('set null');
			$table->unsignedBigInteger('listaprecio_id')->nullable()->default(NULL);
            $table->foreign('listaprecio_id', 'fk_linea_listaprecio')->references('id')->on('listaprecio')->onDelete('set null')->onUpdate('set null');
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
        Schema::dropIfExists('linea');
    }
}
