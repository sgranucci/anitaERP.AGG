<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const CODIGO_CUENTA_TABACO = '414020001';

    /** @var array<int, int> empresa_id => cuentacontable_id VENTAS TABACO */
    private const EMPRESAS = [1, 2, 3];

    public function up(): void
    {
        if (! Schema::hasTable('gastronomia_cierre_jornada_config')
            || ! Schema::hasColumn('gastronomia_cierre_jornada_config', 'cuenta_ventas_kiosco_id')) {
            return;
        }

        foreach (self::EMPRESAS as $empresaId) {
            $cuentaId = DB::table('cuentacontable')
                ->where('empresa_id', $empresaId)
                ->where('codigo', self::CODIGO_CUENTA_TABACO)
                ->value('id');

            if ($cuentaId === null) {
                continue;
            }

            DB::table('gastronomia_cierre_jornada_config')
                ->where('empresa_id', $empresaId)
                ->update([
                    'cuenta_ventas_kiosco_id' => (int) $cuentaId,
                    'updated_at' => now(),
                ]);
        }

        if (Schema::hasTable('contabilidad_cuenta_automatica')) {
            foreach (self::EMPRESAS as $empresaId) {
                $cuentaId = DB::table('cuentacontable')
                    ->where('empresa_id', $empresaId)
                    ->where('codigo', self::CODIGO_CUENTA_TABACO)
                    ->value('id');

                if ($cuentaId === null) {
                    continue;
                }

                DB::table('contabilidad_cuenta_automatica')->updateOrInsert(
                    [
                        'empresa_id' => $empresaId,
                        'clave' => 'cierre_waitry.ventas_kiosco',
                    ],
                    [
                        'cuentacontable_id' => (int) $cuentaId,
                        'updated_at' => now(),
                        'created_at' => now(),
                    ],
                );
            }
        }
    }

    public function down(): void
    {
        // Sin rollback automático: la cuenta anterior (414010001) variaba por empresa.
    }
};
