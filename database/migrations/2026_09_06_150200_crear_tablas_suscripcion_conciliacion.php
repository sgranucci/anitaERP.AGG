<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Conciliación mensual del resumen de tarjeta corporativa contra las OC abiertas.
 *
 * `suscripcion_conciliacion` es el período importado (una cabecera por empresa y mes)
 * y `suscripcion_cargo` cada línea del resumen con su resultado de cruce. El cargo es
 * la unidad que después se imputa en Ingresos y egresos.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('suscripcion_conciliacion')) {
            Schema::create('suscripcion_conciliacion', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('empresa_id');
                $table->string('periodo', 7)->comment('YYYY-MM del resumen');
                $table->date('fecha_desde')->nullable();
                $table->date('fecha_hasta')->nullable();
                $table->string('estado', 20)->default('ABIERTA')->comment('ABIERTA|CERRADA');
                $table->string('archivo_nombre', 255)->nullable();
                $table->unsignedInteger('filas_importadas')->default(0);
                $table->unsignedBigInteger('importo_usuario_id')->nullable();
                $table->dateTime('importado_at')->nullable();
                $table->unsignedBigInteger('cerro_usuario_id')->nullable();
                $table->dateTime('cerrado_at')->nullable();
                $table->string('observacion', 255)->nullable();
                $table->timestamps();

                $table->unique(['empresa_id', 'periodo'], 'uq_susc_concil_empresa_periodo');

                $table->foreign('empresa_id', 'fk_susc_concil_empresa')
                    ->references('id')->on('empresa')
                    ->onDelete('restrict')->onUpdate('restrict');
                $table->foreign('importo_usuario_id', 'fk_susc_concil_importo')
                    ->references('id')->on('usuario')
                    ->onDelete('set null')->onUpdate('restrict');
                $table->foreign('cerro_usuario_id', 'fk_susc_concil_cerro')
                    ->references('id')->on('usuario')
                    ->onDelete('set null')->onUpdate('restrict');
            });
        }

        if (! Schema::hasTable('suscripcion_cargo')) {
            Schema::create('suscripcion_cargo', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('suscripcion_conciliacion_id');
                $table->date('fecha');
                $table->string('comercio', 180)->comment('Descripción tal como viene del emisor');
                $table->string('comercio_normalizado', 180)->nullable()->comment('Base del matcheo por alias');
                $table->string('tarjeta_ult4', 4)->nullable();
                $table->unsignedBigInteger('suscripcion_tarjeta_id')->nullable();
                $table->decimal('monto', 15, 4);
                $table->unsignedBigInteger('moneda_id')->nullable();
                $table->unsignedBigInteger('ordencompra_id')->nullable()->comment('OC abierta de suscripción asociada');
                $table->string('estado', 24)->default('SIN_IDENTIFICAR')
                    ->comment('CONCILIADO|DESVIO|SIN_IDENTIFICAR|PENDIENTE_APROBACION|REGULARIZAR|DESCARTADO');
                $table->decimal('desvio_pct', 8, 2)->nullable()->comment('Positivo = por encima del monto autorizado');
                $table->string('origen_match', 20)->nullable()->comment('AUTO|ALIAS|MANUAL');
                $table->unsignedBigInteger('caja_movimiento_id')->nullable()->comment('Imputación en Ingresos y egresos');
                $table->unsignedBigInteger('asocio_usuario_id')->nullable();
                $table->dateTime('asociado_at')->nullable();
                $table->string('observacion', 255)->nullable();
                $table->string('hash_linea', 64)->nullable()->comment('Evita duplicar filas al reimportar el mismo resumen');
                $table->timestamps();

                $table->index(['suscripcion_conciliacion_id', 'estado'], 'ix_susc_cargo_concil_estado');
                $table->index('ordencompra_id', 'ix_susc_cargo_oc');
                $table->index('comercio_normalizado', 'ix_susc_cargo_comercio');
                $table->unique(['suscripcion_conciliacion_id', 'hash_linea'], 'uq_susc_cargo_hash');

                $table->foreign('suscripcion_conciliacion_id', 'fk_susc_cargo_concil')
                    ->references('id')->on('suscripcion_conciliacion')
                    ->onDelete('cascade')->onUpdate('restrict');
                $table->foreign('suscripcion_tarjeta_id', 'fk_susc_cargo_tarjeta')
                    ->references('id')->on('suscripcion_tarjeta')
                    ->onDelete('set null')->onUpdate('restrict');
                $table->foreign('ordencompra_id', 'fk_susc_cargo_oc')
                    ->references('id')->on('ordencompra')
                    ->onDelete('set null')->onUpdate('restrict');
                $table->foreign('moneda_id', 'fk_susc_cargo_moneda')
                    ->references('id')->on('moneda')
                    ->onDelete('set null')->onUpdate('restrict');
                $table->foreign('asocio_usuario_id', 'fk_susc_cargo_usuario')
                    ->references('id')->on('usuario')
                    ->onDelete('set null')->onUpdate('restrict');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('suscripcion_cargo');
        Schema::dropIfExists('suscripcion_conciliacion');
    }
};
