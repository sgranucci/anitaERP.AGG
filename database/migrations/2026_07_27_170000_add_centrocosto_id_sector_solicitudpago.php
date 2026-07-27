<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('sector_solicitudpago', 'centrocosto_id')) {
            Schema::table('sector_solicitudpago', function (Blueprint $table) {
                $table->unsignedBigInteger('centrocosto_id')->nullable()->after('nombre');
                $table->foreign('centrocosto_id', 'fk_sector_sp_centrocosto')
                    ->references('id')->on('centrocosto')
                    ->onDelete('restrict')
                    ->onUpdate('cascade');
                $table->index('centrocosto_id', 'idx_sector_sp_centrocosto');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('sector_solicitudpago', 'centrocosto_id')) {
            Schema::table('sector_solicitudpago', function (Blueprint $table) {
                $table->dropForeign('fk_sector_sp_centrocosto');
                $table->dropIndex('idx_sector_sp_centrocosto');
                $table->dropColumn('centrocosto_id');
            });
        }
    }
};
