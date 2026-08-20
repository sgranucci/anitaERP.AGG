<?php

use App\Support\Configuracion\EntornoEmpresaSupport;
use App\Support\Ventas\UsuarioPreferenciaFacturacionSupport;
use Illuminate\Database\Migrations\Migration;

/**
 * El Bierzo: preferencia de facturación por usuario.
 * clarisad/cdacurso → FAC + PV 00010 + remito 00001.
 * Resto → FAC + PV 00008 (prueba) + remito 00099 (prueba).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! EntornoEmpresaSupport::esElBierzo()) {
            return;
        }

        UsuarioPreferenciaFacturacionSupport::aplicarAsignacionPorPerfil(true);
    }

    public function down(): void
    {
        // No revierte: las columnas quedan con la última preferencia del usuario.
    }
};
