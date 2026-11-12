<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Semántica canónica puntoventa.modofacturacion:
 * - C = electrónica CAE (wsfev1)
 * - E = exportación WSFEX (wsfex_v1)
 *
 * AGG histórico usaba E + wsfev1 para electrónica local; Ferli/Crown ya usan C.
 * Reasigna esos PV a C. No toca E + wsfex (export Ferli/Interforming).
 */
return new class extends Migration
{
    /** @var list<string> */
    private const ALIASES_WSFE = ['wsfev1', 'wsfe', 'wsfe_v1', ''];

    public function up(): void
    {
        if (! Schema::hasTable('puntoventa')) {
            return;
        }

        $query = DB::table('puntoventa')->where('modofacturacion', 'E');
        $query->where(function ($q) {
            $q->whereIn('webservice', self::ALIASES_WSFE)
                ->orWhereNull('webservice');
        });

        $query->update([
            'modofacturacion' => 'C',
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        // No revertir: en AGG E+wsfe era electrónica; en Ferli E+wsfex es export.
        // Un down ciego remezclaría semánticas.
    }
};
