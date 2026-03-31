<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('encuesta_pregunta', function (Blueprint $table) {
            $table->bigIncrements('id');
			$table->unsignedBigInteger('encuesta_id');
            $table->foreign('encuesta_id', 'fk_encuesta_pregunta_encuesta')->references('id')->on('encuesta')->onDelete('cascade')->onUpdate('cascade');
            $table->string('nombre',255);
            $table->unsignedInteger('desdepuntaje');
            $table->unsignedInteger('hastapuntaje');            
            $table->timestamps();
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_spanish_ci';
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('encuesta_pregunta');
    }
};
