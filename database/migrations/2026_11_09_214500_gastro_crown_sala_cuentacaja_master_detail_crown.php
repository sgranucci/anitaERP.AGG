<?php

use App\Support\Configuracion\EntornoEmpresaSupport;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Crown: mapa sala → N cuentas de caja (efectivo MN/ME, tarjetas especiales por sala).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! EntornoEmpresaSupport::esCrown()) {
            return;
        }

        Schema::dropIfExists('gastro_crown_sala_cuentacaja');

        Schema::create('gastro_crown_sala_cuentacaja', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('sala_id');
            $table->unsignedBigInteger('cuentacaja_id');
            $table->boolean('es_default_f5')->default(false);
            $table->unsignedSmallInteger('orden')->default(0);
            $table->timestamps();
            $table->unique(['sala_id', 'cuentacaja_id']);
            $table->index(['sala_id', 'es_default_f5']);
            $table->index('cuentacaja_id');
        });
    }

    public function down(): void
    {
        if (! EntornoEmpresaSupport::esCrown()) {
            return;
        }

        Schema::dropIfExists('gastro_crown_sala_cuentacaja');

        Schema::create('gastro_crown_sala_cuentacaja', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('sala_id');
            $table->unsignedBigInteger('cuentacaja_efectivo_id');
            $table->timestamps();
            $table->unique('sala_id');
            $table->index('cuentacaja_efectivo_id');
        });
    }
};
