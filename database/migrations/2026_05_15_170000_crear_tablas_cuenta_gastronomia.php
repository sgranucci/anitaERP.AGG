<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cuenta_gastronomia', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('tipo', 10)->comment('mesa | cuenta');
            $table->unsignedBigInteger('empresa_id');
            $table->foreign('empresa_id', 'fk_cuenta_gastro_empresa')
                ->references('id')->on('empresa')
                ->onDelete('restrict')
                ->onUpdate('restrict');
            $table->unsignedBigInteger('mesa_gastronomia_id')->nullable();
            $table->foreign('mesa_gastronomia_id', 'fk_cuenta_gastro_mesa')
                ->references('id')->on('mesa_gastronomia')
                ->onDelete('restrict')
                ->onUpdate('restrict');
            $table->unsignedBigInteger('mozo_gastronomia_id')->nullable();
            $table->foreign('mozo_gastronomia_id', 'fk_cuenta_gastro_mozo')
                ->references('id')->on('mozo_gastronomia')
                ->onDelete('set null')
                ->onUpdate('restrict');
            $table->unsignedSmallInteger('cubiertos')->default(0);
            $table->string('estado', 20)->default('abierta')->index();
            /** PC que creó cuenta libre; mesas comparten ubicación vía configuración PV */
            $table->string('identificador_pc', 100)->nullable()->index();
            $table->unsignedBigInteger('cliente_id')->nullable();
            $table->foreign('cliente_id', 'fk_cuenta_gastro_cliente')
                ->references('id')->on('cliente')
                ->onDelete('set null')
                ->onUpdate('restrict');
            $table->unsignedBigInteger('descuento_gastronomia_id')->nullable();
            $table->foreign('descuento_gastronomia_id', 'fk_cuenta_gastro_desc')
                ->references('id')->on('descuento_gastronomia')
                ->onDelete('set null')
                ->onUpdate('restrict');
            $table->unsignedBigInteger('configuracion_puntoventa_gastronomia_id')->nullable();
            $table->foreign('configuracion_puntoventa_gastronomia_id', 'fk_cuenta_gastro_cfg_pv')
                ->references('id')->on('configuracion_puntoventa_gastronomia')
                ->onDelete('set null')
                ->onUpdate('restrict');
            $table->unsignedBigInteger('venta_id')->nullable();
            $table->foreign('venta_id', 'fk_cuenta_gastro_venta')
                ->references('id')->on('venta')
                ->onDelete('set null')
                ->onUpdate('restrict');
            $table->timestamps();
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_spanish_ci';
        });

        Schema::create('cuenta_gastronomia_linea', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('cuenta_gastronomia_id');
            $table->foreign('cuenta_gastronomia_id', 'fk_linea_cuenta_gastro')
                ->references('id')->on('cuenta_gastronomia')
                ->onDelete('cascade')
                ->onUpdate('restrict');
            $table->unsignedBigInteger('articulo_id');
            $table->foreign('articulo_id', 'fk_linea_gastro_art')
                ->references('id')->on('articulo')
                ->onDelete('restrict')
                ->onUpdate('restrict');
            $table->decimal('cantidad', 14, 4);
            $table->decimal('precio_unitario', 14, 4);
            $table->decimal('descuento_linea_pct', 8, 4)->default(0);
            /** Mapa orden opcional => articulo_id elegido (JSON object) */
            $table->json('opcionales_json')->nullable();
            $table->unsignedSmallInteger('numero_linea')->default(1);
            $table->timestamps();
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_spanish_ci';
            $table->index(['cuenta_gastronomia_id', 'numero_linea'], 'idx_linea_cuenta_num');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cuenta_gastronomia_linea');
        Schema::dropIfExists('cuenta_gastronomia');
    }
};
