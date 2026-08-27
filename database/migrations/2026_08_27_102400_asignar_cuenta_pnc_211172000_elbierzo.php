<?php

use App\Support\Configuracion\EntornoEmpresaSupport;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const CODIGO_CUENTA = '211172000';

    public function up(): void
    {
        if (! EntornoEmpresaSupport::esElBierzo()) {
            return;
        }

        $impuestoId = DB::table('impuesto')->where('codigo', 'PNC')->value('id');
        if (! $impuestoId) {
            return;
        }

        $ahora = now();
        $cuentas = DB::table('cuentacontable')
            ->where('codigo', self::CODIGO_CUENTA)
            ->get(['id', 'empresa_id']);

        foreach ($cuentas as $cuenta) {
            $existe = DB::table('impuesto_cuentacontable')
                ->where('impuesto_id', $impuestoId)
                ->where('empresa_id', $cuenta->empresa_id)
                ->where('cuentacontable_id', $cuenta->id)
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

    public function down(): void
    {
        if (! EntornoEmpresaSupport::esElBierzo()) {
            return;
        }

        $impuestoId = DB::table('impuesto')->where('codigo', 'PNC')->value('id');
        if (! $impuestoId) {
            return;
        }

        $cuentaIds = DB::table('cuentacontable')
            ->where('codigo', self::CODIGO_CUENTA)
            ->pluck('id');
        if ($cuentaIds->isEmpty()) {
            return;
        }

        DB::table('impuesto_cuentacontable')
            ->where('impuesto_id', $impuestoId)
            ->whereIn('cuentacontable_id', $cuentaIds)
            ->delete();
    }
};
