<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CrearTablaPadronExclusionpercepcioniva extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('padron_exclusionpercepcioniva', function (Blueprint $table) {
            $table->bigIncrements('id');
        	$table->string('cuit', 50);
            $table->string('nombre', 255);
            $table->date('desdefecha');
            $table->date('hastafecha')->nullable();
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
        Schema::dropIfExists('padron_exclusionpercepcioniva');
    }
}
