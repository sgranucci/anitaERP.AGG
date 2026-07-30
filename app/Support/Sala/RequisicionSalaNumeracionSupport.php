<?php

namespace App\Support\Sala;

use Illuminate\Support\Facades\DB;

/**
 * Numeración unificada de requisiciones de sala (todas las empresas).
 * El siguiente número es max(numerorequisicion) global + 1.
 */
final class RequisicionSalaNumeracionSupport
{
    public static function ultimoNumeroGlobal(): int
    {
        return (int) DB::table('requisicion_sala')->max('numerorequisicion');
    }

    /**
     * Reserva el próximo número correlativo único entre empresas.
     * Debe llamarse dentro de una transacción (lockForUpdate).
     */
    public static function asignarSiguienteNumero(): int
    {
        DB::table('requisicion_sala')
            ->orderByDesc('numerorequisicion')
            ->lockForUpdate()
            ->limit(1)
            ->value('id');

        return self::ultimoNumeroGlobal() + 1;
    }
}
