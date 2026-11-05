<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Programación de pagos sobre la deuda (espejo de solicitudpago.fecha_pago)
 * y tipo de lote de la propuesta: urgentes por transferencia o cheques del día.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('proveedor_cuentacorriente')) {
            $agregarFecha = ! Schema::hasColumn('proveedor_cuentacorriente', 'fecha_pago');
            $agregarUrgente = ! Schema::hasColumn('proveedor_cuentacorriente', 'urgente_pago');
            if ($agregarFecha || $agregarUrgente) {
                Schema::table('proveedor_cuentacorriente', function (Blueprint $table) use ($agregarFecha, $agregarUrgente) {
                    if ($agregarFecha) {
                        $table->date('fecha_pago')->nullable();
                        $table->index('fecha_pago', 'idx_pcc_fecha_pago');
                    }
                    if ($agregarUrgente) {
                        $table->boolean('urgente_pago')->default(false);
                        $table->index('urgente_pago', 'idx_pcc_urgente_pago');
                    }
                });
            }
        }

        if (Schema::hasTable('propuesta_pago') && ! Schema::hasColumn('propuesta_pago', 'tipo_lote')) {
            Schema::table('propuesta_pago', function (Blueprint $table) {
                $table->string('tipo_lote', 32)->default('LEGACY_VENCIMIENTO');
                $table->index('tipo_lote', 'idx_pp_tipo_lote');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('propuesta_pago') && Schema::hasColumn('propuesta_pago', 'tipo_lote')) {
            Schema::table('propuesta_pago', function (Blueprint $table) {
                $table->dropIndex('idx_pp_tipo_lote');
                $table->dropColumn('tipo_lote');
            });
        }

        if (! Schema::hasTable('proveedor_cuentacorriente')) {
            return;
        }

        $quitarUrgente = Schema::hasColumn('proveedor_cuentacorriente', 'urgente_pago');
        $quitarFecha = Schema::hasColumn('proveedor_cuentacorriente', 'fecha_pago');
        if ($quitarUrgente || $quitarFecha) {
            Schema::table('proveedor_cuentacorriente', function (Blueprint $table) use ($quitarUrgente, $quitarFecha) {
                if ($quitarUrgente) {
                    $table->dropIndex('idx_pcc_urgente_pago');
                    $table->dropColumn('urgente_pago');
                }
                if ($quitarFecha) {
                    $table->dropIndex('idx_pcc_fecha_pago');
                    $table->dropColumn('fecha_pago');
                }
            });
        }
    }
};
