<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cobro operativo Crown: tipo e importe al cerrar (emisión fiscal ARCA en etapa posterior).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('gastro_crown_cuenta')) {
            return;
        }

        Schema::table('gastro_crown_cuenta', function (Blueprint $table) {
            if (! Schema::hasColumn('gastro_crown_cuenta', 'tipo_cobro')) {
                $table->string('tipo_cobro', 20)->nullable()->after('estado');
            }
            if (! Schema::hasColumn('gastro_crown_cuenta', 'importe_cobrado')) {
                $table->decimal('importe_cobrado', 14, 2)->nullable()->after('tipo_cobro');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('gastro_crown_cuenta')) {
            return;
        }

        Schema::table('gastro_crown_cuenta', function (Blueprint $table) {
            if (Schema::hasColumn('gastro_crown_cuenta', 'importe_cobrado')) {
                $table->dropColumn('importe_cobrado');
            }
            if (Schema::hasColumn('gastro_crown_cuenta', 'tipo_cobro')) {
                $table->dropColumn('tipo_cobro');
            }
        });
    }
};
