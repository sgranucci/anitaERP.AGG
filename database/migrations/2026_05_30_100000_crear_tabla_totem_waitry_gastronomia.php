<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('totem_waitry_gastronomia', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('empresa_id');
            $table->unsignedBigInteger('ubicacion_id');
            $table->text('detalle')->nullable();
            $table->timestamps();
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_spanish_ci';

            $table->foreign('empresa_id', 'fk_totem_waitry_gastronomia_empresa')
                ->references('id')->on('empresa')
                ->onDelete('restrict')->onUpdate('restrict');

            $table->foreign('ubicacion_id', 'fk_totem_waitry_gastronomia_ubicacion')
                ->references('id')->on('ubicaciones_gastronomia')
                ->onDelete('restrict')->onUpdate('restrict');

            $table->unique(['empresa_id', 'ubicacion_id'], 'uq_totem_waitry_gastronomia_empresa_ubicacion');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('totem_waitry_gastronomia');
    }
};
