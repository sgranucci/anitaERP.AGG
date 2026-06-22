<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('proveedor_cuentacorriente', function (Blueprint $table) {
            $table->id();
            $table->date('fecha');
            $table->date('fechavencimiento');
            $table->unsignedBigInteger('proveedor_id');
            $table->foreign('proveedor_id', 'fk_prov_cc_proveedor')
                ->references('id')->on('proveedor')->onDelete('restrict')->onUpdate('restrict');
            $table->decimal('total', 22, 4);
            $table->unsignedBigInteger('moneda_id');
            $table->foreign('moneda_id', 'fk_prov_cc_moneda')
                ->references('id')->on('moneda')->onDelete('restrict')->onUpdate('restrict');
            $table->decimal('cotizacion', 22, 8)->default(1);
            $table->unsignedBigInteger('empresa_id');
            $table->foreign('empresa_id', 'fk_prov_cc_empresa')
                ->references('id')->on('empresa')->onDelete('restrict')->onUpdate('restrict');
            $table->unsignedBigInteger('comprobante_proveedor_id')->nullable();
            $table->foreign('comprobante_proveedor_id', 'fk_prov_cc_comprobante')
                ->references('id')->on('comprobante_proveedor')->onDelete('cascade')->onUpdate('cascade');
            $table->unsignedBigInteger('comprobante_proveedor_cuota_id')->nullable();
            $table->foreign('comprobante_proveedor_cuota_id', 'fk_prov_cc_cuota')
                ->references('id')->on('comprobante_proveedor_cuota')->onDelete('cascade')->onUpdate('cascade');
            $table->timestamps();
            $table->softDeletes();
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_spanish_ci';

            $table->unique('comprobante_proveedor_cuota_id', 'uq_prov_cc_cuota');
        });

        Schema::create('proveedor_cuentacorriente_aplicacion', function (Blueprint $table) {
            $table->id();
            $table->date('fecha');
            $table->unsignedBigInteger('proveedor_cuentacorriente_id');
            $table->foreign('proveedor_cuentacorriente_id', 'fk_prov_cc_apl_cc')
                ->references('id')->on('proveedor_cuentacorriente')->onDelete('cascade')->onUpdate('cascade');
            $table->decimal('total', 22, 4);
            $table->unsignedBigInteger('moneda_id');
            $table->foreign('moneda_id', 'fk_prov_cc_apl_moneda')
                ->references('id')->on('moneda')->onDelete('restrict')->onUpdate('restrict');
            $table->decimal('cotizacion', 22, 8)->nullable();
            $table->unsignedBigInteger('comprobante_proveedor_aplicado_id')->nullable();
            $table->foreign('comprobante_proveedor_aplicado_id', 'fk_prov_cc_apl_comprobante')
                ->references('id')->on('comprobante_proveedor')->onDelete('cascade')->onUpdate('cascade');
            $table->string('comprobanteaplicado', 255)->nullable();
            $table->unsignedBigInteger('empresa_id');
            $table->foreign('empresa_id', 'fk_prov_cc_apl_empresa')
                ->references('id')->on('empresa')->onDelete('restrict')->onUpdate('restrict');
            $table->unsignedBigInteger('proveedor_cuentacorriente_aplicado_id')->nullable();
            $table->foreign('proveedor_cuentacorriente_aplicado_id', 'fk_prov_cc_apl_cc_aplicado')
                ->references('id')->on('proveedor_cuentacorriente')->onDelete('cascade')->onUpdate('cascade');
            $table->timestamps();
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_spanish_ci';
        });

        if (Schema::hasTable('comprobante_proveedor_cuota') && ! Schema::hasColumn('comprobante_proveedor_cuota', 'proveedor_cuentacorriente_id')) {
            Schema::table('comprobante_proveedor_cuota', function (Blueprint $table) {
                $table->unsignedBigInteger('proveedor_cuentacorriente_id')->nullable()->after('total_pagado');
                $table->foreign('proveedor_cuentacorriente_id', 'fk_cp_cuota_prov_cc')
                    ->references('id')->on('proveedor_cuentacorriente')->onDelete('set null')->onUpdate('cascade');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('comprobante_proveedor_cuota', 'proveedor_cuentacorriente_id')) {
            Schema::table('comprobante_proveedor_cuota', function (Blueprint $table) {
                $table->dropForeign('fk_cp_cuota_prov_cc');
                $table->dropColumn('proveedor_cuentacorriente_id');
            });
        }

        Schema::dropIfExists('proveedor_cuentacorriente_aplicacion');
        Schema::dropIfExists('proveedor_cuentacorriente');
    }
};
