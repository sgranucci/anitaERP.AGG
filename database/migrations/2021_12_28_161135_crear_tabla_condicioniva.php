<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CrearTablaCondicioniva extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('condicioniva', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('nombre',50);
			$table->string('letra',1);
			$table->string('coniva',1);
			$table->string('coniibb',1);
            $table->string('codigoexterno',50);
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
        Schema::dropIfExists('condicioniva');
    }
}
