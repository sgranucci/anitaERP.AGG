<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('ubicaciones_gastronomia')) {
            return;
        }

        Schema::create('ubicaciones_gastronomia', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('nombre', 255);
            $table->unsignedBigInteger('empresa_id');
            $table->foreign('empresa_id', 'fk_ubicaciones_gastronomia_empresa')
                ->references('id')->on('empresa')
                ->onDelete('restrict')
                ->onUpdate('restrict');
            $table->timestamps();
            $table->unique(['nombre', 'empresa_id'], 'uk_ubicaciones_gastronomia_nombre_empresa');
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_spanish_ci';
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ubicaciones_gastronomia');
    }
};
