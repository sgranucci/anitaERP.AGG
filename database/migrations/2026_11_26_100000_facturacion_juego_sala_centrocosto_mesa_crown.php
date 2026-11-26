<?php

use App\Support\Configuracion\EntornoEmpresaSupport;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Crown: centro de costo propio de MESAS en facturación juego.
 *
 * Hasta ahora el asiento de mesas, tragamonedas y ruleta usaba un único
 * facturacion_juego_sala.centrocosto_id (CC de SLOTS sembrado desde Anita),
 * y las mesas de octubre 2026 quedaron imputadas a SLOTS.
 *
 * Solo agrega la columna. No carga datos: los CC de juego se cargan en la
 * pantalla de configuración (o con el comando de corrección, con OK).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! EntornoEmpresaSupport::esCrown()) {
            return;
        }

        if (! Schema::hasTable('facturacion_juego_sala')
            || Schema::hasColumn('facturacion_juego_sala', 'centrocosto_mesa_id')) {
            return;
        }

        Schema::table('facturacion_juego_sala', function (Blueprint $table) {
            $table->unsignedBigInteger('centrocosto_mesa_id')->nullable()->after('centrocosto_id');
        });
    }

    public function down(): void
    {
        if (! EntornoEmpresaSupport::esCrown()) {
            return;
        }

        if (Schema::hasTable('facturacion_juego_sala')
            && Schema::hasColumn('facturacion_juego_sala', 'centrocosto_mesa_id')) {
            Schema::table('facturacion_juego_sala', function (Blueprint $table) {
                $table->dropColumn('centrocosto_mesa_id');
            });
        }
    }
};
