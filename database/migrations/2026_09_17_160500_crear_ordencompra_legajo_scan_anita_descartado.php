<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Scans Anita (scanfactura) descartados del legajo al borrar su precarga.
 * Evita rematerializar / listar el mismo documento en la OC.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('ordencompra_legajo_scan_anita_descartado')) {
            return;
        }

        Schema::create('ordencompra_legajo_scan_anita_descartado', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('ordencompra_id')->nullable()->index();
            $table->unsignedBigInteger('empresa_id')->nullable()->index();
            $table->string('numeroordencompra', 30)->nullable()->index();
            $table->unsignedBigInteger('documento_id')->index();
            $table->string('letra', 2)->nullable();
            $table->unsignedInteger('sucursal')->nullable();
            $table->unsignedInteger('numerocomprobante')->nullable();
            $table->unsignedBigInteger('precarga_id_origen')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->timestamps();

            $table->unique(
                ['ordencompra_id', 'documento_id'],
                'uk_oc_scan_anita_descartado_oc_doc'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ordencompra_legajo_scan_anita_descartado');
    }
};
