<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Días de atraso del proveedor (Anita promae.prom_dias_atraso).
 * La proyección de pagos suma esos días a la fecha del movimiento para F.Difer.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('proveedor', 'dias_atraso')) {
            return;
        }

        Schema::table('proveedor', function (Blueprint $table) {
            $table->integer('dias_atraso')->default(0)->after('condicionentrega_id');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('proveedor', 'dias_atraso')) {
            return;
        }

        Schema::table('proveedor', function (Blueprint $table) {
            $table->dropColumn('dias_atraso');
        });
    }
};
