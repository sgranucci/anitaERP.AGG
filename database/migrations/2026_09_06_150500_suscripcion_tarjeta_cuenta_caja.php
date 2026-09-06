<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ata la tarjeta corporativa a su cuenta de caja y al tipo de transacción de egreso,
 * que es lo que necesita la conciliación para imputar el cargo en Ingresos y egresos.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('suscripcion_tarjeta')) {
            return;
        }

        Schema::table('suscripcion_tarjeta', function (Blueprint $table) {
            if (! Schema::hasColumn('suscripcion_tarjeta', 'cuentacaja_id')) {
                $table->unsignedBigInteger('cuentacaja_id')->nullable()->after('moneda_id')
                    ->comment('Cuenta de caja que representa el plástico en Ingresos y egresos');
            }
            if (! Schema::hasColumn('suscripcion_tarjeta', 'tipotransaccion_caja_id')) {
                $table->unsignedBigInteger('tipotransaccion_caja_id')->nullable()->after('cuentacaja_id')
                    ->comment('Tipo de transacción de egreso con el que se imputa el cargo');
            }
        });

        if (Schema::hasTable('cuentacaja')) {
            Schema::table('suscripcion_tarjeta', function (Blueprint $table) {
                $table->foreign('cuentacaja_id', 'fk_susc_tarjeta_cuentacaja')
                    ->references('id')->on('cuentacaja')
                    ->onDelete('set null')->onUpdate('restrict');
            });
        }

        if (Schema::hasTable('tipotransaccion_caja')) {
            Schema::table('suscripcion_tarjeta', function (Blueprint $table) {
                $table->foreign('tipotransaccion_caja_id', 'fk_susc_tarjeta_tipotrans')
                    ->references('id')->on('tipotransaccion_caja')
                    ->onDelete('set null')->onUpdate('restrict');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('suscripcion_tarjeta')) {
            return;
        }

        Schema::table('suscripcion_tarjeta', function (Blueprint $table) {
            foreach (['fk_susc_tarjeta_cuentacaja', 'fk_susc_tarjeta_tipotrans'] as $fk) {
                try {
                    $table->dropForeign($fk);
                } catch (\Throwable) {
                    // No existe si la tabla destino faltaba al migrar.
                }
            }
        });

        Schema::table('suscripcion_tarjeta', function (Blueprint $table) {
            foreach (['cuentacaja_id', 'tipotransaccion_caja_id'] as $col) {
                if (Schema::hasColumn('suscripcion_tarjeta', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
