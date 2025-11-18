<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CrearTablaPadronIibbTasa extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('padron_iibb_tasa', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('padron_iibb_id');
            $table->foreign('padron_iibb_id', 'fk_padron_tasa_iibb_padron_iibb')->references('id')->on('padron_iibb')->onDelete('cascade')->onUpdate('cascade');
            $table->unsignedBigInteger('provincia_id')->nullable();
            $table->foreign('provincia_id', 'fk_padron_tasa_iibb_provincia')->references('id')->on('provincia')->onDelete('set null')->onUpdate('set null')->nullable();
            $table->date('desdefecha')->nullable();
            $table->date('hastafecha')->nullable();
            $table->float('tasapercepcion')->nullable();
            $table->float('tasaretencion')->nullable();
            $table->float('tasapercepciondiferencial')->nullable();
            $table->float('tasaretenciondiferencial')->nullable();
            $table->float('coeficiente')->nullable();
            $table->string('riesgofiscal', 10)->nullable(); // Salta
            $table->string('tipocontribuyente', 10)->nullable();
            $table->string('excluido', 10)->nullable(); // Tucuman
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
        Schema::dropIfExists('padron_iibb_tasa');
    }
}
