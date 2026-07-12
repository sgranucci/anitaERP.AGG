<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /** @var array<string, string> */
    private const CUENTAS_POR_CRITERIO = [
        'compras_ganancias' => '214010013',
        'compras_iva' => '214010021',
        'sueldos' => '214010008',
    ];

    public function up(): void
    {
        $now = now();

        foreach (self::CUENTAS_POR_CRITERIO as $criterio => $codigoCuenta) {
            $configId = (int) (DB::table('sicore_config')
                ->where('criterio', $criterio)
                ->where('activo', true)
                ->orderBy('id')
                ->value('id') ?? 0);

            if ($configId <= 0) {
                continue;
            }

            $empresas = DB::table('empresa')->select('id')->get();
            foreach ($empresas as $empresa) {
                $cuentaId = (int) (DB::table('cuentacontable')
                    ->where('empresa_id', $empresa->id)
                    ->where('codigo', $codigoCuenta)
                    ->value('id') ?? 0);

                if ($cuentaId <= 0) {
                    continue;
                }

                $existe = DB::table('sicore_config_cuenta')
                    ->where('sicore_config_id', $configId)
                    ->where('empresa_id', $empresa->id)
                    ->where('cuentacontable_id', $cuentaId)
                    ->exists();

                if ($existe) {
                    continue;
                }

                DB::table('sicore_config_cuenta')->insert([
                    'sicore_config_id' => $configId,
                    'empresa_id' => $empresa->id,
                    'cuentacontable_id' => $cuentaId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }

    public function down(): void
    {
        foreach (self::CUENTAS_POR_CRITERIO as $criterio => $codigoCuenta) {
            $configIds = DB::table('sicore_config')
                ->where('criterio', $criterio)
                ->pluck('id');

            if ($configIds->isEmpty()) {
                continue;
            }

            $cuentaIds = DB::table('cuentacontable')
                ->where('codigo', $codigoCuenta)
                ->pluck('id');

            if ($cuentaIds->isEmpty()) {
                continue;
            }

            DB::table('sicore_config_cuenta')
                ->whereIn('sicore_config_id', $configIds)
                ->whereIn('cuentacontable_id', $cuentaIds)
                ->delete();
        }
    }
};
