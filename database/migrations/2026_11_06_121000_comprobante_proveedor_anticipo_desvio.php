<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('comprobante_proveedor_anticipo_desvio')) {
            Schema::create('comprobante_proveedor_anticipo_desvio', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('comprobante_proveedor_id');
                $table->unsignedBigInteger('ordencompra_id')->nullable();
                $table->unsignedBigInteger('empresa_id');
                $table->unsignedBigInteger('proveedor_id')->nullable();
                $table->decimal('pct_anticipo_oc', 5, 2)->default(0);
                $table->decimal('total_oc', 18, 2)->default(0);
                $table->decimal('importe_esperado', 18, 2)->default(0);
                $table->decimal('importe_ingresado', 18, 2)->default(0);
                $table->decimal('desvio_pct', 8, 4)->default(0);
                $table->decimal('tolerancia_pct', 8, 4)->default(0);
                $table->string('estado', 32);
                $table->unsignedBigInteger('usuario_aprobacion_id')->nullable();
                $table->timestamp('aprobado_at')->nullable();
                $table->string('motivo', 500)->nullable();
                $table->timestamps();

                $table->unique('comprobante_proveedor_id', 'cp_anticipo_desvio_comp_uq');
                $table->index(['estado', 'empresa_id'], 'cp_anticipo_desvio_estado_emp_idx');
            });
        }

        if (! Schema::hasTable('configuracion_anticipo_factura_destinatario')) {
            Schema::create('configuracion_anticipo_factura_destinatario', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('usuario_id');
                $table->timestamps();

                $table->unique('usuario_id', 'cfg_anticipo_factura_dest_uid_uq');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('comprobante_proveedor_anticipo_desvio');
        Schema::dropIfExists('configuracion_anticipo_factura_destinatario');
    }
};
