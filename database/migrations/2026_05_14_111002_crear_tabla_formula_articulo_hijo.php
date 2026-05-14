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
        Schema::create('formula_articulo_hijo', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('formula_articulo_id');
            $table->foreign('formula_articulo_id', 'fk_formula_articulo_hijo_formula_articulo')->references('id')->on('formula_articulo')->onDelete('cascade')->onUpdate('cascade');
            $table->unsignedBigInteger('articulo_id')->nullable();
            $table->foreign('articulo_id', 'fk_formula_articulo_hijo_articulo')->references('id')->on('articulo')->onDelete('cascade')->onUpdate('cascade');
            $table->decimal('cantidad', 10, 2);
            $table->decimal('factorcosto', 10, 2);
            $table->unsignedBigInteger('formula_hija_id')->nullable();
            $table->foreign('formula_hija_id', 'fk_formula_articulo_hijo_formula_hija')->references('id')->on('formula_articulo')->onDelete('set null')->onUpdate('cascade');
            $table->boolean('esopcional')->default(false);
            $table->unsignedBigInteger('deposito_id')->nullable();
            $table->foreign('deposito_id', 'fk_formula_articulo_hijo_deposito')->references('id')->on('depmae')->onDelete('set null')->onUpdate('cascade');
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
        Schema::dropIfExists('formula_articulo_hijo');
    }
};
