<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('proveedor_cuentacorriente_aplicacion', function (Blueprint $table) {
            if (! Schema::hasColumn('proveedor_cuentacorriente_aplicacion', 'cotizacion_liquidacion')) {
                $table->decimal('cotizacion_liquidacion', 22, 8)->nullable()->after('cotizacion');
            }
        });
    }

    public function down(): void
    {
        Schema::table('proveedor_cuentacorriente_aplicacion', function (Blueprint $table) {
            if (Schema::hasColumn('proveedor_cuentacorriente_aplicacion', 'cotizacion_liquidacion')) {
                $table->dropColumn('cotizacion_liquidacion');
            }
        });
    }
};
