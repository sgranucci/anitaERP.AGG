<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cotización de tesorería (Anita cotiz_tes): tasas compra/venta monedas 2..9 por fecha.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('cotizacion_tesoreria')) {
            return;
        }

        Schema::create('cotizacion_tesoreria', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->date('fecha');
            $table->unsignedInteger('fecha_anita')->nullable();
            $table->string('fecha_alfa', 10)->nullable();

            foreach ([2, 3, 4, 5, 6, 7, 8, 9] as $codigo) {
                $table->decimal('cambio_compra_'.$codigo, 18, 6)->nullable();
                $table->decimal('cambio_venta_'.$codigo, 18, 6)->nullable();
            }

            $table->unsignedInteger('usuario_id')->nullable();
            $table->timestamps();

            $table->unique('fecha', 'uq_cotizacion_tesoreria_fecha');
            $table->unique('fecha_anita', 'uq_cotizacion_tesoreria_fecha_anita');
            $table->index('fecha_alfa', 'idx_cotizacion_tesoreria_fecha_alfa');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cotizacion_tesoreria');
    }
};
