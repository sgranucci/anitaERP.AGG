<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('maquinavending', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('empresa_id');
            $table->string('nombre', 255);
            $table->unsignedBigInteger('puntoventa_id');
            $table->unsignedBigInteger('ubicacion_id');
            $table->unsignedBigInteger('deposito_id');
            $table->string('codigo_afip', 20)->nullable();
            $table->string('numero_serie', 50)->nullable();
            $table->timestamps();
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_spanish_ci';

            $table->foreign('empresa_id', 'fk_maquinavending_empresa')
                ->references('id')->on('empresa')
                ->onDelete('restrict')->onUpdate('restrict');

            $table->foreign('puntoventa_id', 'fk_maquinavending_puntoventa')
                ->references('id')->on('puntoventa')
                ->onDelete('restrict')->onUpdate('restrict');

            $table->foreign('ubicacion_id', 'fk_maquinavending_ubicacion')
                ->references('id')->on('ubicaciones_gastronomia')
                ->onDelete('restrict')->onUpdate('restrict');

            $table->foreign('deposito_id', 'fk_maquinavending_deposito')
                ->references('id')->on('depmae')
                ->onDelete('restrict')->onUpdate('restrict');

            $table->index(['empresa_id', 'nombre'], 'idx_maquinavending_empresa_nombre');
        });

        Schema::create('maquinavending_articulo', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('maquinavending_id');
            $table->unsignedInteger('numero_rulo');
            $table->unsignedBigInteger('articulo_id');
            $table->timestamps();
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_spanish_ci';

            $table->foreign('maquinavending_id', 'fk_maquinavending_articulo_maquina')
                ->references('id')->on('maquinavending')
                ->onDelete('cascade')->onUpdate('restrict');

            $table->foreign('articulo_id', 'fk_maquinavending_articulo_articulo')
                ->references('id')->on('articulo')
                ->onDelete('restrict')->onUpdate('restrict');

            $table->unique(['maquinavending_id', 'numero_rulo'], 'uq_maquinavending_articulo_rulo');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('maquinavending_articulo');
        Schema::dropIfExists('maquinavending');
    }
};
