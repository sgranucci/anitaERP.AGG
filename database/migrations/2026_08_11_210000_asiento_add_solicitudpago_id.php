<?php

use App\Support\Database\MigrationDialectSupport;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Referencia directa asiento → solicitud de pago (además de caja_movimiento_id).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('asiento', function (Blueprint $table) {
            if (! Schema::hasColumn('asiento', 'solicitudpago_id')) {
                $table->unsignedBigInteger('solicitudpago_id')->nullable()->after('caja_movimiento_id');
                $table->foreign('solicitudpago_id')
                    ->references('id')
                    ->on('solicitudpago')
                    ->nullOnDelete();
                $table->index('solicitudpago_id');
            }
        });

        // Backfill desde OP/IE ya vinculados a SP
        if (Schema::hasColumn('caja_movimiento', 'solicitudpago_id')) {
            MigrationDialectSupport::statementPorDriver(
                'UPDATE asiento a
                 INNER JOIN caja_movimiento cm ON cm.id = a.caja_movimiento_id
                 SET a.solicitudpago_id = cm.solicitudpago_id
                 WHERE a.solicitudpago_id IS NULL
                   AND cm.solicitudpago_id IS NOT NULL
                   AND cm.solicitudpago_id > 0',
                'UPDATE asiento AS a
                 SET solicitudpago_id = cm.solicitudpago_id
                 FROM caja_movimiento AS cm
                 WHERE cm.id = a.caja_movimiento_id
                   AND a.solicitudpago_id IS NULL
                   AND cm.solicitudpago_id IS NOT NULL
                   AND cm.solicitudpago_id > 0'
            );
        }
    }

    public function down(): void
    {
        Schema::table('asiento', function (Blueprint $table) {
            if (Schema::hasColumn('asiento', 'solicitudpago_id')) {
                $table->dropForeign(['solicitudpago_id']);
                $table->dropColumn('solicitudpago_id');
            }
        });
    }
};
