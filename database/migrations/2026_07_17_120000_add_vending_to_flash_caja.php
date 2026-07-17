<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('flash_caja', function (Blueprint $table) {
            if (! Schema::hasColumn('flash_caja', 'vending')) {
                $table->decimal('vending', 16, 2)->default(0)->after('estac');
            }
        });
    }

    public function down(): void
    {
        Schema::table('flash_caja', function (Blueprint $table) {
            if (Schema::hasColumn('flash_caja', 'vending')) {
                $table->dropColumn('vending');
            }
        });
    }
};
