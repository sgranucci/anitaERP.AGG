<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('maquinavending_rendicion', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('empresa_id');
            $table->unsignedBigInteger('maquinavending_id');
            $table->unsignedInteger('numero_cierre');
            $table->dateTime('fecha_rendicion');
            $table->date('fecha_jornada')->nullable();
            $table->decimal('total_ventas', 15, 2)->default(0);
            $table->decimal('total_cobrado', 15, 2)->default(0);
            $table->text('observacion')->nullable();
            $table->unsignedBigInteger('usuario_id');
            $table->timestamps();
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_spanish_ci';

            $table->foreign('empresa_id', 'fk_mv_rendicion_empresa')
                ->references('id')->on('empresa')
                ->onDelete('restrict')->onUpdate('restrict');

            $table->foreign('maquinavending_id', 'fk_mv_rendicion_maquina')
                ->references('id')->on('maquinavending')
                ->onDelete('restrict')->onUpdate('restrict');

            $table->foreign('usuario_id', 'fk_mv_rendicion_usuario')
                ->references('id')->on('usuario')
                ->onDelete('restrict')->onUpdate('restrict');

            $table->unique(['maquinavending_id', 'numero_cierre'], 'uq_mv_rendicion_maquina_numero');
            $table->index(['empresa_id', 'fecha_rendicion'], 'idx_mv_rendicion_empresa_fecha');
        });

        Schema::create('maquinavending_rendicion_articulo', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('maquinavending_rendicion_id');
            $table->unsignedInteger('numero_rulo');
            $table->unsignedBigInteger('articulo_id');
            $table->decimal('cantidad', 15, 3)->default(0);
            $table->decimal('precio_lista', 15, 2)->default(0);
            $table->decimal('importe_total', 15, 2)->default(0);
            $table->timestamps();
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_spanish_ci';

            $table->foreign('maquinavending_rendicion_id', 'fk_mv_rend_art_rendicion')
                ->references('id')->on('maquinavending_rendicion')
                ->onDelete('cascade')->onUpdate('restrict');

            $table->foreign('articulo_id', 'fk_mv_rend_art_articulo')
                ->references('id')->on('articulo')
                ->onDelete('restrict')->onUpdate('restrict');

            $table->index(['maquinavending_rendicion_id', 'numero_rulo'], 'idx_mv_rend_art_rend_rulo');
        });

        Schema::create('maquinavending_rendicion_medio_pago', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('maquinavending_rendicion_id');
            $table->unsignedBigInteger('cuentacaja_id');
            $table->decimal('monto', 15, 2)->default(0);
            $table->decimal('cotizacion', 15, 4)->default(1);
            $table->timestamps();
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_spanish_ci';

            $table->foreign('maquinavending_rendicion_id', 'fk_mv_rend_mp_rendicion')
                ->references('id')->on('maquinavending_rendicion')
                ->onDelete('cascade')->onUpdate('restrict');

            $table->foreign('cuentacaja_id', 'fk_mv_rend_mp_cuentacaja')
                ->references('id')->on('cuentacaja')
                ->onDelete('restrict')->onUpdate('restrict');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('maquinavending_rendicion_medio_pago');
        Schema::dropIfExists('maquinavending_rendicion_articulo');
        Schema::dropIfExists('maquinavending_rendicion');
    }
};
