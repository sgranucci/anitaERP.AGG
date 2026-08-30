<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Matriz de cuentas (tipo + vigencia + CC) y precios con vigencia.
 * Filas actuales (sin tipo ni fechas) siguen siendo el default: Bierzo no cambia.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('concepto_venta_cuentacontable', function (Blueprint $table) {
            // El unique (concepto, empresa) es el índice del FK a concepto_venta.
            $table->dropForeign('fk_concepto_venta_cc_concepto');
            $table->dropUnique('uk_concepto_venta_cc_empresa');
            $table->index(['concepto_venta_id', 'empresa_id'], 'idx_concepto_venta_cc_lookup');
            $table->foreign('concepto_venta_id', 'fk_concepto_venta_cc_concepto')
                ->references('id')->on('concepto_venta')
                ->onDelete('cascade')->onUpdate('cascade');
            $table->unsignedBigInteger('tipotransaccion_id')->nullable()->after('empresa_id');
            $table->date('vigencia_desde')->nullable()->after('cuentacontable_id');
            $table->date('vigencia_hasta')->nullable()->after('vigencia_desde');
            $table->unsignedBigInteger('centrocosto_id')->nullable()->after('vigencia_hasta');
            $table->foreign('tipotransaccion_id', 'fk_concepto_venta_cc_tipo')
                ->references('id')->on('tipotransaccion')
                ->onDelete('restrict')->onUpdate('cascade');
            $table->foreign('centrocosto_id', 'fk_concepto_venta_cc_centrocosto')
                ->references('id')->on('centrocosto')
                ->onDelete('restrict')->onUpdate('cascade');
        });

        Schema::create('concepto_venta_precio', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('concepto_venta_id');
            $table->decimal('precio', 15, 4);
            $table->date('vigencia_desde')->nullable();
            $table->date('vigencia_hasta')->nullable();
            $table->unsignedBigInteger('creousuario_id');
            $table->timestamps();

            $table->index('concepto_venta_id', 'idx_concepto_venta_precio_concepto');
            $table->foreign('concepto_venta_id', 'fk_concepto_venta_precio_concepto')
                ->references('id')->on('concepto_venta')
                ->onDelete('cascade')->onUpdate('cascade');
            $table->foreign('creousuario_id', 'fk_concepto_venta_precio_usuario')
                ->references('id')->on('usuario')
                ->onDelete('restrict')->onUpdate('restrict');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('concepto_venta_precio');

        Schema::table('concepto_venta_cuentacontable', function (Blueprint $table) {
            $table->dropForeign('fk_concepto_venta_cc_tipo');
            $table->dropForeign('fk_concepto_venta_cc_centrocosto');
            $table->dropColumn(['tipotransaccion_id', 'vigencia_desde', 'vigencia_hasta', 'centrocosto_id']);
            $table->dropForeign('fk_concepto_venta_cc_concepto');
            $table->dropIndex('idx_concepto_venta_cc_lookup');
            $table->unique(['concepto_venta_id', 'empresa_id'], 'uk_concepto_venta_cc_empresa');
            $table->foreign('concepto_venta_id', 'fk_concepto_venta_cc_concepto')
                ->references('id')->on('concepto_venta')
                ->onDelete('cascade')->onUpdate('cascade');
        });
    }
};
