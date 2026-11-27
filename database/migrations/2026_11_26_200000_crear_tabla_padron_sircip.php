<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Padrón único SIRCIP (Comisión Arbitral). Un solo archivo mensual para todas
 * las jurisdicciones: la letra de alícuota y el CRC son del contribuyente, y el
 * campo 7 (25 dígitos) trae el estado en cada jurisdicción 901…924.
 *
 * Una fila por período + CUIT. No es una tabla por provincia.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('padron_sircip')) {
            return;
        }

        Schema::create('padron_sircip', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->char('periodo', 6);
            $table->char('cuit', 11);
            $table->string('razon_social', 255)->nullable();
            $table->unsignedSmallInteger('jurisdiccion_sede')->nullable();
            $table->unsignedTinyInteger('crc');
            $table->char('alicuota_letra', 1);
            $table->char('campo7', 25);
            $table->timestamps();

            $table->unique(['periodo', 'cuit'], 'uq_padron_sircip_periodo_cuit');
            $table->index('cuit', 'ix_padron_sircip_cuit');
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_spanish_ci';
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('padron_sircip');
    }
};
