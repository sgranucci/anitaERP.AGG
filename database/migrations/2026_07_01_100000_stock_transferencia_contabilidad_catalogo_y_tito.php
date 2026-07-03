<?php

use App\Support\Contable\CuentaAutomaticaClaves;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Catálogo: cuenta Otros activos en transferencias TRCONT + flag TITO en SKUs legacy.
 */
return new class extends Migration
{
    private const CODIGO_OTROS_ACTIVOS = '117010001';

    /** @var list<string> */
    private const SKUS_TITO = [
        '201421',
        '100000-006',
        '201266',
        '201265',
        '0000000201421',
        '000100000-006',
        '0000000201266',
        '0000000201265',
    ];

    public function up(): void
    {
        $this->seedCatalogoOtrosActivos();
        $this->marcarSkusTito();
    }

    public function down(): void
    {
        if (Schema::hasTable('contabilidad_cuenta_automatica')) {
            DB::table('contabilidad_cuenta_automatica')
                ->where('clave', CuentaAutomaticaClaves::STOCK_TRANSFERENCIA_OTROS_ACTIVOS)
                ->delete();
        }

        if (Schema::hasTable('articulo')) {
            DB::table('articulo')
                ->whereIn('sku', self::SKUS_TITO)
                ->update(['fl_precio_promedio_transferencia' => 0]);
        }
    }

    private function seedCatalogoOtrosActivos(): void
    {
        if (! Schema::hasTable('contabilidad_cuenta_automatica') || ! Schema::hasTable('cuentacontable')) {
            return;
        }

        $clave = CuentaAutomaticaClaves::STOCK_TRANSFERENCIA_OTROS_ACTIVOS;
        $empresaIds = DB::table('empresa')->orderBy('id')->pluck('id');

        foreach ($empresaIds as $empresaId) {
            $empresaId = (int) $empresaId;
            $cuentaId = $this->resolverCuentaPorCodigo($empresaId, self::CODIGO_OTROS_ACTIVOS);

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

    private function marcarSkusTito(): void
    {
        if (! Schema::hasTable('articulo')) {
            return;
        }

        DB::table('articulo')
            ->whereIn('sku', self::SKUS_TITO)
            ->update(['fl_precio_promedio_transferencia' => 1]);
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
