<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Débitos/créditos brutos por mes en cuentacontable_saldo_mes.
 * Necesarios para Balance de Sumas y Saldos en modo períodos (sin releer asientos).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('cuentacontable_saldo_mes')) {
            return;
        }

        Schema::table('cuentacontable_saldo_mes', function (Blueprint $table) {
            if (! Schema::hasColumn('cuentacontable_saldo_mes', 'debe')) {
                $table->decimal('debe', 24, 4)->default(0)->after('moneda_id');
            }
            if (! Schema::hasColumn('cuentacontable_saldo_mes', 'haber')) {
                $table->decimal('haber', 24, 4)->default(0)->after('debe');
            }
            if (! Schema::hasColumn('cuentacontable_saldo_mes', 'debe_local')) {
                $table->decimal('debe_local', 24, 4)->default(0)->after('haber');
            }
            if (! Schema::hasColumn('cuentacontable_saldo_mes', 'haber_local')) {
                $table->decimal('haber_local', 24, 4)->default(0)->after('debe_local');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('cuentacontable_saldo_mes')) {
            return;
        }

        Schema::table('cuentacontable_saldo_mes', function (Blueprint $table) {
            foreach (['debe', 'haber', 'debe_local', 'haber_local'] as $col) {
                if (Schema::hasColumn('cuentacontable_saldo_mes', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
