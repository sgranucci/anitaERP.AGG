<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Diccionario de alias de comercios del resumen de tarjeta.
 *
 * El emisor informa "ADOBE *CREATIVE CLOUD" y la OC dice "Adobe Inc.": cada vez que
 * alguien asocia un cargo a mano se guarda el alias, de manera que el mismo comercio
 * se reconozca solo en los períodos siguientes.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('suscripcion_comercio_alias')) {
            return;
        }

        Schema::create('suscripcion_comercio_alias', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('empresa_id')->nullable()->comment('Null = alias válido para todo el grupo');
            $table->string('alias', 180)->comment('Comercio normalizado del resumen');
            $table->unsignedBigInteger('proveedor_id')->nullable();
            $table->unsignedBigInteger('ordencompra_id')->nullable()->comment('Suscripción concreta cuando el alias es unívoco');
            $table->unsignedInteger('veces_usado')->default(0);
            $table->dateTime('ultimo_uso_at')->nullable();
            $table->unsignedBigInteger('creousuario_id')->nullable();
            $table->timestamps();

            $table->unique(['empresa_id', 'alias'], 'uq_susc_alias_empresa_alias');
            $table->index('alias', 'ix_susc_alias_alias');

            $table->foreign('empresa_id', 'fk_susc_alias_empresa')
                ->references('id')->on('empresa')
                ->onDelete('cascade')->onUpdate('restrict');
            $table->foreign('proveedor_id', 'fk_susc_alias_proveedor')
                ->references('id')->on('proveedor')
                ->onDelete('set null')->onUpdate('restrict');
            $table->foreign('ordencompra_id', 'fk_susc_alias_oc')
                ->references('id')->on('ordencompra')
                ->onDelete('set null')->onUpdate('restrict');
            $table->foreign('creousuario_id', 'fk_susc_alias_usuario')
                ->references('id')->on('usuario')
                ->onDelete('set null')->onUpdate('restrict');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('suscripcion_comercio_alias');
    }
};
