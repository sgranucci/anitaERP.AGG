<?php

use App\Support\Compras\PagoproveedorAopReversoRetencionRepairSupport;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Log;

/**
 * Deploy: completa NC (retenciones negativas) en AOP de reversión desde 2026-10-01
 * en todas las empresas. Idempotente (solo AOP sin filas en pagoproveedor_retencion).
 *
 * El alta de nuevas reversiones ya clona retenciones en ERP
 * (PagoproveedorAnularRevertirService).
 */
return new class extends Migration
{
    public function up(): void
    {
        $resultado = PagoproveedorAopReversoRetencionRepairSupport::reparar(
            PagoproveedorAopReversoRetencionRepairSupport::DESDE_DEFAULT,
            null,
            false,
        );

        Log::info('migracion.reparar_retenciones_aop_reverso', $resultado);
    }

    public function down(): void
    {
        // No se revierten: borrar NC reparadas dejaría SICORE otra vez incompleto.
    }
};
