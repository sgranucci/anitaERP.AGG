<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('ordencompra_legajo_nc_imputacion')) {
            return;
        }

        Schema::create('ordencompra_legajo_nc_imputacion', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('ordencompra_id');
            $table->unsignedBigInteger('precarga_comprobante_proveedor_id')->nullable();
            $table->unsignedBigInteger('anita_scan_documento_id')->nullable();
            $table->string('modo', 16);
            $table->unsignedBigInteger('cuentacontable_id')->nullable();
            $table->timestamps();

            $table->foreign('ordencompra_id', 'fk_oc_nc_imp_oc')
                ->references('id')->on('ordencompra')->cascadeOnDelete();
            $table->foreign('precarga_comprobante_proveedor_id', 'fk_oc_nc_imp_pre')
                ->references('id')->on('precarga_comprobante_proveedor')->nullOnDelete();
            $table->foreign('cuentacontable_id', 'fk_oc_nc_imp_cta')
                ->references('id')->on('cuentacontable')->restrictOnDelete();

            $table->unique(
                ['ordencompra_id', 'precarga_comprobante_proveedor_id'],
                'uk_oc_nc_imp_precarga'
            );
            $table->unique(
                ['ordencompra_id', 'anita_scan_documento_id'],
                'uk_oc_nc_imp_anita'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ordencompra_legajo_nc_imputacion');
    }
};
