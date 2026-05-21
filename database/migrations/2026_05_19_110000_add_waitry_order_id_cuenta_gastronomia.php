<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cuenta_gastronomia', function (Blueprint $table) {
            $table->unsignedBigInteger('waitry_order_id')->nullable()->after('venta_id');
            $table->index('waitry_order_id', 'idx_cuenta_gastro_waitry_order');
        });
    }

    public function down(): void
    {
        Schema::table('cuenta_gastronomia', function (Blueprint $table) {
            $table->dropIndex('idx_cuenta_gastro_waitry_order');
            $table->dropColumn('waitry_order_id');
        });
    }
};
