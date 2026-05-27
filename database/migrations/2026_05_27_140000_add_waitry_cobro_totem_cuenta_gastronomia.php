<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('cuenta_gastronomia')) {
            return;
        }

        Schema::table('cuenta_gastronomia', function (Blueprint $table) {
            if (! Schema::hasColumn('cuenta_gastronomia', 'waitry_cobro_totem')) {
                $table->boolean('waitry_cobro_totem')->default(false)->after('waitry_order_id');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('cuenta_gastronomia')) {
            return;
        }

        Schema::table('cuenta_gastronomia', function (Blueprint $table) {
            if (Schema::hasColumn('cuenta_gastronomia', 'waitry_cobro_totem')) {
                $table->dropColumn('waitry_cobro_totem');
            }
        });
    }
};
