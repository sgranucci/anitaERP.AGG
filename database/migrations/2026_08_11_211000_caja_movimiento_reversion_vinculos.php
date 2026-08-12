<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('caja_movimiento', function (Blueprint $table) {
            if (! Schema::hasColumn('caja_movimiento', 'caja_movimiento_origen_id')) {
                $table->unsignedBigInteger('caja_movimiento_origen_id')->nullable()->after('solicitudpago_id');
                $table->foreign('caja_movimiento_origen_id', 'fk_caja_mov_origen_reversion')
                    ->references('id')->on('caja_movimiento')->nullOnDelete()->cascadeOnUpdate();
            }
            if (! Schema::hasColumn('caja_movimiento', 'caja_movimiento_revertido_por_id')) {
                $table->unsignedBigInteger('caja_movimiento_revertido_por_id')->nullable()->after('caja_movimiento_origen_id');
                $table->foreign('caja_movimiento_revertido_por_id', 'fk_caja_mov_revertido_por')
                    ->references('id')->on('caja_movimiento')->nullOnDelete()->cascadeOnUpdate();
            }
        });
    }

    public function down(): void
    {
        Schema::table('caja_movimiento', function (Blueprint $table) {
            if (Schema::hasColumn('caja_movimiento', 'caja_movimiento_revertido_por_id')) {
                $table->dropForeign('fk_caja_mov_revertido_por');
                $table->dropColumn('caja_movimiento_revertido_por_id');
            }
            if (Schema::hasColumn('caja_movimiento', 'caja_movimiento_origen_id')) {
                $table->dropForeign('fk_caja_mov_origen_reversion');
                $table->dropColumn('caja_movimiento_origen_id');
            }
        });
    }
};
