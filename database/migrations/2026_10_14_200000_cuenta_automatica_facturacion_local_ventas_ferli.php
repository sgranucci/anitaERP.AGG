<?php

use App\Support\Configuracion\EntornoEmpresaSupport;
use App\Support\Contable\CuentaAutomaticaClaves;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Ferli: el neto de locales (POS, Tiendanube, Facturante) va a L-VENTAS (411000003).
 * Solo inserta la clave facturacion_local.ventas. No toca el resto del catálogo.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! EntornoEmpresaSupport::esFerli() || ! Schema::hasTable('contabilidad_cuenta_automatica')) {
            return;
        }

        $empresaIds = DB::table('cuentacontable')
            ->where('codigo', '411000003')
            ->distinct()
            ->pluck('empresa_id');

        foreach ($empresaIds as $empresaId) {
            $empresaId = (int) $empresaId;
            $cuentaId = (int) (DB::table('cuentacontable')
                ->where('empresa_id', $empresaId)
                ->where('codigo', '411000003')
                ->value('id') ?? 0);
            if ($cuentaId <= 0) {
                continue;
            }

            $existe = DB::table('contabilidad_cuenta_automatica')
                ->where('empresa_id', $empresaId)
                ->where('clave', CuentaAutomaticaClaves::FACTURACION_LOCAL_VENTAS)
                ->exists();

            if ($existe) {
                DB::table('contabilidad_cuenta_automatica')
                    ->where('empresa_id', $empresaId)
                    ->where('clave', CuentaAutomaticaClaves::FACTURACION_LOCAL_VENTAS)
                    ->where(function ($q) {
                        $q->whereNull('cuentacontable_id')->orWhere('cuentacontable_id', 0);
                    })
                    ->update([
                        'cuentacontable_id' => $cuentaId,
                        'updated_at' => now(),
                    ]);

                continue;
            }

            DB::table('contabilidad_cuenta_automatica')->insert([
                'empresa_id' => $empresaId,
                'clave' => CuentaAutomaticaClaves::FACTURACION_LOCAL_VENTAS,
                'cuentacontable_id' => $cuentaId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        if (! EntornoEmpresaSupport::esFerli() || ! Schema::hasTable('contabilidad_cuenta_automatica')) {
            return;
        }

        DB::table('contabilidad_cuenta_automatica')
            ->where('clave', CuentaAutomaticaClaves::FACTURACION_LOCAL_VENTAS)
            ->delete();
    }
};
