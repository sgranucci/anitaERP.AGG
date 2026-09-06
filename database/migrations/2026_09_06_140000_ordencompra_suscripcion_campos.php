<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Especializa OC contrato como suscripción (SaaS / tarjeta corporativa).
 * La suscripción vive en la OC; el módulo Suscripciones es la capa de proceso.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('ordencompra')) {
            return;
        }

        Schema::table('ordencompra', function (Blueprint $table) {
            if (! Schema::hasColumn('ordencompra', 'es_suscripcion')) {
                $table->boolean('es_suscripcion')->default(false)->after('es_contrato')
                    ->comment('OC abierta originada en el módulo de Suscripciones');
            }
            if (! Schema::hasColumn('ordencompra', 'suscripcion_nombre')) {
                $table->string('suscripcion_nombre', 180)->nullable()->after('es_suscripcion')
                    ->comment('Nombre del servicio / plan (ej. Adobe Creative Cloud)');
            }
            if (! Schema::hasColumn('ordencompra', 'suscripcion_periodicidad')) {
                $table->string('suscripcion_periodicidad', 20)->nullable()->after('suscripcion_nombre')
                    ->comment('mensual|anual');
            }
            if (! Schema::hasColumn('ordencompra', 'suscripcion_monto_periodo')) {
                $table->decimal('suscripcion_monto_periodo', 15, 4)->nullable()->after('suscripcion_periodicidad')
                    ->comment('Importe de referencia por período');
            }
            if (! Schema::hasColumn('ordencompra', 'suscripcion_tolerancia_pct')) {
                $table->decimal('suscripcion_tolerancia_pct', 6, 2)->nullable()->after('suscripcion_monto_periodo')
                    ->comment('Desvío admitido % antes de re-aprobación');
            }
            if (! Schema::hasColumn('ordencompra', 'suscripcion_tarjeta_ult4')) {
                $table->string('suscripcion_tarjeta_ult4', 4)->nullable()->after('suscripcion_tolerancia_pct')
                    ->comment('Últimos 4 dígitos de la tarjeta corporativa');
            }
            if (! Schema::hasColumn('ordencompra', 'suscripcion_area')) {
                $table->string('suscripcion_area', 80)->nullable()->after('suscripcion_tarjeta_ult4')
                    ->comment('Área solicitante (texto de negocio)');
            }
            if (! Schema::hasColumn('ordencompra', 'suscripcion_solicitante')) {
                $table->string('suscripcion_solicitante', 120)->nullable()->after('suscripcion_area');
            }
            if (! Schema::hasColumn('ordencompra', 'suscripcion_borrador')) {
                $table->boolean('suscripcion_borrador')->default(false)->after('suscripcion_solicitante')
                    ->comment('Alta guardada sin enviar al árbol de aprobación');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('ordencompra')) {
            return;
        }

        $cols = [
            'es_suscripcion',
            'suscripcion_nombre',
            'suscripcion_periodicidad',
            'suscripcion_monto_periodo',
            'suscripcion_tolerancia_pct',
            'suscripcion_tarjeta_ult4',
            'suscripcion_area',
            'suscripcion_solicitante',
            'suscripcion_borrador',
        ];

        Schema::table('ordencompra', function (Blueprint $table) use ($cols) {
            foreach ($cols as $col) {
                if (Schema::hasColumn('ordencompra', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
