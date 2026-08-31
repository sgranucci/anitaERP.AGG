<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Maestro de abonos/contratos de cliente (Ventas) + períodos facturados + valores de tags.
 * Catálogo global de tags y valores tipados por línea de emisión.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('concepto_venta_tag_catalogo', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('clave', 40);
            $table->string('etiqueta', 80);
            $table->string('tipo', 20)->default('texto');
            $table->boolean('es_sistema')->default(false);
            $table->unsignedSmallInteger('largo_max')->nullable();
            $table->string('opciones', 255)->nullable();
            $table->timestamps();

            $table->unique('clave', 'uk_concepto_venta_tag_catalogo_clave');
        });

        Schema::table('concepto_venta_tag', function (Blueprint $table) {
            $table->string('origen', 20)->default('pedible')->after('tipo');
            $table->string('opciones', 255)->nullable()->after('largo_max');
        });

        Schema::create('contrato_venta', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('codigo', 20);
            $table->unsignedBigInteger('empresa_id');
            $table->unsignedBigInteger('cliente_id');
            $table->unsignedBigInteger('concepto_venta_id');
            $table->string('estado', 20)->default('activo');
            $table->date('vigencia_desde');
            $table->date('vigencia_hasta')->nullable();
            $table->string('periodicidad', 20)->default('mensual');
            $table->unsignedTinyInteger('dia_facturacion')->default(1);
            $table->decimal('precio', 18, 4)->nullable();
            $table->unsignedBigInteger('moneda_id')->nullable();
            $table->unsignedBigInteger('condicionventa_id')->nullable();
            $table->string('observacion', 255)->nullable();
            $table->unsignedBigInteger('creousuario_id')->nullable();
            $table->timestamps();

            $table->unique(['empresa_id', 'codigo'], 'uk_contrato_venta_empresa_codigo');
            $table->index(['cliente_id', 'estado'], 'idx_contrato_venta_cliente_estado');
            $table->index(['concepto_venta_id', 'estado'], 'idx_contrato_venta_concepto_estado');
            $table->index(['vigencia_desde', 'vigencia_hasta'], 'idx_contrato_venta_vigencia');

            $table->foreign('empresa_id', 'fk_contrato_venta_empresa')
                ->references('id')->on('empresa')->onDelete('restrict')->onUpdate('cascade');
            $table->foreign('cliente_id', 'fk_contrato_venta_cliente')
                ->references('id')->on('cliente')->onDelete('restrict')->onUpdate('cascade');
            $table->foreign('concepto_venta_id', 'fk_contrato_venta_concepto')
                ->references('id')->on('concepto_venta')->onDelete('restrict')->onUpdate('cascade');
            $table->foreign('moneda_id', 'fk_contrato_venta_moneda')
                ->references('id')->on('moneda')->onDelete('restrict')->onUpdate('cascade');
            $table->foreign('condicionventa_id', 'fk_contrato_venta_condicionventa')
                ->references('id')->on('condicionventa')->onDelete('restrict')->onUpdate('cascade');
            $table->foreign('creousuario_id', 'fk_contrato_venta_usuario')
                ->references('id')->on('usuario')->onDelete('restrict')->onUpdate('cascade');
        });

        Schema::create('contrato_venta_dato', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('contrato_venta_id');
            $table->string('clave', 40);
            $table->string('valor', 255)->nullable();
            $table->timestamps();

            $table->unique(['contrato_venta_id', 'clave'], 'uk_contrato_venta_dato_clave');
            $table->foreign('contrato_venta_id', 'fk_contrato_venta_dato_contrato')
                ->references('id')->on('contrato_venta')
                ->onDelete('cascade')->onUpdate('cascade');
        });

        Schema::create('contrato_venta_periodo', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('contrato_venta_id');
            $table->date('periodo_desde');
            $table->date('periodo_hasta');
            $table->string('estado', 20)->default('pendiente');
            $table->unsignedBigInteger('venta_id')->nullable();
            $table->unsignedBigInteger('venta_emision_id')->nullable();
            $table->timestamps();

            $table->unique(
                ['contrato_venta_id', 'periodo_desde', 'periodo_hasta'],
                'uk_contrato_venta_periodo'
            );
            $table->index(['estado', 'periodo_desde'], 'idx_contrato_venta_periodo_estado');

            $table->foreign('contrato_venta_id', 'fk_contrato_venta_periodo_contrato')
                ->references('id')->on('contrato_venta')
                ->onDelete('cascade')->onUpdate('cascade');
            $table->foreign('venta_id', 'fk_contrato_venta_periodo_venta')
                ->references('id')->on('venta')
                ->onDelete('restrict')->onUpdate('cascade');
            $table->foreign('venta_emision_id', 'fk_contrato_venta_periodo_emision')
                ->references('id')->on('venta_emision')
                ->onDelete('restrict')->onUpdate('cascade');
        });

        Schema::create('venta_emision_tag_valor', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('venta_emision_id');
            $table->string('clave', 40);
            $table->string('valor', 255)->nullable();
            $table->timestamps();

            $table->unique(['venta_emision_id', 'clave'], 'uk_venta_emision_tag_valor');
            $table->foreign('venta_emision_id', 'fk_venta_emision_tag_valor_emision')
                ->references('id')->on('venta_emision')
                ->onDelete('cascade')->onUpdate('cascade');
        });

        Schema::table('venta_emision', function (Blueprint $table) {
            $table->unsignedBigInteger('contrato_venta_id')->nullable()->after('concepto_venta_id');
            $table->foreign('contrato_venta_id', 'fk_venta_emision_contrato_venta')
                ->references('id')->on('contrato_venta')
                ->onDelete('restrict')->onUpdate('cascade');
        });
    }

    public function down(): void
    {
        Schema::table('venta_emision', function (Blueprint $table) {
            $table->dropForeign('fk_venta_emision_contrato_venta');
            $table->dropColumn('contrato_venta_id');
        });

        Schema::dropIfExists('venta_emision_tag_valor');
        Schema::dropIfExists('contrato_venta_periodo');
        Schema::dropIfExists('contrato_venta_dato');
        Schema::dropIfExists('contrato_venta');

        Schema::table('concepto_venta_tag', function (Blueprint $table) {
            $table->dropColumn(['origen', 'opciones']);
        });

        Schema::dropIfExists('concepto_venta_tag_catalogo');
    }
};
