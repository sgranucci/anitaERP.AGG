<?php

use App\Support\Configuracion\EntornoEmpresaSupport;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ledger de fichas para poder público (l-stockfichm): catálogo, conversión diaria,
 * movimientos agregados y cierres/banca. El Generador deja de leer Anita en vivo.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! EntornoEmpresaSupport::esCrown()) {
            return;
        }

        if (! Schema::hasTable('tesoreria_crown_ficha')) {
            Schema::create('tesoreria_crown_ficha', function (Blueprint $table) {
                $table->id();
                $table->unsignedInteger('codigo')->unique();
                $table->string('serie', 20)->default('');
                $table->decimal('valor', 18, 2)->default(0);
                $table->unsignedInteger('orden_carga')->default(0);
                $table->boolean('ficha_canje')->default(false);
                $table->string('desc_color', 40)->default('');
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('tesoreria_crown_conversion_ficha')) {
            Schema::create('tesoreria_crown_conversion_ficha', function (Blueprint $table) {
                $table->id();
                $table->date('fecha');
                $table->unsignedInteger('codigo_ficha');
                $table->integer('conv_final')->default(0);
                $table->timestamps();
                $table->unique(['fecha', 'codigo_ficha'], 'tc_conv_ficha_fecha_codigo_uq');
                $table->index('fecha');
            });
        }

        if (! Schema::hasTable('tesoreria_crown_ficha_movimiento')) {
            Schema::create('tesoreria_crown_ficha_movimiento', function (Blueprint $table) {
                $table->id();
                $table->date('fecha');
                $table->string('origen', 16); // mesa|cajero|canje|otros
                $table->unsignedInteger('codigo_ficha');
                $table->char('tipo_oper', 1)->default('');
                $table->integer('cantidad')->default(0);
                $table->timestamps();
                $table->unique(
                    ['fecha', 'origen', 'codigo_ficha', 'tipo_oper'],
                    'tc_ficha_mov_uq'
                );
                $table->index(['fecha', 'origen']);
            });
        }

        if (! Schema::hasTable('tesoreria_crown_ficha_cierre')) {
            Schema::create('tesoreria_crown_ficha_cierre', function (Blueprint $table) {
                $table->id();
                $table->date('fecha');
                $table->unsignedInteger('codigo_ficha');
                $table->integer('reserva')->default(0);
                $table->timestamps();
                $table->unique(['fecha', 'codigo_ficha'], 'tc_ficha_cierre_uq');
            });
        }

        if (! Schema::hasTable('tesoreria_crown_ficha_banca')) {
            Schema::create('tesoreria_crown_ficha_banca', function (Blueprint $table) {
                $table->id();
                $table->unsignedInteger('codigo_ficha')->unique();
                $table->integer('cantidad')->default(0);
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        if (! EntornoEmpresaSupport::esCrown()) {
            return;
        }

        Schema::dropIfExists('tesoreria_crown_ficha_banca');
        Schema::dropIfExists('tesoreria_crown_ficha_cierre');
        Schema::dropIfExists('tesoreria_crown_ficha_movimiento');
        Schema::dropIfExists('tesoreria_crown_conversion_ficha');
        Schema::dropIfExists('tesoreria_crown_ficha');
    }
};
