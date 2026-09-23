<?php

use App\Support\Configuracion\EntornoEmpresaSupport;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * AGG: ~100 PV quedaron con localidad 108 (COLONIA CELLO) y provincia 3 (Catamarca)
 * por defaults del sync Anita. El domicilio fiscal canónico es el de cada empresa:
 * BSA Avellaneda, KSA Wilde, RSA Florencio Varela (Buenos Aires).
 * Sin esto, el PDF de factura imprime COLONIA CELLO / Catamarca.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! EntornoEmpresaSupport::esAgg()) {
            return;
        }

        $empresas = DB::table('empresa')
            ->whereIn('id', [1, 2, 3])
            ->get(['id', 'localidad_id', 'provincia_id', 'codigopostal']);

        foreach ($empresas as $empresa) {
            $locId = (int) ($empresa->localidad_id ?? 0);
            $provId = (int) ($empresa->provincia_id ?? 0);
            if ($locId <= 0 || $provId <= 0) {
                continue;
            }

            $cp = trim((string) ($empresa->codigopostal ?? ''));

            $afectados = DB::table('puntoventa')
                ->where('empresa_id', $empresa->id)
                ->where(function ($q) {
                    $q->where('localidad_id', 108)
                        ->orWhere('provincia_id', 3);
                })
                ->pluck('id');

            if ($afectados->isEmpty()) {
                continue;
            }

            $update = [
                'localidad_id' => $locId,
                'provincia_id' => $provId,
                'updated_at' => now(),
            ];
            if ($cp !== '') {
                $update['codigopostal'] = $cp;
            }

            DB::table('puntoventa')
                ->whereIn('id', $afectados->all())
                ->update($update);
        }
    }

    public function down(): void
    {
        // No revertir: COLONIA CELLO / Catamarca eran incorrectos.
    }
};
