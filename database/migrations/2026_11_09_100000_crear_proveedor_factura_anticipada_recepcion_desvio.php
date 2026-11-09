<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Historia diaria de desvíos (recepción > factura anticipada) por OC.
 * El auditor avisa solo cuando aparece un desvío nuevo o cambia el exceso.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('proveedor_factura_anticipada_recepcion_desvio')) {
            return;
        }

        Schema::create('proveedor_factura_anticipada_recepcion_desvio', function (Blueprint $table) {
            $table->id();
            $table->date('fecha');
            $table->unsignedBigInteger('empresa_id');
            $table->unsignedBigInteger('ordencompra_id');
            $table->unsignedBigInteger('cuentacontable_id')->nullable();
            $table->string('numeroordencompra', 40)->nullable();
            $table->decimal('factura', 18, 2)->default(0);
            $table->decimal('recepcion', 18, 2)->default(0);
            $table->decimal('exceso_recepcion', 18, 2)->default(0);
            $table->timestamps();

            $table->unique(
                ['fecha', 'empresa_id', 'ordencompra_id'],
                'fa_recepcion_desvio_dia_unico'
            );
            $table->index(
                ['empresa_id', 'fecha'],
                'fa_recepcion_desvio_empresa_fecha'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('proveedor_factura_anticipada_recepcion_desvio');
    }
};
