<?php

use App\Support\Contable\CuentacontableTipocuentaNormalizacionSupport;
use Illuminate\Database\Migrations\Migration;

/**
 * Alinea tipocuenta con Anita ctamae (no heurística 1↔2).
 *
 * Preferible correr antes en dry-run:
 *   php artisan cuentacontable:sincronizar-tipocuenta-anita --dry-run
 * Esta migración persiste al migrar (requiere bridge Anita disponible).
 *
 * La migración 2026_09_23_210000 (flip heurístico) no debe usarse como fuente de verdad.
 */
return new class extends Migration
{
    public function up(): void
    {
        CuentacontableTipocuentaNormalizacionSupport::sincronizarDesdeAnita(false);
    }

    public function down(): void
    {
        // No se puede revertir sin un snapshot previo de tipocuenta.
    }
};
