<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * COM asignada a la precarga (factura) del legajo, para que CxP cargue con la recepción ya vinculada.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('precarga_comprobante_proveedor_recepcion')) {
            return;
        }

        Schema::create('precarga_comprobante_proveedor_recepcion', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('precarga_comprobante_proveedor_id');
            $table->unsignedBigInteger('recepcion_proveedor_id');
            $table->unsignedInteger('orden')->default(1);
            $table->timestamps();

            $table->unique(
                ['precarga_comprobante_proveedor_id', 'recepcion_proveedor_id'],
                'uk_precarga_comprobante_recepcion'
            );
            $table->foreign('precarga_comprobante_proveedor_id', 'fk_precarga_cp_recepcion_precarga')
                ->references('id')->on('precarga_comprobante_proveedor')->cascadeOnDelete();
            $table->foreign('recepcion_proveedor_id', 'fk_precarga_cp_recepcion_com')
                ->references('id')->on('recepcion_proveedor')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('precarga_comprobante_proveedor_recepcion');
    }
};
