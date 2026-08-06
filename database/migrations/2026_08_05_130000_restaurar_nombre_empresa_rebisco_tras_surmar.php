<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * AGG: la migración 2026_08_05_120000_crear_empresa_surmar (El Bierzo, id=3)
 * renombró por error a REBISCO S.A. (id=3, CUIT 30-70546459-2) como «Surmar».
 * Restaura el nombre operativo. Solo tiene efecto en AGG.
 */
return new class extends Migration
{
    private const EMPRESA_ID = 3;

    private const CUIT_REBISCO = '30-70546459-2';

    private const NOMBRE_REBISCO = 'REBISCO S.A.';

    public function up(): void
    {
        if (strtoupper((string) config('app.empresa')) !== 'AGG') {
            return;
        }

        $fila = DB::table('empresa')->where('id', self::EMPRESA_ID)->first();
        if ($fila === null) {
            return;
        }

        $cuit = trim((string) ($fila->nroinscripcion ?? ''));
        $nombre = trim((string) ($fila->nombre ?? ''));
        if ($cuit !== self::CUIT_REBISCO && strcasecmp($nombre, 'Surmar') !== 0) {
            return;
        }

        if ($cuit === self::CUIT_REBISCO && $nombre === self::NOMBRE_REBISCO) {
            return;
        }

        DB::table('empresa')->where('id', self::EMPRESA_ID)->update([
            'nombre' => self::NOMBRE_REBISCO,
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        // No volver a «Surmar»: ese nombre no corresponde a Rebisco en AGG.
    }
};
