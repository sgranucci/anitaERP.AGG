<?php

namespace App\Support\Configuracion;

final class OcArbolTriggerCatalog
{
    public const TIPO_EVENTO = 'EVENTO';

    public const TIPO_CONDICION = 'CONDICION';

    public const EVENTO_ALTA = 'ALTA';

    public const EVENTO_CAMBIO_SECTOR = 'CAMBIO_SECTOR';

    public const EVALUADOR_CAPEX_MES_EXCEDIDO = 'CAPEX_MES_EXCEDIDO';

    public const ACCION_NINGUNA = 'NINGUNA';

    public const ACCION_CAMBIAR_SECTOR = 'CAMBIAR_SECTOR';

    public const ACCION_CAMBIAR_ESTADO = 'CAMBIAR_ESTADO';

    /** @return list<string> */
    public static function tipos(): array
    {
        return [self::TIPO_EVENTO, self::TIPO_CONDICION];
    }

    /** @return list<string> */
    public static function eventos(): array
    {
        return [self::EVENTO_ALTA, self::EVENTO_CAMBIO_SECTOR];
    }

    /** @return list<string> */
    public static function evaluadores(): array
    {
        return [self::EVALUADOR_CAPEX_MES_EXCEDIDO];
    }

    /** @return list<string> */
    public static function accionesFinales(): array
    {
        return [self::ACCION_NINGUNA, self::ACCION_CAMBIAR_SECTOR, self::ACCION_CAMBIAR_ESTADO];
    }

    public static function etiquetaTipo(string $tipo): string
    {
        return match ($tipo) {
            self::TIPO_EVENTO => 'Evento',
            self::TIPO_CONDICION => 'Condición',
            default => $tipo,
        };
    }

    public static function etiquetaEvento(string $evento): string
    {
        return match ($evento) {
            self::EVENTO_ALTA => 'Alta / edición PENDIENTE',
            self::EVENTO_CAMBIO_SECTOR => 'Cambio de sector',
            default => $evento,
        };
    }

    public static function etiquetaEvaluador(string $evaluador): string
    {
        return match ($evaluador) {
            self::EVALUADOR_CAPEX_MES_EXCEDIDO => 'CAPEX: supera monto asignado del mes',
            default => $evaluador,
        };
    }

    public static function etiquetaAccionFinal(string $accion): string
    {
        return match ($accion) {
            self::ACCION_NINGUNA => 'Ninguna',
            self::ACCION_CAMBIAR_SECTOR => 'Cambiar sector legajo',
            self::ACCION_CAMBIAR_ESTADO => 'Cambiar estado OC',
            default => $accion,
        };
    }
}
