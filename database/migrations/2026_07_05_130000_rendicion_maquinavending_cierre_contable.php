<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rendicion_maquinavending_caja', function (Blueprint $table) {
            if (! Schema::hasColumn('rendicion_maquinavending_caja', 'asiento_id')) {
                $table->unsignedBigInteger('asiento_id')->nullable()->after('observacion');
                $table->index('asiento_id', 'idx_rend_mv_caja_asiento');
            }
            if (! Schema::hasColumn('rendicion_maquinavending_caja', 'cierre_contable_en')) {
                $table->timestamp('cierre_contable_en')->nullable()->after('asiento_id');
            }
            if (! Schema::hasColumn('rendicion_maquinavending_caja', 'cierre_contable_usuario_id')) {
                $table->unsignedBigInteger('cierre_contable_usuario_id')->nullable()->after('cierre_contable_en');
            }
        });
    }

    public function down(): void
    {
        Schema::table('rendicion_maquinavending_caja', function (Blueprint $table) {
            if (Schema::hasColumn('rendicion_maquinavending_caja', 'cierre_contable_usuario_id')) {
                $table->dropColumn('cierre_contable_usuario_id');
            }
            if (Schema::hasColumn('rendicion_maquinavending_caja', 'cierre_contable_en')) {
                $table->dropColumn('cierre_contable_en');
            }
            if (Schema::hasColumn('rendicion_maquinavending_caja', 'asiento_id')) {
                $table->dropIndex('idx_rend_mv_caja_asiento');
                $table->dropColumn('asiento_id');
            }
        });
    }
};
