<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('maquinavending_rendicion', function (Blueprint $table) {
            $table->string('codigo', 50)->nullable()->after('numero_cierre');
            $table->unsignedBigInteger('nro_oper_anita')->nullable()->after('codigo');
            $table->string('fuente_nro_oper', 20)->nullable()->after('nro_oper_anita');
            $table->timestamp('anita_sincronizado_en')->nullable()->after('fuente_nro_oper');
        });

        Schema::create('rendicion_maquinavending_caja', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('codigo', 50);
            $table->unsignedBigInteger('maquinavending_rendicion_id');
            $table->unsignedBigInteger('empresa_id');
            $table->unsignedBigInteger('maquinavending_id');
            $table->unsignedBigInteger('puntoventa_cae_id');
            $table->unsignedBigInteger('puntoventa_caea_id');
            $table->unsignedBigInteger('caja_id');
            $table->unsignedBigInteger('creousuario_id');
            $table->dateTime('fecharendicion');
            $table->decimal('iniciodelfondo', 22, 2)->default(0);
            $table->decimal('totalfactura', 22, 2)->default(0);
            $table->decimal('totalcobrado', 22, 2)->default(0);
            $table->decimal('totalinvitacion', 22, 2)->default(0);
            $table->decimal('totalnotacredito', 22, 2)->default(0);
            $table->decimal('totalredondeo', 22, 2)->default(0);
            $table->decimal('totalredondeoinvitacion', 22, 2)->default(0);
            $table->decimal('sobrantefaltante', 22, 2)->default(0);
            $table->text('observacion')->nullable();
            $table->timestamps();
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_spanish_ci';

            $table->unique('maquinavending_rendicion_id', 'uq_rend_mv_caja_rendicion_ventas');
            $table->index(['empresa_id', 'fecharendicion'], 'idx_rend_mv_caja_empresa_fecha');

            $table->foreign('maquinavending_rendicion_id', 'fk_rend_mv_caja_rendicion')
                ->references('id')->on('maquinavending_rendicion')
                ->onDelete('restrict')->onUpdate('restrict');
            $table->foreign('empresa_id', 'fk_rend_mv_caja_empresa')
                ->references('id')->on('empresa')
                ->onDelete('restrict')->onUpdate('restrict');
            $table->foreign('maquinavending_id', 'fk_rend_mv_caja_maquina')
                ->references('id')->on('maquinavending')
                ->onDelete('restrict')->onUpdate('restrict');
            $table->foreign('puntoventa_cae_id', 'fk_rend_mv_caja_pv_cae')
                ->references('id')->on('puntoventa')
                ->onDelete('restrict')->onUpdate('restrict');
            $table->foreign('puntoventa_caea_id', 'fk_rend_mv_caja_pv_caea')
                ->references('id')->on('puntoventa')
                ->onDelete('restrict')->onUpdate('restrict');
            $table->foreign('caja_id', 'fk_rend_mv_caja_caja')
                ->references('id')->on('caja')
                ->onDelete('restrict')->onUpdate('restrict');
            $table->foreign('creousuario_id', 'fk_rend_mv_caja_usuario')
                ->references('id')->on('usuario')
                ->onDelete('restrict')->onUpdate('restrict');
        });

        Schema::create('rendicion_maquinavending_movimiento_caja', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('rendicion_maquinavending_caja_id');
            $table->unsignedBigInteger('cuentacaja_id');
            $table->decimal('monto', 15, 2)->default(0);
            $table->decimal('cotizacion', 15, 4)->default(1);
            $table->timestamps();
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_spanish_ci';

            $table->foreign('rendicion_maquinavending_caja_id', 'fk_rend_mv_mov_caja')
                ->references('id')->on('rendicion_maquinavending_caja')
                ->onDelete('cascade')->onUpdate('restrict');
            $table->foreign('cuentacaja_id', 'fk_rend_mv_mov_cuentacaja')
                ->references('id')->on('cuentacaja')
                ->onDelete('restrict')->onUpdate('restrict');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rendicion_maquinavending_movimiento_caja');
        Schema::dropIfExists('rendicion_maquinavending_caja');

        Schema::table('maquinavending_rendicion', function (Blueprint $table) {
            $table->dropColumn(['codigo', 'nro_oper_anita', 'fuente_nro_oper', 'anita_sincronizado_en']);
        });
    }
};
