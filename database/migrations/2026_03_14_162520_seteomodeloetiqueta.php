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
        Schema::create('seteomodeloetiqueta', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('usuario_id');
            $table->foreign('usuario_id', 'fk_seteomodeloetiqueta_usuario')->references('id')->on('usuario')->onDelete('cascade')->onUpdate('cascade');
            $table->unsignedBigInteger('modeloetiqueta_id');
            $table->foreign('modeloetiqueta_id', 'fk_seteomodeloetiqueta_modeloetiqueta')->references('id')->on('modeloetiqueta')->onDelete('cascade')->onUpdate('cascade');
            $table->string('programa',255);
            $table->timestamps();
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_spanish_ci';
        });     }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('seteomodeloetiqueta');
    }
};
