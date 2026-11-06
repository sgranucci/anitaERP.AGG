<?php

use App\Support\Configuracion\EntornoEmpresaSupport;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cotización grabada en el comprobante Anita (efe_cotiz_dolar / efe_cotiz_euro).
 * El resumen de cajeros convierte moneda extranjera con esa tasa, no con la del día.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! EntornoEmpresaSupport::esCrown()) {
            return;
        }
        if (! Schema::hasTable('tesoreria_crown_comprobante')) {
            return;
        }
        Schema::table('tesoreria_crown_comprobante', function (Blueprint $table) {
            if (! Schema::hasColumn('tesoreria_crown_comprobante', 'cotiz_dolar')) {
                $table->decimal('cotiz_dolar', 14, 4)->nullable()->after('total_qr');
            }
            if (! Schema::hasColumn('tesoreria_crown_comprobante', 'cotiz_euro')) {
                $table->decimal('cotiz_euro', 14, 4)->nullable()->after('cotiz_dolar');
            }
        });
    }

    public function down(): void
    {
        if (! EntornoEmpresaSupport::esCrown()) {
            return;
        }
        if (! Schema::hasTable('tesoreria_crown_comprobante')) {
            return;
        }
        Schema::table('tesoreria_crown_comprobante', function (Blueprint $table) {
            if (Schema::hasColumn('tesoreria_crown_comprobante', 'cotiz_euro')) {
                $table->dropColumn('cotiz_euro');
            }
            if (Schema::hasColumn('tesoreria_crown_comprobante', 'cotiz_dolar')) {
                $table->dropColumn('cotiz_dolar');
            }
        });
    }
};
