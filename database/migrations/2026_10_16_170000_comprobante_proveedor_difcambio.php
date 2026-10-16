<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('comprobante_proveedor_difcambio')) {
            return;
        }

        Schema::create('comprobante_proveedor_difcambio', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('comprobante_proveedor_id');
            $table->unsignedBigInteger('recepcion_proveedor_id');
            $table->unsignedBigInteger('ordencompra_id')->nullable();
            $table->unsignedBigInteger('empresa_id');
            $table->unsignedBigInteger('proveedor_id')->nullable();
            $table->decimal('tc_recepcion', 18, 6);
            $table->decimal('tc_factura', 18, 6);
            $table->decimal('nominal_me', 18, 4)->default(0);
            $table->decimal('importe_pesos', 18, 2)->default(0);
            $table->decimal('porcentaje', 8, 4)->default(0);
            $table->string('estado', 32);
            $table->unsignedBigInteger('usuario_aprobacion_id')->nullable();
            $table->timestamp('aprobado_at')->nullable();
            $table->unsignedBigInteger('asiento_id')->nullable();
            $table->timestamps();

            $table->unique(
                ['comprobante_proveedor_id', 'recepcion_proveedor_id'],
                'cp_difcambio_comp_rec_uq'
            );
            $table->index(['estado', 'empresa_id'], 'cp_difcambio_estado_emp_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('comprobante_proveedor_difcambio');
    }
};
