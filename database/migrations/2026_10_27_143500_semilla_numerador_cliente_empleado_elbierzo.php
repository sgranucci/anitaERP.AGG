<?php

use App\Support\Configuracion\EntornoEmpresaSupport;
use App\Support\Ventas\ClienteCodigoEmpleadoBierzoSupport;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * EL BIERZO: numerador ERP de clientes empleado (14000–14500).
 * Semilla = último código ya cargado en ese rango. No toca el numerador CLI de clientes.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! EntornoEmpresaSupport::esElBierzo()) {
            return;
        }

        if (! Schema::hasTable('sistema_numerador') || ! Schema::hasTable('cliente')) {
            return;
        }

        ClienteCodigoEmpleadoBierzoSupport::asegurarFila(
            ClienteCodigoEmpleadoBierzoSupport::ultimoCargadoEnRango()
        );
    }

    public function down(): void
    {
        if (! EntornoEmpresaSupport::esElBierzo()) {
            return;
        }

        if (! Schema::hasTable('sistema_numerador')) {
            return;
        }

        DB::table('sistema_numerador')
            ->where('codigo', ClienteCodigoEmpleadoBierzoSupport::CODIGO_NUMERADOR)
            ->delete();
    }
};
