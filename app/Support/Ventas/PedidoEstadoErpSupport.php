<?php

namespace App\Support\Ventas;

/**
 * Estados de pedido ERP (El Bierzo): cabecera P/E/F/A/C e ítem P/A/F.
 * Anita pendmae.penm_estado en El Bierzo es numérico (0=pendiente entregar).
 */
final class PedidoEstadoErpSupport
{
    public const PENDIENTE = 'P';

    public const ENTREGADO = 'E';

    public const FACTURADO = 'F';

    public const ANULADO = 'A';

    public const CERRADO = 'C';

    public const TRANSFERIDO = 'T';

    public const ETIQUETA_TRANSFERIDO = 'Transferido';

    /** @var list<string> */
    private const CABECERA_ERP = [self::PENDIENTE, self::ENTREGADO, self::FACTURADO, self::ANULADO, self::CERRADO, self::TRANSFERIDO];

    /** @var list<string> */
    private const ITEM_ERP = [self::PENDIENTE, self::ANULADO, self::FACTURADO, self::ENTREGADO];

    /**
     * Cabecera lista para facturar en ERP (ignora el estado Anita).
     *
     * @return array{estado: string, estadopedido: string}
     */
    public static function cabeceraPendiente(): array
    {
        return ['estado' => self::PENDIENTE, 'estadopedido' => 'Pendiente'];
    }

    /**
     * @return array{estado: string, estadopedido: string}
     */
    public static function mapearCabeceraDesdeAnita(?string $estadoAnita): array
    {
        $key = strtoupper(trim((string) $estadoAnita));

        return match ($key) {
            PedidoEstadosInterforming::CAB_PENTREGAR,
            PedidoEstadosInterforming::CAB_ENTRPARC,
            PedidoEstadosInterforming::CAB_UNIFICADO,
            PedidoEstadosInterforming::CAB_RESERVA,
            '',
            self::PENDIENTE => ['estado' => self::PENDIENTE, 'estadopedido' => 'Pendiente'],
            PedidoEstadosInterforming::CAB_ENTREGADO,
            self::ENTREGADO => ['estado' => self::ENTREGADO, 'estadopedido' => 'Pendiente'],
            PedidoEstadosInterforming::CAB_FACTURADO,
            PedidoEstadosInterforming::CAB_REFACTURA,
            self::FACTURADO => ['estado' => self::FACTURADO, 'estadopedido' => 'Facturado'],
            PedidoEstadosInterforming::CAB_SUSPENDIDO,
            'S' => ['estado' => self::PENDIENTE, 'estadopedido' => 'Suspendido'],
            PedidoEstadosInterforming::CAB_ANULADO,
            self::ANULADO => ['estado' => self::ANULADO, 'estadopedido' => 'Anulado'],
            self::CERRADO => ['estado' => self::CERRADO, 'estadopedido' => 'Pendiente'],
            self::TRANSFERIDO => ['estado' => self::TRANSFERIDO, 'estadopedido' => self::ETIQUETA_TRANSFERIDO],
            default => ['estado' => self::PENDIENTE, 'estadopedido' => 'Pendiente'],
        };
    }

    public static function normalizarEstadoCabecera(?string $estado, ?string $estadopedido = null): string
    {
        $estado = strtoupper(trim((string) $estado));
        $etiqueta = trim((string) $estadopedido);

        if ($etiqueta === 'Facturado') {
            return self::FACTURADO;
        }
        if ($etiqueta === self::ETIQUETA_TRANSFERIDO) {
            return self::TRANSFERIDO;
        }
        if ($etiqueta === 'Anulado') {
            return self::ANULADO;
        }
        if (in_array($estado, self::CABECERA_ERP, true)) {
            return $estado;
        }

        return self::mapearCabeceraDesdeAnita($estado)['estado'];
    }

    public static function normalizarEstadoItem(?string $estado): string
    {
        $estado = strtoupper(trim((string) $estado));
        if (in_array($estado, self::ITEM_ERP, true)) {
            return $estado;
        }

        return self::PENDIENTE;
    }

    public static function esItemPendienteFacturable(?string $estado): bool
    {
        return self::normalizarEstadoItem($estado) === self::PENDIENTE;
    }

    /**
     * @return array{estado: string, estadopedido: string}
     */
    public static function cabeceraTransferido(): array
    {
        return ['estado' => self::TRANSFERIDO, 'estadopedido' => self::ETIQUETA_TRANSFERIDO];
    }

    public static function esTransferido(?string $estado, ?string $estadopedido = null): bool
    {
        return self::normalizarEstadoCabecera($estado, $estadopedido) === self::TRANSFERIDO;
    }
}
