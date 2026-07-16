<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Estado V = VIP (monto ticket 0, no canjeable). Actualiza VIP ya emitidos como Pendiente.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('ticket_canje_caja')
            ->where('es_vip', true)
            ->where('estado', 'P')
            ->update(['estado' => 'V']);
    }

    public function down(): void
    {
        DB::table('ticket_canje_caja')
            ->where('estado', 'V')
            ->update(['estado' => 'P']);
    }
};
