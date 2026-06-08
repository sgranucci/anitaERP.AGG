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
        Schema::create('recepcion_proveedor_estado', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('recepcion_proveedor_id');
            $table->foreign('recepcion_proveedor_id', 'fk_recepcion_proveedor_estado_recepcion_proveedor')->references('id')->on('recepcion_proveedor')->onDelete('cascade');
            $table->string('estado', 50);
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
        Schema::dropIfExists('recepcion_proveedor_estado');
    }
};
