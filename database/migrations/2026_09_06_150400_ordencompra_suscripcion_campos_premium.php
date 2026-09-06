<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cierra el modelo de la suscripción con lo que exige la conciliación:
 * la tarjeta como referencia al maestro, el dueño del servicio (para que ninguna
 * suscripción quede huérfana) y la marca de desvío abierto que alimenta el estado
 * del listado sin recorrer los cargos en cada fila.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('ordencompra') || ! Schema::hasColumn('ordencompra', 'es_suscripcion')) {
            return;
        }

        Schema::table('ordencompra', function (Blueprint $table) {
            if (! Schema::hasColumn('ordencompra', 'suscripcion_tarjeta_id')) {
                $table->unsignedBigInteger('suscripcion_tarjeta_id')->nullable()->after('suscripcion_tarjeta_ult4')
                    ->comment('Tarjeta del maestro; ult4 queda como respaldo histórico');
            }
            if (! Schema::hasColumn('ordencompra', 'suscripcion_owner_usuario_id')) {
                $table->unsignedBigInteger('suscripcion_owner_usuario_id')->nullable()->after('suscripcion_solicitante')
                    ->comment('Dueño del servicio: a quién se le pregunta si sigue en uso');
            }
            if (! Schema::hasColumn('ordencompra', 'suscripcion_desvio_abierto')) {
                $table->boolean('suscripcion_desvio_abierto')->default(false)->after('suscripcion_borrador')
                    ->comment('Hay un cargo conciliado fuera de tolerancia sin resolver');
            }
        });

        if (Schema::hasTable('suscripcion_tarjeta')) {
            Schema::table('ordencompra', function (Blueprint $table) {
                $table->foreign('suscripcion_tarjeta_id', 'fk_oc_suscripcion_tarjeta')
                    ->references('id')->on('suscripcion_tarjeta')
                    ->onDelete('set null')->onUpdate('restrict');
            });
        }

        Schema::table('ordencompra', function (Blueprint $table) {
            $table->foreign('suscripcion_owner_usuario_id', 'fk_oc_suscripcion_owner')
                ->references('id')->on('usuario')
                ->onDelete('set null')->onUpdate('restrict');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('ordencompra')) {
            return;
        }

        Schema::table('ordencompra', function (Blueprint $table) {
            foreach (['fk_oc_suscripcion_tarjeta', 'fk_oc_suscripcion_owner'] as $fk) {
                try {
                    $table->dropForeign($fk);
                } catch (\Throwable) {
                    // La FK puede no existir si la tabla destino faltaba al migrar.
                }
            }
        });

        $cols = ['suscripcion_tarjeta_id', 'suscripcion_owner_usuario_id', 'suscripcion_desvio_abierto'];

        Schema::table('ordencompra', function (Blueprint $table) use ($cols) {
            foreach ($cols as $col) {
                if (Schema::hasColumn('ordencompra', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
