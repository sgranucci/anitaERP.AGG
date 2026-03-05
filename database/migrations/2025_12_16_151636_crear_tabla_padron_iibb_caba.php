<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CrearTablaPadronIibbCaba extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('padron_iibb_caba', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('cuit', 50);
            $table->string('nombre', 255);
            $table->date('desdefecha')->nullable();
            $table->date('hastafecha')->nullable();
            $table->float('tasapercepcion')->nullable();
            $table->float('tasaretencion')->nullable();
            $table->string('tipocontribuyente', 10)->nullable();
            $table->timestamps();
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_spanish_ci';
            $table->index(['cuit']);
        }); 
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('padron_iibb_caba');
    }
}
