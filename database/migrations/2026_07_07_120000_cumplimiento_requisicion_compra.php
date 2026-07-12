<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cumplimiento de requisiciones de compra.
 *
 * Documento que registra la entrega de ítems de una requisición de compra generando
 * una transferencia de mercadería (origen → destino elegidos en el formulario).
 * Análogo a cumplimiento_requisicion_sala, sin NPU / UID / técnico de laboratorio.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cumplimiento_requisicion_compra', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('numero')->index();
            $table->dateTime('fecha');
            $table->unsignedBigInteger('usuario_id');
            $table->unsignedBigInteger('empresa_id')->nullable();
            $table->text('leyenda')->nullable();
            $table->char('estado', 1)->default('A')->comment('A=activo, R=revertido');
            $table->unsignedBigInteger('revertido_por_id')->nullable();
            $table->dateTime('revertido_en')->nullable();
            $table->text('observacion_reversion')->nullable();
            $table->timestamps();

            $table->foreign('usuario_id', 'fk_crc_cab_usuario')->references('id')->on('usuario');
            $table->foreign('empresa_id', 'fk_crc_cab_empresa')->references('id')->on('empresa');
            $table->foreign('revertido_por_id', 'fk_crc_cab_revertidopor')->references('id')->on('usuario');
        });

        Schema::create('cumplimiento_requisicion_compra_articulo', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('cumplimiento_requisicion_compra_id');
            $table->unsignedBigInteger('requisicion_id');
            $table->unsignedBigInteger('requisicion_articulo_id');
            $table->unsignedBigInteger('articulo_id')->nullable();
            $table->decimal('cantidad_entrega', 18, 4)->default(0);
            $table->decimal('cantidad_pendiente_antes', 18, 4)->default(0);
            $table->decimal('cantidadentregada_antes', 18, 4)->default(0);
            $table->unsignedBigInteger('deposito_origen_id')->nullable();
            $table->unsignedBigInteger('deposito_destino_id')->nullable();
            $table->decimal('precio', 22, 4)->nullable();
            $table->unsignedBigInteger('moneda_id')->nullable();
            $table->unsignedBigInteger('centrocostodestino_id')->nullable();
            $table->text('detalle')->nullable();
            $table->string('estado_requisicion_antes', 50)->nullable();
            $table->timestamps();

            $table->foreign('cumplimiento_requisicion_compra_id', 'fk_crca_cab')
                ->references('id')->on('cumplimiento_requisicion_compra')->cascadeOnDelete();
            $table->foreign('requisicion_id', 'fk_crca_req')
                ->references('id')->on('requisicion');
            $table->foreign('requisicion_articulo_id', 'fk_crca_reqart')
                ->references('id')->on('requisicion_articulo');
            $table->foreign('articulo_id', 'fk_crca_art')
                ->references('id')->on('articulo');
            $table->foreign('deposito_origen_id', 'fk_crca_deporig')
                ->references('id')->on('depmae');
            $table->foreign('deposito_destino_id', 'fk_crca_depdest')
                ->references('id')->on('depmae');
        });

        Schema::create('cumplimiento_requisicion_compra_transferencia', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('cumplimiento_requisicion_compra_id');
            $table->unsignedBigInteger('transferencia_mercaderia_id');
            $table->timestamps();

            $table->unique(['cumplimiento_requisicion_compra_id', 'transferencia_mercaderia_id'], 'uq_crct_tm');
            $table->foreign('cumplimiento_requisicion_compra_id', 'fk_crct_cab')
                ->references('id')->on('cumplimiento_requisicion_compra')->cascadeOnDelete();
            $table->foreign('transferencia_mercaderia_id', 'fk_crct_tm')
                ->references('id')->on('transferencia_mercaderia');
        });

        // Cantidad ya entregada por cumplimientos de compra (fuente de verdad del pendiente de este módulo).
        if (! Schema::hasColumn('requisicion_articulo', 'cantidadentregada')) {
            Schema::table('requisicion_articulo', function (Blueprint $table) {
                $table->double('cantidadentregada')->default(0)->after('cantidad');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('cumplimiento_requisicion_compra_transferencia');
        Schema::dropIfExists('cumplimiento_requisicion_compra_articulo');
        Schema::dropIfExists('cumplimiento_requisicion_compra');

        if (Schema::hasColumn('requisicion_articulo', 'cantidadentregada')) {
            Schema::table('requisicion_articulo', function (Blueprint $table) {
                $table->dropColumn('cantidadentregada');
            });
        }
    }
};
