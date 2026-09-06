<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Maestro de tarjetas corporativas.
 *
 * Los últimos 4 dígitos son la clave con la que el resumen del emisor se cruza
 * contra las OC abiertas de suscripción, así que necesitan dueño y área propios
 * en lugar de vivir como texto libre en cada suscripción.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('suscripcion_tarjeta')) {
            return;
        }

        Schema::create('suscripcion_tarjeta', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('empresa_id');
            $table->string('ult4', 4)->comment('Últimos 4 dígitos impresos en el plástico');
            $table->string('etiqueta', 80)->comment('Nombre interno, ej. Visa Marketing');
            $table->string('emisor', 60)->nullable()->comment('Visa / Amex / Mastercard');
            $table->string('area', 80)->nullable();
            $table->unsignedBigInteger('centrocosto_id')->nullable();
            $table->unsignedBigInteger('responsable_usuario_id')->nullable()
                ->comment('Dueño del plástico; recibe los avisos de cargos sin identificar');
            $table->unsignedBigInteger('moneda_id')->nullable()->comment('Moneda de liquidación habitual');
            $table->decimal('limite_mensual', 15, 4)->nullable();
            $table->boolean('activo')->default(true);
            $table->string('observacion', 255)->nullable();
            $table->timestamps();

            $table->unique(['empresa_id', 'ult4'], 'uq_suscripcion_tarjeta_empresa_ult4');
            $table->index('activo');

            $table->foreign('empresa_id', 'fk_susc_tarjeta_empresa')
                ->references('id')->on('empresa')
                ->onDelete('restrict')->onUpdate('restrict');
            $table->foreign('centrocosto_id', 'fk_susc_tarjeta_centrocosto')
                ->references('id')->on('centrocosto')
                ->onDelete('set null')->onUpdate('restrict');
            $table->foreign('responsable_usuario_id', 'fk_susc_tarjeta_responsable')
                ->references('id')->on('usuario')
                ->onDelete('set null')->onUpdate('restrict');
            $table->foreign('moneda_id', 'fk_susc_tarjeta_moneda')
                ->references('id')->on('moneda')
                ->onDelete('set null')->onUpdate('restrict');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('suscripcion_tarjeta');
    }
};
