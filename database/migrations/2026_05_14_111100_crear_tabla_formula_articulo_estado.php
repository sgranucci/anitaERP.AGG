<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('formula_articulo_estado', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('formula_articulo_id');
            $table->foreign('formula_articulo_id', 'fk_formula_articulo_estado_formula')->references('id')->on('formula_articulo')->onDelete('cascade')->onUpdate('cascade');
            $table->dateTime('fecha');
            $table->string('estado', 50);
            $table->unsignedBigInteger('usuario_id');
            $table->foreign('usuario_id', 'fk_formula_articulo_estado_usuario')->references('id')->on('usuario')->onDelete('restrict')->onUpdate('restrict');
            $table->text('observacion')->nullable();
            $table->timestamps();
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_spanish_ci';
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('formula_articulo_estado');
    }
};
