<?php

use Illuminate\Database\Migrations\Migration;

/**
 * Quedó anulada: la asignación era para AGG, no para Crown.
 * El alta está en 2026_10_29_202000_menu_ventas_articulos_gsurace_impuestos_agg.
 * Esta clase ya figura corrida en AGG (no asignó nada). En Crown tampoco asigna.
 */
return new class extends Migration
{
    public function up(): void
    {
    }

    public function down(): void
    {
    }
};
