<?php

use App\Support\Contable\CuentaAutomaticaClaves;
use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** @var array<string, string> */
    private const CODIGO_SUGERIDO = [
        CuentaAutomaticaClaves::CIERRE_ESTACIONAMIENTO_VENTAS => '415010003',
        CuentaAutomaticaClaves::CIERRE_ESTACIONAMIENTO_DIFERENCIA_CAJA => '411010001',
    ];

    public function up(): void
    {
        if (! Schema::hasTable('contabilidad_cuenta_automatica')) {
            return;
        }

        $empresaIds = DB::table('empresa')->pluck('id')->map(fn ($id) => (int) $id)->all();

        foreach ($empresaIds as $empresaId) {
            if ($empresaId <= 0) {
                continue;
            }
            foreach (CuentaAutomaticaClaves::catalogo() as $clave => $meta) {
                if (! str_starts_with($clave, 'cierre_estacionamiento.')) {
                    continue;
                }

                $existente = DB::table('contabilidad_cuenta_automatica')
                    ->where('empresa_id', $empresaId)
                    ->where('clave', $clave)
                    ->first();

                if ($existente !== null) {
                    continue;
                }

                $cuentaId = $this->resolverCuentaId($empresaId, self::CODIGO_SUGERIDO[$clave] ?? '');

                DB::table('contabilidad_cuenta_automatica')->insert([
                    'empresa_id' => $empresaId,
                    'clave' => $clave,
                    'cuentacontable_id' => $cuentaId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        SuitecrmPermiso::flushCachePermisos();
    }

    public function down(): void
    {
        if (! Schema::hasTable('contabilidad_cuenta_automatica')) {
            return;
        }

        DB::table('contabilidad_cuenta_automatica')
            ->whereIn('clave', [
                CuentaAutomaticaClaves::CIERRE_ESTACIONAMIENTO_VENTAS,
                CuentaAutomaticaClaves::CIERRE_ESTACIONAMIENTO_DIFERENCIA_CAJA,
            ])
            ->delete();
    }

    private function resolverCuentaId(int $empresaId, string $codigo): ?int
    {
        $codigo = trim($codigo);
        if ($codigo === '' || ! Schema::hasTable('cuentacontable')) {
            return null;
        }

        $id = (int) (DB::table('cuentacontable')
            ->where('empresa_id', $empresaId)
            ->where('codigo', $codigo)
            ->value('id') ?? 0);

        return $id > 0 ? $id : null;
    }
};
