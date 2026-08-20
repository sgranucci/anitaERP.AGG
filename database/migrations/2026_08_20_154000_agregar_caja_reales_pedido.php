<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('pedido') || Schema::hasColumn('pedido', 'caja_reales')) {
            return;
        }

        Schema::table('pedido', function (Blueprint $table) {
            $table->unsignedInteger('caja_reales')->nullable()->after('estadopedido');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('pedido') || ! Schema::hasColumn('pedido', 'caja_reales')) {
            return;
        }

        Schema::table('pedido', function (Blueprint $table) {
            $table->dropColumn('caja_reales');
        });
    }
};
