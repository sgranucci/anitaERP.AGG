<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Huecos ya revisados contra ARCA (cargado, inexistente, omitido, error).
 * Sin SoftDeletes: la fila es el recuerdo de la corrida, no un comprobante.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('facturacion_hueco_arca')) {
            return;
        }

        Schema::create('facturacion_hueco_arca', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('empresa_id');
            $table->unsignedBigInteger('puntoventa_id');
            $table->unsignedSmallInteger('codigo_afip');
            $table->unsignedInteger('numerocomprobante');
            $table->string('estado', 20);
            $table->unsignedBigInteger('venta_id')->nullable();
            $table->string('cae', 20)->nullable();
            $table->decimal('importe', 18, 2)->nullable();
            $table->date('fecha_comprobante')->nullable();
            $table->string('detalle', 500)->nullable();
            $table->timestamp('avisado_at')->nullable();
            $table->timestamps();

            $table->unique(
                ['puntoventa_id', 'codigo_afip', 'numerocomprobante'],
                'uq_facturacion_hueco_arca_serie'
            );
            $table->index('estado', 'ix_facturacion_hueco_arca_estado');
            $table->foreign('puntoventa_id', 'fk_fha_puntoventa')
                ->references('id')->on('puntoventa');
            $table->foreign('venta_id', 'fk_fha_venta')
                ->references('id')->on('venta');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('facturacion_hueco_arca');
    }
};
