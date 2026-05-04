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
        Schema::create('requisicion_estado', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('requisicion_id');
            $table->foreign('requisicion_id', 'fk_requisicion_estado_requisicion')->references('id')->on('requisicion')->onDelete('cascade')->onUpdate('cascade');
            $table->date('fecha');
            $table->string('estado', 50);
            $table->unsignedBigInteger('usuario_id');
            $table->foreign('usuario_id', 'fk_requisicion_estado_usuario')->references('id')->on('usuario')->onDelete('restrict')->onUpdate('restrict');
            $table->text('observacion');
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
        Schema::dropIfExists('requisicion_estado');
    }
};
