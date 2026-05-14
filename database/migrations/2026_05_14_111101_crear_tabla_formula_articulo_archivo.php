<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('formula_articulo_archivo', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('formula_articulo_id');
            $table->foreign('formula_articulo_id', 'fk_formula_articulo_archivo_formula')->references('id')->on('formula_articulo')->onDelete('cascade')->onUpdate('cascade');
            $table->string('nombrearchivo', 255);
            $table->timestamps();
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_spanish_ci';
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('formula_articulo_archivo');
    }
};
