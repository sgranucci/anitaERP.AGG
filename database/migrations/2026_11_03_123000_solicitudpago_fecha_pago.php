<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fecha en la que tesorería va a pagar la solicitud.
 * La posición bancaria diaria toma esta fecha; sin ella la SP no entra.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('solicitudpago') || Schema::hasColumn('solicitudpago', 'fecha_pago')) {
            return;
        }

        Schema::table('solicitudpago', function (Blueprint $table) {
            $table->date('fecha_pago')->nullable()->after('fecha_vencimiento');
            $table->index('fecha_pago', 'idx_sp_fecha_pago');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('solicitudpago') || ! Schema::hasColumn('solicitudpago', 'fecha_pago')) {
            return;
        }

        Schema::table('solicitudpago', function (Blueprint $table) {
            $table->dropIndex('idx_sp_fecha_pago');
            $table->dropColumn('fecha_pago');
        });
    }
};
