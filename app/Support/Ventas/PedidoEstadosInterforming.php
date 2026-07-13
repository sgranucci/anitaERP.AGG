<?php

namespace App\Support\Ventas;

/**
 * Estados Anita pendmae / pendmov (Interforming).
 * Fuente: pendmae.def, pendmov.def.
 */
final class PedidoEstadosInterforming
{
    // Cabecera (penm_estado)
    public const CAB_PENTREGAR = '0';
    public const CAB_ENTRPARC = '1';
    public const CAB_ENTREGADO = '2';
    public const CAB_FACTURADO = '3';
    public const CAB_SUSPENDIDO = '4';
    public const CAB_UNIFICADO = '5';
    public const CAB_RESERVA = '6';
    public const CAB_ANULADO = '7';
    public const CAB_REFACTURA = '8';

    // Ítem aprobación (penv_estado)
    public const ITEM_PENDIENTE = 'P';
    public const ITEM_APROBADO = 'A';
    public const ITEM_RECHAZADO = 'R';
    public const ITEM_PRODUCCION = 'D';
    public const ITEM_ENTREGADO = 'E';
    public const ITEM_CONDICIONAL = 'C';

    // Cierre de línea (penv_estado_cierre)
    public const CIERRE_ABIERTO = ' ';
    public const CIERRE_CERRADO = 'C';

    // Partida ítem (penv_partida): 0=propio, 1=fason
    public const PARTIDA_PROPIO = 0;
    public const PARTIDA_FASON = 1;

    /**
     * @return array<string, string>
     */
    public static function etiquetasCabecera(): array
    {
        return [
            self::CAB_PENTREGAR => 'Pendiente entrega',
            self::CAB_ENTRPARC => 'Entrega parcial',
            self::CAB_ENTREGADO => 'Entregado',
            self::CAB_FACTURADO => 'Facturado',
            self::CAB_SUSPENDIDO => 'Suspendido',
            self::CAB_UNIFICADO => 'Unificado',
            self::CAB_RESERVA => 'Reserva',
            self::CAB_ANULADO => 'Anulado',
            self::CAB_REFACTURA => 'Refactura',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function etiquetasItem(): array
    {
        return [
            self::ITEM_PENDIENTE => 'Pendiente',
            self::ITEM_APROBADO => 'Aprobado',
            self::ITEM_RECHAZADO => 'Rechazado',
            self::ITEM_PRODUCCION => 'Producción',
            self::ITEM_ENTREGADO => 'Entregado',
            self::ITEM_CONDICIONAL => 'Condicional',
        ];
    }

    public static function etiquetaCabecera(?string $estado): string
    {
        $estado = (string) $estado;

        return self::etiquetasCabecera()[$estado] ?? $estado;
    }

    public static function etiquetaItem(?string $estado): string
    {
        $estado = (string) $estado;

        return self::etiquetasItem()[$estado] ?? $estado;
    }
}
