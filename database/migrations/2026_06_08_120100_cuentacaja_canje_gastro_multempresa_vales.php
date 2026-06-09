<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Corrige cuentas caja canje gastronomía que apuntaban a cuentacontable de otra empresa.
 */
return new class extends Migration
{
    private const CODIGO_VALES = '211010020';

    private const CUENTACAJA_IDS = [131, 132];

    public function up(): void
    {
        foreach (self::CUENTACAJA_IDS as $cuentacajaId) {
            $caja = DB::table('cuentacaja')->where('id', $cuentacajaId)->first(['id', 'empresa_id']);
            if ($caja === null) {
                continue;
            }

            $empresaId = (int) ($caja->empresa_id ?? 0);
            if ($empresaId <= 0) {
                continue;
            }

            $valesId = DB::table('cuentacontable')
                ->where('codigo', self::CODIGO_VALES)
                ->where('empresa_id', $empresaId)
                ->value('id');

            if ($valesId === null) {
                continue;
            }

            DB::table('cuentacaja')
                ->where('id', $cuentacajaId)
                ->update(['cuentacontable_id' => (int) $valesId]);
        }
    }

    public function down(): void
    {
        // Sin reversión: el estado anterior era incorrecto (FK de otra empresa).
    }
};
