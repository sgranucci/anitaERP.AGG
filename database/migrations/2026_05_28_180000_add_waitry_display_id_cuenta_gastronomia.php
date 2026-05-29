<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cuenta_gastronomia', function (Blueprint $table) {
            if (! Schema::hasColumn('cuenta_gastronomia', 'waitry_display_id')) {
                $table->string('waitry_display_id', 64)->nullable()->after('waitry_order_id');
                $table->index('waitry_display_id', 'idx_cuenta_gastro_waitry_display');
            }
        });
    }

    public function down(): void
    {
        Schema::table('cuenta_gastronomia', function (Blueprint $table) {
            if (Schema::hasColumn('cuenta_gastronomia', 'waitry_display_id')) {
                $table->dropIndex('idx_cuenta_gastro_waitry_display');
                $table->dropColumn('waitry_display_id');
            }
        });
    }
};
