<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('tipotransaccion_stock')) {
            return;
        }

        Schema::table('tipotransaccion_stock', function (Blueprint $table) {
            $table->string('abreviatura', 15)->change();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('tipotransaccion_stock')) {
            return;
        }

        Schema::table('tipotransaccion_stock', function (Blueprint $table) {
            $table->string('abreviatura', 5)->change();
        });
    }
};
