<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const INDEX = 'idx_venta_fecha_deleted';

    public function up(): void
    {
        Schema::table('venta', function (Blueprint $table): void {
            $table->index(['fecha', 'deleted_at'], self::INDEX);
        });
    }

    public function down(): void
    {
        Schema::table('venta', function (Blueprint $table): void {
            $table->dropIndex(self::INDEX);
        });
    }
};
