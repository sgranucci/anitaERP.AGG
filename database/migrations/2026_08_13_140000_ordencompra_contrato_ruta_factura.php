<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Contrato / OC abierta: ruta de facturación (con o sin recepción COM) e imputación
 * contable del neto cuando la factura no pasa por recepción.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('ordencompra')) {
            return;
        }

        Schema::table('ordencompra', function (Blueprint $table) {
            if (! Schema::hasColumn('ordencompra', 'contrato_requiere_recepcion')) {
                $table->boolean('contrato_requiere_recepcion')->default(true)->after('contrato_responsable_id')
                    ->comment('Si el contrato exige COM para cargar facturas; false = factura sin recepción');
            }
            if (! Schema::hasColumn('ordencompra', 'contrato_imputacion_contable')) {
                $table->string('contrato_imputacion_contable', 20)->nullable()->after('contrato_requiere_recepcion')
                    ->comment('Sin recepción: articulos (cuenta de ítems OC) o manual (carga en la factura)');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('ordencompra')) {
            return;
        }

        foreach (['contrato_imputacion_contable', 'contrato_requiere_recepcion'] as $columna) {
            if (Schema::hasColumn('ordencompra', $columna)) {
                Schema::table('ordencompra', function (Blueprint $table) use ($columna) {
                    $table->dropColumn($columna);
                });
            }
        }
    }
};
