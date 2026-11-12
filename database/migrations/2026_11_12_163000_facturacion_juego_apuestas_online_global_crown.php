<?php

use App\Support\Configuracion\EntornoEmpresaSupport;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Apuestas deportivas en facturación juego (PV + cuenta propios) y flags en flash.
 * Los 3 rubros online (Del Río / Betsur / Apuestas) se facturan una sola vez por día.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! EntornoEmpresaSupport::esCrown()) {
            return;
        }

        if (Schema::hasTable('flash_crown')) {
            Schema::table('flash_crown', function (Blueprint $table) {
                if (! Schema::hasColumn('flash_crown', 'proc_apuestas')) {
                    $table->char('proc_apuestas', 1)->default(' ')->after('proc_betsur');
                }
                if (! Schema::hasColumn('flash_crown', 'venta_apuestas_id')) {
                    $table->unsignedBigInteger('venta_apuestas_id')->nullable()->after('venta_betsur_id');
                }
                if (! Schema::hasColumn('flash_crown', 'asiento_apuestas_id')) {
                    $table->unsignedBigInteger('asiento_apuestas_id')->nullable()->after('asiento_betsur_id');
                }
            });
        }

        if (Schema::hasTable('facturacion_juego_parametro')
            && ! Schema::hasColumn('facturacion_juego_parametro', 'sucursal_apuestas')) {
            Schema::table('facturacion_juego_parametro', function (Blueprint $table) {
                $table->unsignedInteger('sucursal_apuestas')->default(0)->after('sucursal_betsur');
            });
        }

        if (Schema::hasTable('facturacion_juego_sala')
            && ! Schema::hasColumn('facturacion_juego_sala', 'cuentacontable_apuestas_id')) {
            Schema::table('facturacion_juego_sala', function (Blueprint $table) {
                $table->unsignedBigInteger('cuentacontable_apuestas_id')->nullable()->after('cuentacontable_betsur_id');
            });
        }
    }

    public function down(): void
    {
        if (! EntornoEmpresaSupport::esCrown()) {
            return;
        }

        if (Schema::hasTable('facturacion_juego_sala')
            && Schema::hasColumn('facturacion_juego_sala', 'cuentacontable_apuestas_id')) {
            Schema::table('facturacion_juego_sala', function (Blueprint $table) {
                $table->dropColumn('cuentacontable_apuestas_id');
            });
        }

        if (Schema::hasTable('facturacion_juego_parametro')
            && Schema::hasColumn('facturacion_juego_parametro', 'sucursal_apuestas')) {
            Schema::table('facturacion_juego_parametro', function (Blueprint $table) {
                $table->dropColumn('sucursal_apuestas');
            });
        }

        if (Schema::hasTable('flash_crown') && Schema::hasColumn('flash_crown', 'proc_apuestas')) {
            Schema::table('flash_crown', function (Blueprint $table) {
                $table->dropColumn(['proc_apuestas', 'venta_apuestas_id', 'asiento_apuestas_id']);
            });
        }
    }
};
