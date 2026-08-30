<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Conceptos de comprobante de ventas (mostrador): ítem ARCA + cuenta contable
 * cuando la línea no es un artículo. No aplica a POS gastronomía/estacionamiento.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('concepto_venta', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('codigo', 20);
            $table->string('nombre', 80);
            $table->string('descripcion', 255);
            $table->string('codigo_gtin', 13)->nullable();
            $table->unsignedTinyInteger('unidades_mtx')->default(1);
            $table->unsignedBigInteger('impuesto_id')->nullable();
            $table->unsignedBigInteger('unidadmedida_id')->nullable();
            $table->boolean('activo')->default(true);
            $table->unsignedInteger('codigo_anita')->nullable();
            $table->timestamps();

            $table->unique('codigo', 'uk_concepto_venta_codigo');
            $table->unique('codigo_anita', 'uk_concepto_venta_codigo_anita');
            $table->index('activo', 'idx_concepto_venta_activo');

            $table->foreign('impuesto_id', 'fk_concepto_venta_impuesto')
                ->references('id')->on('impuesto')
                ->onDelete('restrict')->onUpdate('cascade');
            $table->foreign('unidadmedida_id', 'fk_concepto_venta_unidadmedida')
                ->references('id')->on('unidadmedida')
                ->onDelete('restrict')->onUpdate('cascade');
        });

        Schema::create('concepto_venta_cuentacontable', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('concepto_venta_id');
            $table->unsignedBigInteger('empresa_id');
            $table->unsignedBigInteger('cuentacontable_id');
            $table->unsignedBigInteger('creousuario_id');
            $table->timestamps();

            $table->unique(['concepto_venta_id', 'empresa_id'], 'uk_concepto_venta_cc_empresa');

            $table->foreign('concepto_venta_id', 'fk_concepto_venta_cc_concepto')
                ->references('id')->on('concepto_venta')
                ->onDelete('cascade')->onUpdate('cascade');
            $table->foreign('empresa_id', 'fk_concepto_venta_cc_empresa')
                ->references('id')->on('empresa')
                ->onDelete('restrict')->onUpdate('restrict');
            $table->foreign('cuentacontable_id', 'fk_concepto_venta_cc_cuenta')
                ->references('id')->on('cuentacontable')
                ->onDelete('restrict')->onUpdate('restrict');
            $table->foreign('creousuario_id', 'fk_concepto_venta_cc_usuario')
                ->references('id')->on('usuario')
                ->onDelete('restrict')->onUpdate('restrict');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('concepto_venta_cuentacontable');
        Schema::dropIfExists('concepto_venta');
    }
};
