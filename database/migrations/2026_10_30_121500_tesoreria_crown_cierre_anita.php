<?php

use App\Support\Configuracion\EntornoEmpresaSupport;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * El cierre guarda el desglose con el que se armó la diferencia,
 * y la rendición puede cargar el fallo de mozo que Anita resta del X.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! EntornoEmpresaSupport::esCrown()) {
            return;
        }

        if (Schema::hasTable('tesoreria_crown_comprobante') && ! Schema::hasColumn('tesoreria_crown_comprobante', 'fallo_mozo')) {
            Schema::table('tesoreria_crown_comprobante', function (Blueprint $table) {
                $table->decimal('fallo_mozo', 14, 2)->default(0)->after('total_nc');
            });
        }

        if (Schema::hasTable('tesoreria_crown_cierre') && ! Schema::hasColumn('tesoreria_crown_cierre', 'componentes')) {
            Schema::table('tesoreria_crown_cierre', function (Blueprint $table) {
                $table->json('componentes')->nullable()->after('diferencia');
            });
        }
    }

    public function down(): void
    {
        if (! EntornoEmpresaSupport::esCrown()) {
            return;
        }

        if (Schema::hasTable('tesoreria_crown_cierre') && Schema::hasColumn('tesoreria_crown_cierre', 'componentes')) {
            Schema::table('tesoreria_crown_cierre', function (Blueprint $table) {
                $table->dropColumn('componentes');
            });
        }

        if (Schema::hasTable('tesoreria_crown_comprobante') && Schema::hasColumn('tesoreria_crown_comprobante', 'fallo_mozo')) {
            Schema::table('tesoreria_crown_comprobante', function (Blueprint $table) {
                $table->dropColumn('fallo_mozo');
            });
        }
    }
};
