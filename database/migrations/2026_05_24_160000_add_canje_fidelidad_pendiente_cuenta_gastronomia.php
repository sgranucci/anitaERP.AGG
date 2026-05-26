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
            if (! Schema::hasColumn('cuenta_gastronomia', 'canje_fidelidad_pendiente')) {
                $table->json('canje_fidelidad_pendiente')->nullable()->after('canje_premio_pendiente');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('cuenta_gastronomia')) {
            return;
        }

        Schema::table('cuenta_gastronomia', function (Blueprint $table) {
            if (Schema::hasColumn('cuenta_gastronomia', 'canje_fidelidad_pendiente')) {
                $table->dropColumn('canje_fidelidad_pendiente');
            }
        });
    }
};
