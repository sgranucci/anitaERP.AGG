<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * P0 validación de abono: período de servicio, plantilla y control de ingresos en la OC.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('ordencompra')) {
            return;
        }

        Schema::table('ordencompra', function (Blueprint $table) {
            if (! Schema::hasColumn('ordencompra', 'contrato_periodo_servicio')) {
                $table->string('contrato_periodo_servicio', 20)->nullable()->after('contrato_cuentacontable_id')
                    ->comment('mes_vencido | mismo_mes: ventana de tickets del remito');
            }
            if (! Schema::hasColumn('ordencompra', 'contrato_requiere_validacion_abono')) {
                $table->boolean('contrato_requiere_validacion_abono')->default(false)->after('contrato_periodo_servicio');
            }
            if (! Schema::hasColumn('ordencompra', 'contrato_validacion_plantilla_id')) {
                $table->unsignedBigInteger('contrato_validacion_plantilla_id')->nullable()
                    ->after('contrato_requiere_validacion_abono');
            }
            if (! Schema::hasColumn('ordencompra', 'contrato_exige_ingresos')) {
                $table->boolean('contrato_exige_ingresos')->default(false)
                    ->after('contrato_validacion_plantilla_id');
            }
            if (! Schema::hasColumn('ordencompra', 'contrato_minimo_ingresos')) {
                $table->unsignedSmallInteger('contrato_minimo_ingresos')->nullable()
                    ->after('contrato_exige_ingresos')
                    ->comment('Mínimo de tickets finalizados en el período; default 1');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('ordencompra')) {
            return;
        }

        foreach ([
            'contrato_minimo_ingresos',
            'contrato_exige_ingresos',
            'contrato_validacion_plantilla_id',
            'contrato_requiere_validacion_abono',
            'contrato_periodo_servicio',
        ] as $columna) {
            if (Schema::hasColumn('ordencompra', $columna)) {
                Schema::table('ordencompra', function (Blueprint $table) use ($columna) {
                    $table->dropColumn($columna);
                });
            }
        }
    }
};
