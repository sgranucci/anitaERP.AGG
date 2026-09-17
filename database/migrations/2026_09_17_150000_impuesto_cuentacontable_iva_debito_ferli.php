<?php

use App\Support\Configuracion\EntornoEmpresaSupport;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Ferli: mapa impuestos gravados (10.5 / 21 / 27) → IVA Débito Fiscal 213100001.
 * Sin estas filas armaContabilidad corta con "Error en cuenta contable de impuesto N".
 */
return new class extends Migration
{
    private const CODIGO_IVA_DEBITO = '213100001';

    public function up(): void
    {
        if (! EntornoEmpresaSupport::esFerli()) {
            return;
        }
        if (! Schema::hasTable('impuesto_cuentacontable') || ! Schema::hasTable('cuentacontable')) {
            return;
        }

        $impuestos = DB::table('impuesto')
            ->where(function ($q) {
                $q->whereIn('id', [2, 3, 4])
                    ->orWhereIn('valor', [10.5, 21, 27]);
            })
            ->pluck('id')
            ->unique()
            ->values();
        if ($impuestos->isEmpty()) {
            return;
        }

        $ahora = now();
        $cuentas = DB::table('cuentacontable')
            ->where('codigo', self::CODIGO_IVA_DEBITO)
            ->get(['id', 'empresa_id']);

        foreach ($cuentas as $cuenta) {
            foreach ($impuestos as $impuestoId) {
                $existe = DB::table('impuesto_cuentacontable')
                    ->where('impuesto_id', $impuestoId)
                    ->where('empresa_id', $cuenta->empresa_id)
                    ->exists();
                if ($existe) {
                    continue;
                }

                DB::table('impuesto_cuentacontable')->insert([
                    'impuesto_id' => $impuestoId,
                    'empresa_id' => $cuenta->empresa_id,
                    'cuentacontable_id' => $cuenta->id,
                    'creousuario_id' => 2,
                    'created_at' => $ahora,
                    'updated_at' => $ahora,
                ]);
            }
        }
    }

    public function down(): void
    {
        if (! EntornoEmpresaSupport::esFerli()) {
            return;
        }
        if (! Schema::hasTable('impuesto_cuentacontable')) {
            return;
        }

        $cuentaIds = DB::table('cuentacontable')
            ->where('codigo', self::CODIGO_IVA_DEBITO)
            ->pluck('id');
        if ($cuentaIds->isEmpty()) {
            return;
        }

        DB::table('impuesto_cuentacontable')
            ->whereIn('cuentacontable_id', $cuentaIds)
            ->whereIn('impuesto_id', DB::table('impuesto')
                ->where(function ($q) {
                    $q->whereIn('id', [2, 3, 4])
                        ->orWhereIn('valor', [10.5, 21, 27]);
                })
                ->pluck('id'))
            ->delete();
    }
};
