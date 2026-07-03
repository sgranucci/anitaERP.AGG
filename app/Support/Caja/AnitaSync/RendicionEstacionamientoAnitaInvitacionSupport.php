<?php

namespace App\Support\Caja\AnitaSync;

use App\Models\Caja\RendicionEstacionamientoCaja;

/**
 * Invitaciones $0,01 en estacionamiento: ya están en rendg_total_x / rendg_total_z.
 * Anita legacy suma rendg_invitacion al haber y duplica el importe.
 * rendg_invitacion = 0; centavos de invitación van en rendg_tot_redondeo **negativo**
 * (igual que totalredondeo en el cierre de turno ERP).
 */
final class RendicionEstacionamientoAnitaInvitacionSupport
{
    /**
     * @return array{invitacion: float, tot_redondeo: float}
     */
    public static function camposDesdeRendicion(RendicionEstacionamientoCaja $rendicion): array
    {
        $invitacion = round((float) $rendicion->totalinvitacion, 2);

        return [
            'invitacion' => 0.0,
            'tot_redondeo' => $invitacion > 0.0001 ? round(-$invitacion, 2) : 0.0,
        ];
    }
}
