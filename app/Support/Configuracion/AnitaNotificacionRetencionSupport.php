<?php

namespace App\Support\Configuracion;

use Carbon\CarbonInterface;

/**
 * Cortes de retención para purga de anita_notificacion.
 */
final class AnitaNotificacionRetencionSupport
{
    /**
     * @return array{corte_leidas: ?\Carbon\CarbonInterface, corte_no_leidas: ?\Carbon\CarbonInterface}
     */
    public static function cortes(
        int $diasLeidas,
        int $diasNoLeidas,
        ?CarbonInterface $ahora = null
    ): array {
        $ahora ??= now();

        return [
            'corte_leidas' => $diasLeidas > 0 ? $ahora->copy()->subDays($diasLeidas) : null,
            'corte_no_leidas' => $diasNoLeidas > 0 ? $ahora->copy()->subDays($diasNoLeidas) : null,
        ];
    }
}
