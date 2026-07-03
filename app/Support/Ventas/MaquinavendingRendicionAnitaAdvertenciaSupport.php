<?php

declare(strict_types=1);

namespace App\Support\Ventas;

use App\Models\Ventas\MaquinavendingRendicion;

final class MaquinavendingRendicionAnitaAdvertenciaSupport
{
    public static function mensajeDesdeExcepcion(\Throwable $e, MaquinavendingRendicion $rendicion, string $contexto): string
    {
        $nroOper = (int) ($rendicion->nro_oper_anita ?? 0);
        $cierre = (int) $rendicion->numero_cierre;
        $detalle = trim($e->getMessage());

        return sprintf(
            'No se pudo replicar en Anita (rendgastro) %s — cierre Ventas #%d%s: %s. '
            .'La rendición quedó guardada en el ERP; la auditoría matutina intentará corregirlo '
            .'o ejecute: php artisan maquinavending:resincronizar-rendiciones-anita --rendicion=%d',
            $contexto,
            $cierre,
            $nroOper > 0 ? ', nro_oper '.$nroOper : '',
            $detalle !== '' ? $detalle : 'error desconocido',
            (int) $rendicion->id,
        );
    }

    public static function mensajeTotalZNoConfirmado(MaquinavendingRendicion $rendicion, float $esperado, ?float $leido): string
    {
        $nroOper = (int) ($rendicion->nro_oper_anita ?? 0);
        $leidoFmt = $leido === null ? 'sin lectura' : number_format($leido, 2, ',', '.');

        return sprintf(
            'Anita (rendgastro) no confirmó rendg_total_z tras presentar en caja — cierre Ventas #%d, nro_oper %d: '
            .'esperado $%s, leído %s. La presentación quedó guardada en el ERP; '
            .'la auditoría matutina intentará corregirlo.',
            (int) $rendicion->numero_cierre,
            $nroOper,
            number_format($esperado, 2, ',', '.'),
            $leidoFmt,
        );
    }
}
