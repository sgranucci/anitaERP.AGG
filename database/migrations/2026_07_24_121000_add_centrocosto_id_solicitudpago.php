<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('solicitudpago', 'centrocosto_id')) {
            Schema::table('solicitudpago', function (Blueprint $table) {
                $table->unsignedBigInteger('centrocosto_id')->nullable()->after('sector_solicitudpago_id');
                $table->foreign('centrocosto_id', 'fk_sp_centrocosto')
                    ->references('id')->on('centrocosto')
                    ->onDelete('restrict')
                    ->onUpdate('cascade');
                $table->index('centrocosto_id', 'idx_sp_centrocosto');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('solicitudpago', 'centrocosto_id')) {
            Schema::table('solicitudpago', function (Blueprint $table) {
                $table->dropForeign('fk_sp_centrocosto');
                $table->dropIndex('idx_sp_centrocosto');
                $table->dropColumn('centrocosto_id');
            });
        }
    }
};
