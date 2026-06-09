<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lista_precio_estacionamiento', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('empresa_id');
            $table->foreign('empresa_id', 'fk_lista_precio_estacionamiento_empresa')
                ->references('id')->on('empresa')->onDelete('restrict')->onUpdate('restrict');
            $table->unsignedBigInteger('categoria_automovil_id');
            $table->foreign('categoria_automovil_id', 'fk_lista_precio_estacionamiento_categoria')
                ->references('id')->on('categoria_automovil_estacionamiento')->onDelete('restrict')->onUpdate('restrict');
            $table->date('fecha_vigencia');
            $table->unsignedBigInteger('moneda_id');
            $table->foreign('moneda_id', 'fk_lista_precio_estacionamiento_moneda')
                ->references('id')->on('moneda')->onDelete('restrict')->onUpdate('restrict');
            $table->unsignedBigInteger('creousuario_id');
            $table->foreign('creousuario_id', 'fk_lista_precio_estacionamiento_usuario')
                ->references('id')->on('usuario')->onDelete('restrict')->onUpdate('restrict');
            $table->timestamps();
            $table->unique(
                ['empresa_id', 'categoria_automovil_id', 'fecha_vigencia'],
                'uq_lista_precio_estacionamiento_empresa_cat_fecha'
            );
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_spanish_ci';
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lista_precio_estacionamiento');
    }
};
