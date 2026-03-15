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
        Schema::create('modeloetiqueta', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('nombre', 255);
            $table->text('codigoetiqueta')->nullable();
            $table->string('estado', 50);
            $table->unsignedBigInteger('creousuario_id');
            $table->foreign('creousuario_id', 'fk_modeloetiqueta_usuario')->references('id')->on('usuario')->onDelete('restrict')->onUpdate('restrict');
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
        Schema::dropIfExists('modeloetiqueta');
    }
};
