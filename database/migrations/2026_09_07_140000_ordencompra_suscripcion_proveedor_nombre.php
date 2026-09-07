<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Suscripciones con tarjeta: el proveedor puede no estar en el padrón
 * (nombre libre en suscripcion_proveedor_nombre; proveedor_id queda null).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('ordencompra')) {
            return;
        }

        Schema::table('ordencompra', function (Blueprint $table) {
            if (! Schema::hasColumn('ordencompra', 'suscripcion_proveedor_nombre')) {
                $table->string('suscripcion_proveedor_nombre', 180)->nullable()
                    ->after('suscripcion_nombre')
                    ->comment('Nombre libre si no hay proveedor en el padrón');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('ordencompra')) {
            return;
        }

        Schema::table('ordencompra', function (Blueprint $table) {
            if (Schema::hasColumn('ordencompra', 'suscripcion_proveedor_nombre')) {
                $table->dropColumn('suscripcion_proveedor_nombre');
            }
        });
    }
};
