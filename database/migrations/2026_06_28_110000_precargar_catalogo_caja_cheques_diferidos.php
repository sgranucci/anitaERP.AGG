<?php

use App\Support\Contable\CuentaAutomaticaClaves;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Catálogo central: cuenta CHEQUES DIFERIDOS (211010-013) por empresa.
 * La imputación posdatada sigue deshabilitada (CAJA_CHEQUE_PROPIO_IMPUTACION_DIFERIDOS=false).
 */
return new class extends Migration
{
    private const CODIGO_CHEQUES_DIFERIDOS = '211010013';

    public function up(): void
    {
        if (! Schema::hasTable('contabilidad_cuenta_automatica') || ! Schema::hasTable('cuentacontable')) {
            return;
        }

        $clave = CuentaAutomaticaClaves::CAJA_CHEQUES_DIFERIDOS;
        $empresaIds = DB::table('empresa')->orderBy('id')->pluck('id');

        foreach ($empresaIds as $empresaId) {
            $empresaId = (int) $empresaId;
            $cuentaId = $this->resolverCuentaPorCodigo($empresaId, self::CODIGO_CHEQUES_DIFERIDOS);

            $existente = DB::table('contabilidad_cuenta_automatica')
                ->where('empresa_id', $empresaId)
                ->where('clave', $clave)
                ->first();

            if ($existente !== null) {
                DB::table('contabilidad_cuenta_automatica')
                    ->where('id', $existente->id)
                    ->update([
                        'cuentacontable_id' => $cuentaId,
                        'updated_at' => now(),
                    ]);
            } else {
                DB::table('contabilidad_cuenta_automatica')->insert([
                    'empresa_id' => $empresaId,
                    'clave' => $clave,
                    'cuentacontable_id' => $cuentaId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('contabilidad_cuenta_automatica')) {
            return;
        }

        DB::table('contabilidad_cuenta_automatica')
            ->where('clave', CuentaAutomaticaClaves::CAJA_CHEQUES_DIFERIDOS)
            ->delete();
    }

    private function resolverCuentaPorCodigo(int $empresaId, string $codigo): ?int
    {
        $id = DB::table('cuentacontable')
            ->where('empresa_id', $empresaId)
            ->where('codigo', $codigo)
            ->value('id');

        if ($id === null) {
            return null;
        }

        $id = (int) $id;

        return $id > 0 ? $id : null;
    }
};
