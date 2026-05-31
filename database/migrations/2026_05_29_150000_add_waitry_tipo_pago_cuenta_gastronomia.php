<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cuenta_gastronomia', function (Blueprint $table) {
            if (! Schema::hasColumn('cuenta_gastronomia', 'waitry_tipo_pago')) {
                $table->string('waitry_tipo_pago', 32)->nullable()->after('waitry_cobro_totem');
            }
        });
    }

    public function down(): void
    {
        Schema::table('cuenta_gastronomia', function (Blueprint $table) {
            if (Schema::hasColumn('cuenta_gastronomia', 'waitry_tipo_pago')) {
                $table->dropColumn('waitry_tipo_pago');
            }
        });
    }
};
