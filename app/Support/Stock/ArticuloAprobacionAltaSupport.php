<?php

namespace App\Support\Stock;

/**
 * Feature flag y constantes del circuito opt-in de alta de artículos.
 */
final class ArticuloAprobacionAltaSupport
{
    public const ESTADO_PENDIENTE = 'PENDIENTE';

    public const ESTADO_RECHAZADO = 'RECHAZADO';

    public const ESTADO_ACTIVO = 'ACTIVO';

    public const MODO_AUTO = 'auto';

    public const MODO_ARBOL = 'arbol';

    public const MODO_DEFAULT = 'default';

    public static function habilitado(): bool
    {
        // Prioridad: Configuración general (parametro_sistema) → config/.env
        if (class_exists(\App\Support\Configuracion\ParametroSistemaSupport::class)) {
            return \App\Support\Configuracion\ParametroSistemaSupport::boolean(
                \App\Support\Configuracion\ParametroSistemaSupport::CLAVE_ARTICULO_APROBACION_ALTA,
                (bool) filter_var(
                    config('articulo.aprobacion_alta.habilitado', false),
                    FILTER_VALIDATE_BOOLEAN
                )
            );
        }

        return (bool) filter_var(
            config('articulo.aprobacion_alta.habilitado', false),
            FILTER_VALIDATE_BOOLEAN
        );
    }

    public static function esOperativo(?string $estado): bool
    {
        return strtoupper(trim((string) $estado)) === self::ESTADO_ACTIVO;
    }

    public static function requiereCircuito(?string $estado): bool
    {
        $e = strtoupper(trim((string) $estado));

        return in_array($e, [self::ESTADO_PENDIENTE, self::ESTADO_RECHAZADO], true);
    }
}
