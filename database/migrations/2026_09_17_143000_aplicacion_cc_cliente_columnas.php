<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Paridad con proveedor_cuentacorriente_aplicacion para el workbench de aplicación CC clientes:
 * empresa, liquidación cruzada, DC y asiento.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cliente_cuentacorriente_aplicacion', function (Blueprint $table) {
            if (! Schema::hasColumn('cliente_cuentacorriente_aplicacion', 'empresa_id')) {
                $table->unsignedBigInteger('empresa_id')->nullable()->after('comprobanteaplicado');
                $table->index('empresa_id', 'cc_cli_apl_empresa_idx');
            }
            if (! Schema::hasColumn('cliente_cuentacorriente_aplicacion', 'cotizacion_liquidacion')) {
                $table->decimal('cotizacion_liquidacion', 18, 6)->nullable()->after('cotizacion');
            }
            if (! Schema::hasColumn('cliente_cuentacorriente_aplicacion', 'diferencia_cambio')) {
                $table->decimal('diferencia_cambio', 18, 4)->default(0)->after('cotizacion_liquidacion');
            }
            if (! Schema::hasColumn('cliente_cuentacorriente_aplicacion', 'asiento_id')) {
                $table->unsignedBigInteger('asiento_id')->nullable()->after('diferencia_cambio');
                $table->index('asiento_id', 'cc_cli_apl_asiento_idx');
            }
        });
    }

    public function down(): void
    {
        Schema::table('cliente_cuentacorriente_aplicacion', function (Blueprint $table) {
            if (Schema::hasColumn('cliente_cuentacorriente_aplicacion', 'asiento_id')) {
                $table->dropIndex('cc_cli_apl_asiento_idx');
                $table->dropColumn('asiento_id');
            }
            if (Schema::hasColumn('cliente_cuentacorriente_aplicacion', 'diferencia_cambio')) {
                $table->dropColumn('diferencia_cambio');
            }
            if (Schema::hasColumn('cliente_cuentacorriente_aplicacion', 'cotizacion_liquidacion')) {
                $table->dropColumn('cotizacion_liquidacion');
            }
            if (Schema::hasColumn('cliente_cuentacorriente_aplicacion', 'empresa_id')) {
                $table->dropIndex('cc_cli_apl_empresa_idx');
                $table->dropColumn('empresa_id');
            }
        });
    }
};
