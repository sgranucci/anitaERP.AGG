<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Snapshot de forma/medio de pago (Anita M.Pago + Detalle pago) en líneas de propuesta.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('propuesta_pago_linea')) {
            return;
        }

        Schema::table('propuesta_pago_linea', function (Blueprint $table) {
            if (! Schema::hasColumn('propuesta_pago_linea', 'formapago_id')) {
                $table->unsignedBigInteger('formapago_id')->nullable()->after('moneda_id');
                $table->index('formapago_id');
            }
            if (! Schema::hasColumn('propuesta_pago_linea', 'detalle_pago')) {
                $table->string('detalle_pago', 255)->nullable()->after('formapago_id');
            }
            if (! Schema::hasColumn('propuesta_pago_linea', 'ordencompra_id')) {
                $table->unsignedBigInteger('ordencompra_id')->nullable()->after('comprobante_proveedor_id');
                $table->index('ordencompra_id');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('propuesta_pago_linea')) {
            return;
        }

        Schema::table('propuesta_pago_linea', function (Blueprint $table) {
            foreach (['formapago_id', 'detalle_pago', 'ordencompra_id'] as $col) {
                if (Schema::hasColumn('propuesta_pago_linea', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
