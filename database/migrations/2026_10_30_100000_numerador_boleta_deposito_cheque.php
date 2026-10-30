<?php

use App\Support\Caja\ChequeDepositoBoletaSupport;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * Numerador nativo de boleta de depósito CHT y carga de las boletas que quedaron vacías.
 * Una boleta = misma empresa, fecha y cuenta. El piso es el mayor número ya grabado.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('cheque') || ! Schema::hasTable('sistema_numerador')) {
            return;
        }

        ChequeDepositoBoletaSupport::completarFaltantes();
    }

    public function down(): void
    {
        // Los números ya asignados quedan: son la trazabilidad del depósito.
    }
};
