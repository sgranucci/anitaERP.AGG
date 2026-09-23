<?php

namespace App\Support\Configuracion;

/**
 * Estado del documento tras aprobar un nivel de requisición.
 *
 * Si el nivel está configurado como APROBADA pero todavía hay un firmante siguiente
 * que aplica por monto (doble aprobación / umbral alto), el circuito debe seguir
 * en EN ARBOL APROBACION. Así el área puede tener APROBADA en la grilla (caso &lt; 5M)
 * sin cortar el paso a la segunda firma cuando el monto lo requiere.
 */
final class ArbolRequisicionEstadoTrasNivelSupport
{
    public const APROBADA = 'APROBADA';

    public const EN_ARBOL = 'EN ARBOL APROBACION';

    public static function resolver(string $estadoConfiguradoODefault, bool $hayProximoNivelQueAplica): string
    {
        $estado = trim($estadoConfiguradoODefault);
        if ($estado === '') {
            $estado = self::APROBADA;
        }

        if ($hayProximoNivelQueAplica && strcasecmp($estado, self::APROBADA) === 0) {
            return self::EN_ARBOL;
        }

        return $estado;
    }
}
