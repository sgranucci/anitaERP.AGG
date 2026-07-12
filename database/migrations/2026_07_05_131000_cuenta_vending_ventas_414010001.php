<?php

use App\Support\Contable\CuentaAutomaticaClaves;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const CODIGO_VENTAS_VENDING = '414010001';

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

            $cuentaId = $this->resolverCuentaId($empresaId, self::CODIGO_VENTAS_VENDING);

            $existente = DB::table('contabilidad_cuenta_automatica')
                ->where('empresa_id', $empresaId)
                ->where('clave', CuentaAutomaticaClaves::CIERRE_VENDING_VENTAS)
                ->first();

            if ($existente !== null) {
                DB::table('contabilidad_cuenta_automatica')
                    ->where('id', $existente->id)
                    ->update([
                        'cuentacontable_id' => $cuentaId,
                        'updated_at' => now(),
                    ]);

                continue;
            }

            DB::table('contabilidad_cuenta_automatica')->insert([
                'empresa_id' => $empresaId,
                'clave' => CuentaAutomaticaClaves::CIERRE_VENDING_VENTAS,
                'cuentacontable_id' => $cuentaId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        // No revertir: la cuenta 414010001 es la definición operativa acordada.
    }

    private function resolverCuentaId(int $empresaId, string $codigo): ?int
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
