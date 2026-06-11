<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('canje_marketing_entrega_gastronomia')) {
            return;
        }

        Schema::create('canje_marketing_entrega_gastronomia', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('venta_id');
            $table->foreign('venta_id', 'fk_cme_venta')->references('id')->on('venta')->cascadeOnDelete();
            $table->unsignedBigInteger('cuenta_gastronomia_id')->nullable();
            $table->foreign('cuenta_gastronomia_id', 'fk_cme_cuenta')->references('id')->on('cuenta_gastronomia')->nullOnDelete();
            $table->unsignedBigInteger('cliente_vip_gastronomia_id');
            $table->foreign('cliente_vip_gastronomia_id', 'fk_cme_cliente_vip')->references('id')->on('cliente_vip_gastronomia')->restrictOnDelete();
            $table->unsignedBigInteger('mozo_gastronomia_id')->nullable();
            $table->foreign('mozo_gastronomia_id', 'fk_cme_mozo')->references('id')->on('mozo_gastronomia')->nullOnDelete();
            $table->unsignedBigInteger('empresa_id');
            $table->foreign('empresa_id', 'fk_cme_empresa')->references('id')->on('empresa')->restrictOnDelete();
            $table->unsignedBigInteger('descuento_gastronomia_id')->nullable();
            $table->foreign('descuento_gastronomia_id', 'fk_cme_descuento')->references('id')->on('descuento_gastronomia')->nullOnDelete();
            $table->string('identificador_pc', 120)->nullable();
            $table->string('nrodocumento_vip', 20)->nullable();
            $table->string('apellido_vip', 40)->nullable();
            $table->string('nombre_vip', 40)->nullable();
            $table->date('fechacanje');
            $table->timestamps();

            $table->index(['empresa_id', 'fechacanje'], 'idx_cme_empresa_fecha');
            $table->index(['cliente_vip_gastronomia_id', 'fechacanje'], 'idx_cme_vip_fecha');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('canje_marketing_entrega_gastronomia');
    }
};
