<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Historia del delta mayor − deuda de clientes, por empresa y cuenta.
 * El auditor diario la usa para avisar solo cuando ese delta se mueve.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('cliente_deuda_mayor_delta')) {
            return;
        }

        Schema::create('cliente_deuda_mayor_delta', function (Blueprint $table) {
            $table->id();
            $table->date('fecha');
            $table->unsignedBigInteger('empresa_id');
            $table->unsignedBigInteger('cuentacontable_id');
            $table->decimal('deuda_local', 18, 2);
            $table->decimal('mayor_local', 18, 2);
            $table->decimal('delta', 18, 2);
            $table->timestamps();

            $table->unique(
                ['fecha', 'empresa_id', 'cuentacontable_id'],
                'cliente_deuda_mayor_delta_dia_unico'
            );
            $table->index(['empresa_id', 'fecha'], 'cliente_deuda_mayor_delta_empresa_fecha');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cliente_deuda_mayor_delta');
    }
};
