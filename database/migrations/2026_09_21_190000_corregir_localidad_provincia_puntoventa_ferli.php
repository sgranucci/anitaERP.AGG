<?php

use App\Support\Configuracion\EntornoEmpresaSupport;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Ferli: 31 PV quedaron con localidad 108 (CABRAL SARGENTO) y provincia 3 (Catamarca)
 * por defaults del sync Anita. El domicilio fiscal canónico es Villa Madero / Bs.As.
 * (mismo criterio que la limpieza del PV 00012 en 2026_09_13_170000).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! EntornoEmpresaSupport::esFerli()) {
            return;
        }

        $locId = (int) (DB::table('localidad')
            ->where('nombre', 'VILLA MADERO')
            ->where('codigopostal', '1768')
            ->value('id') ?? 0);

        if ($locId <= 0) {
            return;
        }

        $provinciaBsAs = 2;
        $cp = '1768';

        $afectados = DB::table('puntoventa')
            ->where(function ($q) {
                $q->where('localidad_id', 108)
                    ->orWhere('provincia_id', 3);
            })
            ->pluck('id');

        if ($afectados->isEmpty()) {
            return;
        }

        DB::table('puntoventa')
            ->whereIn('id', $afectados->all())
            ->update([
                'localidad_id' => $locId,
                'provincia_id' => $provinciaBsAs,
                'codigopostal' => $cp,
                'updated_at' => now(),
            ]);

        // Telefono a veces trae ciudad/CP desde Anita (no es un TEL.).
        $pvsTel = DB::table('puntoventa')
            ->whereIn('id', $afectados->all())
            ->whereNotNull('telefono')
            ->where('telefono', '!=', '')
            ->get(['id', 'telefono']);

        foreach ($pvsTel as $pv) {
            $tel = trim((string) $pv->telefono);
            $pareceTelefono = $tel !== ''
                && preg_match('/\d/', $tel)
                && ! preg_match('/\bCP\.?\s*\d/i', $tel)
                && ! preg_match('/\b(Ciudad|Villa|C\.?A\.?B\.?A\.?|Madero|Lugano|Matanza)\b/i', $tel);

            if (! $pareceTelefono) {
                DB::table('puntoventa')
                    ->where('id', $pv->id)
                    ->update([
                        'telefono' => null,
                        'updated_at' => now(),
                    ]);
            }
        }
    }

    public function down(): void
    {
        // No revertir: los valores previos (Cabral Sargento / Catamarca) eran incorrectos.
    }
};
