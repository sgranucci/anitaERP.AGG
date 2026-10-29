<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cuenta_gastronomia', function (Blueprint $table) {
            if (! Schema::hasColumn('cuenta_gastronomia', 'waitry_slot')) {
                $table->string('waitry_slot', 64)->nullable()->after('waitry_display_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('cuenta_gastronomia', function (Blueprint $table) {
            if (Schema::hasColumn('cuenta_gastronomia', 'waitry_slot')) {
                $table->dropColumn('waitry_slot');
            }
        });
    }
};
