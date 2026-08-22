<?php

use App\Support\Configuracion\EntornoEmpresaSupport;
use App\Support\Contable\CuentaAutomaticaClaves;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Cuenta global de anticipos a proveedores: habilita el contraasiento
 * Debe proveedores / Haber anticipos al aplicar una OP adelantada a una factura.
 *
 * Se da de alta la clave en todas las empresas (vacía = sin cuenta separada) y
 * se precarga 114040001 ANTICIPOS A PROVEEDORES en las empresas operativas de AGG.
 */
return new class extends Migration
{
    private const CLAVE = CuentaAutomaticaClaves::PAGO_ANTICIPO_PROVEEDOR;

    private const CODIGO_ANTICIPO_AGG = '114040001';

    /** Biyemas, Kandiko, Rebisco. */
    private const EMPRESAS_AGG = [1, 2, 3];

    public function up(): void
    {
        if (! Schema::hasTable('contabilidad_cuenta_automatica')) {
            return;
        }

        $precargaAgg = EntornoEmpresaSupport::esAgg();

        foreach (DB::table('empresa')->pluck('id') as $id) {
            $empresaId = (int) $id;
            if ($empresaId <= 0) {
                continue;
            }

            $cuentaId = ($precargaAgg && in_array($empresaId, self::EMPRESAS_AGG, true))
                ? $this->cuentaIdPorCodigo($empresaId, self::CODIGO_ANTICIPO_AGG)
                : null;

            $existente = DB::table('contabilidad_cuenta_automatica')
                ->where('empresa_id', $empresaId)
                ->where('clave', self::CLAVE)
                ->first();

            if ($existente === null) {
                DB::table('contabilidad_cuenta_automatica')->insert([
                    'empresa_id' => $empresaId,
                    'clave' => self::CLAVE,
                    'cuentacontable_id' => $cuentaId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                continue;
            }

            // No pisar una cuenta ya elegida en el ABM.
            if ($cuentaId === null || (int) ($existente->cuentacontable_id ?? 0) > 0) {
                continue;
            }

            DB::table('contabilidad_cuenta_automatica')
                ->where('id', $existente->id)
                ->update([
                    'cuentacontable_id' => $cuentaId,
                    'updated_at' => now(),
                ]);
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('contabilidad_cuenta_automatica')) {
            return;
        }

        DB::table('contabilidad_cuenta_automatica')
            ->where('clave', self::CLAVE)
            ->delete();
    }

    private function cuentaIdPorCodigo(int $empresaId, string $codigo): ?int
    {
        if (! Schema::hasTable('cuentacontable')) {
            return null;
        }

        $id = (int) (DB::table('cuentacontable')
            ->where('empresa_id', $empresaId)
            ->where('codigo', $codigo)
            ->value('id') ?? 0);

        return $id > 0 ? $id : null;
    }
};
