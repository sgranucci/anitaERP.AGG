<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Canje gastronomía: cuenta contable 211010017 (intereses) → 211010020 (vales gastronomía).
 */
return new class extends Migration
{
    private const CODIGO_LEGACY = '211010017';

    private const CODIGO_VALES = '211010020';

    /** Cuentas caja de canje gastronomía por empresa (excl. estacionamiento id 200). */
    private const CUENTACAJA_IDS = [116, 131, 132];

    public function up(): void
    {
        foreach (self::CUENTACAJA_IDS as $cuentacajaId) {
            $caja = DB::table('cuentacaja')->where('id', $cuentacajaId)->first(['id', 'empresa_id', 'cuentacontable_id']);
            if ($caja === null) {
                continue;
            }

            $empresaId = (int) ($caja->empresa_id ?? 0);
            if ($empresaId <= 0) {
                continue;
            }

            $legacyId = DB::table('cuentacontable')
                ->where('codigo', self::CODIGO_LEGACY)
                ->where('empresa_id', $empresaId)
                ->value('id');

            if ($legacyId === null || (int) $caja->cuentacontable_id !== (int) $legacyId) {
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
        foreach (self::CUENTACAJA_IDS as $cuentacajaId) {
            $caja = DB::table('cuentacaja')->where('id', $cuentacajaId)->first(['id', 'empresa_id', 'cuentacontable_id']);
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

            if ($valesId === null || (int) $caja->cuentacontable_id !== (int) $valesId) {
                continue;
            }

            $legacyId = DB::table('cuentacontable')
                ->where('codigo', self::CODIGO_LEGACY)
                ->where('empresa_id', $empresaId)
                ->value('id');

            if ($legacyId === null) {
                continue;
            }

            DB::table('cuentacaja')
                ->where('id', $cuentacajaId)
                ->update(['cuentacontable_id' => (int) $legacyId]);
        }
    }
};
