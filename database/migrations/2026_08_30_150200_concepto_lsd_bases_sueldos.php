<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('concepto_sueldos')) {
            return;
        }
        if (Schema::hasColumn('concepto_sueldos', 'lsd_bases')) {
            return;
        }
        Schema::table('concepto_sueldos', function (Blueprint $table) {
            $table->json('lsd_bases')->nullable()->after('lsd_subsistemas');
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('concepto_sueldos', 'lsd_bases')) {
            Schema::table('concepto_sueldos', function (Blueprint $table) {
                $table->dropColumn('lsd_bases');
            });
        }
    }
};
