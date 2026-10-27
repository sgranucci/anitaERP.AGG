<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * COA generada por el pago de más de una cobranza: apunta a la cobranza que la originó.
 * Sin este vínculo, editar o anular la cobranza no encuentra su COA.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('cobranza', 'cobranza_origen_id')) {
            return;
        }

        Schema::table('cobranza', function (Blueprint $table) {
            $table->unsignedBigInteger('cobranza_origen_id')->nullable()->after('venta_id');
            $table->index('cobranza_origen_id', 'idx_cobranza_origen_id');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('cobranza', 'cobranza_origen_id')) {
            return;
        }

        Schema::table('cobranza', function (Blueprint $table) {
            $table->dropIndex('idx_cobranza_origen_id');
            $table->dropColumn('cobranza_origen_id');
        });
    }
};
