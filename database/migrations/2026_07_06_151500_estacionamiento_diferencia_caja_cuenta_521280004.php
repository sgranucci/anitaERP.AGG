<?php

use App\Support\Contable\CuentaAutomaticaClaves;
use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const CODIGO_CUENTA_DIFERENCIA_CAJA = '521280004';

    public function up(): void
    {
        if (! Schema::hasTable('contabilidad_cuenta_automatica') || ! Schema::hasTable('cuentacontable')) {
            return;
        }

        $empresaIds = DB::table('empresa')->pluck('id')->map(fn ($id) => (int) $id)->all();

        foreach ($empresaIds as $empresaId) {
            if ($empresaId <= 0) {
                continue;
            }

            $cuentaId = (int) (DB::table('cuentacontable')
                ->where('empresa_id', $empresaId)
                ->where('codigo', self::CODIGO_CUENTA_DIFERENCIA_CAJA)
                ->value('id') ?? 0);

            if ($cuentaId <= 0) {
                continue;
            }

            DB::table('contabilidad_cuenta_automatica')
                ->where('empresa_id', $empresaId)
                ->where('clave', CuentaAutomaticaClaves::CIERRE_ESTACIONAMIENTO_DIFERENCIA_CAJA)
                ->update([
                    'cuentacontable_id' => $cuentaId,
                    'updated_at' => now(),
                ]);
        }

        SuitecrmPermiso::flushCachePermisos();
    }

    public function down(): void
    {
        if (! Schema::hasTable('contabilidad_cuenta_automatica') || ! Schema::hasTable('cuentacontable')) {
            return;
        }

        $empresaIds = DB::table('empresa')->pluck('id')->map(fn ($id) => (int) $id)->all();

        foreach ($empresaIds as $empresaId) {
            if ($empresaId <= 0) {
                continue;
            }

            $cuentaId = (int) (DB::table('cuentacontable')
                ->where('empresa_id', $empresaId)
                ->where('codigo', '411010001')
                ->value('id') ?? 0);

            if ($cuentaId <= 0) {
                continue;
            }

            DB::table('contabilidad_cuenta_automatica')
                ->where('empresa_id', $empresaId)
                ->where('clave', CuentaAutomaticaClaves::CIERRE_ESTACIONAMIENTO_DIFERENCIA_CAJA)
                ->update([
                    'cuentacontable_id' => $cuentaId,
                    'updated_at' => now(),
                ]);
        }

        SuitecrmPermiso::flushCachePermisos();
    }
};
