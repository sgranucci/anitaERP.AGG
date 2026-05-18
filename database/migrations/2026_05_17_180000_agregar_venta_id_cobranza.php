<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('cobranza')) {
            return;
        }

        if (! Schema::hasColumn('cobranza', 'venta_id')) {
            Schema::table('cobranza', function (Blueprint $table) {
                $table->unsignedBigInteger('venta_id')->nullable()->after('cliente_id');
                $table->foreign('venta_id', 'fk_cobranza_venta')
                    ->references('id')->on('venta')
                    ->onDelete('restrict')
                    ->onUpdate('restrict');
            });
        }

        if (Schema::hasTable('caja_movimiento') && Schema::hasColumn('caja_movimiento', 'venta_id')) {
            DB::table('cobranza as c')
                ->join('caja_movimiento as cm', 'cm.cobranza_id', '=', 'c.id')
                ->whereNotNull('cm.venta_id')
                ->whereNull('c.venta_id')
                ->update(['c.venta_id' => DB::raw('cm.venta_id')]);
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('cobranza') || ! Schema::hasColumn('cobranza', 'venta_id')) {
            return;
        }

        Schema::table('cobranza', function (Blueprint $table) {
            $table->dropForeign('fk_cobranza_venta');
            $table->dropColumn('venta_id');
        });
    }
};
