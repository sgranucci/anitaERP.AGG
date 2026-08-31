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
            if (! Schema::hasColumn('cuenta_gastronomia', 'waitry_monto_cobro')) {
                $table->decimal('waitry_monto_cobro', 15, 2)->nullable()->after('waitry_tipo_pago');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('cuenta_gastronomia')) {
            return;
        }

        Schema::table('cuenta_gastronomia', function (Blueprint $table) {
            if (Schema::hasColumn('cuenta_gastronomia', 'waitry_monto_cobro')) {
                $table->dropColumn('waitry_monto_cobro');
            }
        });
    }
};
