<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('proveedor_cuentacorriente_aplicacion', function (Blueprint $table) {
            if (! Schema::hasColumn('proveedor_cuentacorriente_aplicacion', 'diferencia_cambio')) {
                $table->decimal('diferencia_cambio', 22, 4)->default(0)->after('cotizacion');
            }
            if (! Schema::hasColumn('proveedor_cuentacorriente_aplicacion', 'asiento_id')) {
                $table->unsignedBigInteger('asiento_id')->nullable()->after('diferencia_cambio');
                $table->index('asiento_id', 'idx_prov_cc_apl_asiento');
            }
        });
    }

    public function down(): void
    {
        Schema::table('proveedor_cuentacorriente_aplicacion', function (Blueprint $table) {
            if (Schema::hasColumn('proveedor_cuentacorriente_aplicacion', 'asiento_id')) {
                $table->dropIndex('idx_prov_cc_apl_asiento');
                $table->dropColumn('asiento_id');
            }
            if (Schema::hasColumn('proveedor_cuentacorriente_aplicacion', 'diferencia_cambio')) {
                $table->dropColumn('diferencia_cambio');
            }
        });
    }
};
