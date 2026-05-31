<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('totem_waitry_gastronomia')) {
            return;
        }

        Schema::table('totem_waitry_gastronomia', function (Blueprint $table) {
            if (! Schema::hasColumn('totem_waitry_gastronomia', 'waitry_table_id')) {
                $table->unsignedInteger('waitry_table_id')->nullable()->after('ubicacion_id');
                $table->index(['empresa_id', 'waitry_table_id'], 'idx_totem_waitry_empresa_table');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('totem_waitry_gastronomia')) {
            return;
        }

        Schema::table('totem_waitry_gastronomia', function (Blueprint $table) {
            if (Schema::hasColumn('totem_waitry_gastronomia', 'waitry_table_id')) {
                $table->dropIndex('idx_totem_waitry_empresa_table');
                $table->dropColumn('waitry_table_id');
            }
        });
    }
};
