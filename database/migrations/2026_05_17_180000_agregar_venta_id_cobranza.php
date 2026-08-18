<?php

use App\Support\Database\MigrationDialectSupport;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
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
            MigrationDialectSupport::statementPorDriver(
                'UPDATE cobranza c
                 INNER JOIN caja_movimiento cm ON cm.cobranza_id = c.id
                 SET c.venta_id = cm.venta_id
                 WHERE cm.venta_id IS NOT NULL AND c.venta_id IS NULL',
                'UPDATE cobranza AS c
                 SET venta_id = cm.venta_id
                 FROM caja_movimiento AS cm
                 WHERE cm.cobranza_id = c.id
                   AND cm.venta_id IS NOT NULL
                   AND c.venta_id IS NULL'
            );
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
