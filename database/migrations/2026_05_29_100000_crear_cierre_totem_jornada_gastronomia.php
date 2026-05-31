<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cierre_totem_jornada_gastronomia', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('jornada_gastronomia_id');
            $table->unsignedBigInteger('empresa_id');
            $table->unsignedInteger('ticket_movimiento_id_anterior')->default(0);
            $table->unsignedInteger('ticket_movimiento_id_desde')->nullable();
            $table->unsignedInteger('ticket_movimiento_id_hasta')->nullable();
            $table->unsignedInteger('cantidad_lineas')->default(0);
            $table->decimal('total_montoticket', 14, 2)->default(0);
            $table->unsignedInteger('cantidad_pendiente_anita')->default(0);
            $table->unsignedInteger('cantidad_canjeado_anita')->default(0);
            $table->unsignedInteger('cantidad_canjeado_erp')->default(0);
            $table->json('detalle_json')->nullable();
            $table->boolean('detalle_truncado')->default(false);
            $table->unsignedBigInteger('usuario_id')->nullable();
            $table->timestamps();

            $table->foreign('jornada_gastronomia_id', 'fk_cierre_totem_jornada')
                ->references('id')->on('jornada_gastronomia')->onDelete('cascade');
            $table->foreign('empresa_id', 'fk_cierre_totem_empresa')
                ->references('id')->on('empresa');
            $table->foreign('usuario_id', 'fk_cierre_totem_usuario')
                ->references('id')->on('usuario');
            $table->unique('jornada_gastronomia_id', 'uq_cierre_totem_jornada');
            $table->index(['empresa_id', 'ticket_movimiento_id_hasta'], 'idx_cierre_totem_empresa_hasta');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cierre_totem_jornada_gastronomia');
    }
};
