<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CobranzaArchivo extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('cobranza_archivo', function (Blueprint $table) {
            $table->bigIncrements('id');
			$table->unsignedBigInteger('cobranza_id');
            $table->foreign('cobranza_id', 'fk_cobranza_archivo_cobranza')->references('id')->on('cobranza')->onDelete('cascade')->onUpdate('cascade');
            $table->string('nombrearchivo',255);
            $table->softDeletes();
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
        Schema::dropIfExists('cobranza_archivo');
    }
}
