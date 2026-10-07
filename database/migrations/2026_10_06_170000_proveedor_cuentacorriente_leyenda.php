<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('proveedor_cuentacorriente', function (Blueprint $table) {
            if (! Schema::hasColumn('proveedor_cuentacorriente', 'leyenda')) {
                $table->string('leyenda', 120)->nullable()->after('pagoproveedor_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('proveedor_cuentacorriente', function (Blueprint $table) {
            if (Schema::hasColumn('proveedor_cuentacorriente', 'leyenda')) {
                $table->dropColumn('leyenda');
            }
        });
    }
};
