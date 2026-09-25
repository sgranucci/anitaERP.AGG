<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Reparto del Debe de gasto (neto) en N cuentas desde la solapa Asiento.
 * Sin SoftDeletes: baja física al sincronizar el comprobante.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('comprobante_proveedor_debe_gasto')) {
            return;
        }

        Schema::create('comprobante_proveedor_debe_gasto', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('comprobante_proveedor_id');
            $table->unsignedInteger('orden')->default(1);
            $table->unsignedBigInteger('cuentacontable_id');
            $table->decimal('importe', 18, 4)->default(0);
            $table->unsignedBigInteger('centrocosto_id')->nullable();
            $table->timestamps();

            $table->foreign('comprobante_proveedor_id', 'cp_debe_gasto_cp_fk')
                ->references('id')->on('comprobante_proveedor')->cascadeOnDelete();
            $table->foreign('cuentacontable_id', 'cp_debe_gasto_cuenta_fk')
                ->references('id')->on('cuentacontable')->restrictOnDelete();
            $table->foreign('centrocosto_id', 'cp_debe_gasto_cc_fk')
                ->references('id')->on('centrocosto')->nullOnDelete();
            $table->index(['comprobante_proveedor_id', 'orden'], 'cp_debe_gasto_cp_orden_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('comprobante_proveedor_debe_gasto');
    }
};
