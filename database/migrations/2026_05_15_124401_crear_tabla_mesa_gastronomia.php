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
        Schema::create('mesa_gastronomia', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('nombre', 255);
            $table->unsignedBigInteger('ubicacion_id')->nullable();
            $table->foreign('ubicacion_id', 'fk_mesa_gastronomia_ubicacion')
                ->references('id')->on('ubicaciones_gastronomia')
                ->onDelete('set null')
                ->onUpdate('restrict');
            $table->string('numeromesa', 50);
            $table->string('codigo', 50)->nullable();
            $table->unsignedBigInteger('empresa_id');
            $table->foreign('empresa_id', 'fk_mesa_gastronomia_empresa')->references('id')->on('empresa')->onDelete('restrict')->onUpdate('restrict');
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
        Schema::dropIfExists('mesa_gastronomia');
    }
};
