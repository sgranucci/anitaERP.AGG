<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Serie fiscal local: un numerador por tipo ARCA + punto de venta.
 * 001 FAC A y 006 FAC B son series distintas; la letra no es clave.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('venta_serie_numerador')) {
            return;
        }

        Schema::create('venta_serie_numerador', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('empresa_id')->nullable();
            $table->unsignedBigInteger('puntoventa_id');
            $table->unsignedSmallInteger('codigo_afip');
            $table->unsignedInteger('ultimo_numero')->default(0);
            $table->unsignedInteger('piso')->default(0);
            $table->string('observacion', 255)->nullable();
            $table->timestamps();

            $table->unique(
                ['puntoventa_id', 'codigo_afip'],
                'venta_serie_numerador_pv_afip_unique'
            );
            $table->index('empresa_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('venta_serie_numerador');
    }
};
