<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('caja_movimiento', function (Blueprint $table) {
            if (! Schema::hasColumn('caja_movimiento', 'solicitudpago_id')) {
                $table->unsignedBigInteger('solicitudpago_id')->nullable()->after('pagoproveedor_id');
                $table->foreign('solicitudpago_id', 'fk_caja_mov_solicitudpago')
                    ->references('id')->on('solicitudpago')
                    ->onDelete('restrict')->onUpdate('cascade');
                $table->index('solicitudpago_id', 'idx_caja_mov_solicitudpago');
            }
        });
    }

    public function down(): void
    {
        Schema::table('caja_movimiento', function (Blueprint $table) {
            if (Schema::hasColumn('caja_movimiento', 'solicitudpago_id')) {
                $table->dropForeign('fk_caja_mov_solicitudpago');
                $table->dropIndex('idx_caja_mov_solicitudpago');
                $table->dropColumn('solicitudpago_id');
            }
        });
    }
};
