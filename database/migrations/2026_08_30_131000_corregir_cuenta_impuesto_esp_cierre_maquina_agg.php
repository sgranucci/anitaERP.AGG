<?php

use App\Support\Configuracion\EntornoEmpresaSupport;
use App\Support\Contable\CuentaAutomaticaClaves;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * p-vtamaquina.c lee_impcont(467) → 521120010 IMPUESTO ESPECIFICO (no 214010028 pasivo).
 */
return new class extends Migration
{
    private const CODIGO_ANITA = '521120010';

    public function up(): void
    {
        if (! EntornoEmpresaSupport::esAgg()) {
            return;
        }
        if (! Schema::hasTable('contabilidad_cuenta_automatica') || ! Schema::hasTable('cuentacontable')) {
            return;
        }

        $clave = CuentaAutomaticaClaves::CIERRE_MAQUINA_IMPUESTO_ESP;
        $empresas = DB::table('empresa')->pluck('id');
        foreach ($empresas as $empresaId) {
            $empresaId = (int) $empresaId;
            $cuentaId = (int) (DB::table('cuentacontable')
                ->where('empresa_id', $empresaId)
                ->where('codigo', self::CODIGO_ANITA)
                ->value('id') ?? 0);
            if ($cuentaId <= 0) {
                continue;
            }

            DB::table('contabilidad_cuenta_automatica')
                ->where('empresa_id', $empresaId)
                ->where('clave', $clave)
                ->update([
                    'cuentacontable_id' => $cuentaId,
                    'updated_at' => now(),
                ]);
        }
    }

    public function down(): void
    {
        // No revertir: el código anterior (214010028) era un guess incorrecto.
    }
};
