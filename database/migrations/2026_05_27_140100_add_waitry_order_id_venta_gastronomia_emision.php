<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('venta_gastronomia_emision')) {
            return;
        }

        Schema::table('venta_gastronomia_emision', function (Blueprint $table) {
            if (! Schema::hasColumn('venta_gastronomia_emision', 'waitry_order_id')) {
                $table->unsignedBigInteger('waitry_order_id')->nullable()->after('cuenta_gastronomia_id');
                $table->unique('waitry_order_id', 'uq_vge_waitry_order_id');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('venta_gastronomia_emision')) {
            return;
        }

        Schema::table('venta_gastronomia_emision', function (Blueprint $table) {
            if (Schema::hasColumn('venta_gastronomia_emision', 'waitry_order_id')) {
                $table->dropUnique('uq_vge_waitry_order_id');
                $table->dropColumn('waitry_order_id');
            }
        });
    }
};
