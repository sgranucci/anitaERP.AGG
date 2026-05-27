<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('rendicion_gastronomia_caja', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('codigo', 50);
            $table->unsignedBigInteger('empresa_id');
            $table->foreign('empresa_id', 'fk_rendicion_gastronomia_caja_empresa')->references('id')->on('empresa')->onDelete('restrict')->onUpdate('restrict');
            $table->unsignedBigInteger('puntoventa_cae_id');
            $table->foreign('puntoventa_cae_id', 'fk_rendicion_gastronomia_caja_puntoventa_cae')->references('id')->on('puntoventa')->onDelete('restrict')->onUpdate('restrict');
            $table->unsignedBigInteger('puntoventa_caea_id');
            $table->foreign('puntoventa_caea_id', 'fk_rendicion_gastronomia_caja_puntoventa_caea')->references('id')->on('puntoventa')->onDelete('restrict')->onUpdate('restrict');
            $table->unsignedBigInteger('caja_id');
            $table->foreign('caja_id', 'fk_rendicion_gastronomia_caja_caja')->references('id')->on('caja')->onDelete('restrict')->onUpdate('restrict');
            $table->unsignedBigInteger('creousuario_id');
            $table->foreign('creousuario_id', 'fk_rendicion_gastronomia_caja_usuario')->references('id')->on('usuario')->onDelete('restrict')->onUpdate('restrict');
            $table->datetime('fecharendicion');
            $table->decimal('iniciodelfondo', 22, 2);
            $table->decimal('totalfactura', 22, 2);
            $table->decimal('totalcobrado', 22, 2);
            $table->decimal('totalinvitacion', 22, 2);
            $table->decimal('totalnotacredito', 22, 2);
            $table->decimal('totalredondeo', 22, 2);
            $table->decimal('totalredondeoinvitacion', 22, 2);
            $table->decimal('sobrantefaltante', 22, 2);
            $table->unsignedBigInteger('turno_operativo_gastronomia_id');
            $table->foreign('turno_operativo_gastronomia_id', 'fk_rendicion_gastronomia_caja_turno_operativo_gastronomia')->references('id')->on('turno_operativo_gastronomia')->onDelete('restrict')->onUpdate('restrict');
            $table->text('observacion')->nullable();
            $table->timestamps();
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_spanish_ci';
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('rendicion_gastronomia_caja');
    }
};
