<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CrearTablaCapexArchivo extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('capex_archivo', function (Blueprint $table) {
            $table->bigIncrements('id');
			$table->unsignedBigInteger('capex_id');
            $table->foreign('capex_id', 'fk_capex_archivo_capex')->references('id')->on('capex')->onDelete('cascade')->onUpdate('cascade');
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
        Schema::dropIfExists('capex_archivo');
    }
}
