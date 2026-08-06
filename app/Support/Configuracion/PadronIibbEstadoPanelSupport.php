<?php

declare(strict_types=1);

namespace App\Support\Configuracion;

use App\Models\Configuracion\Padron_Iibb_Carga;
use Throwable;

/**
 * Datos del panel de estado de la pantalla de importación de padrones IIBB.
 *
 * La vigencia por jurisdicción vive en PadronIibbVigenciaSupport.
 */
final class PadronIibbEstadoPanelSupport
{
    /**
     * Últimas importaciones registradas, con la provincia ya resuelta.
     *
     * @return \Illuminate\Support\Collection<int,Padron_Iibb_Carga>
     */
    public static function ultimasCargas(int $limite = 10)
    {
        try {
            return Padron_Iibb_Carga::query()
                ->with(['provincias:id,nombre,codigo', 'usuarios:id,nombre'])
                ->orderByDesc('id')
                ->limit($limite)
                ->get();
        } catch (Throwable $e) {
            return collect();
        }
    }
}
