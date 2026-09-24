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

    /**
     * Normaliza código Anita (0–8), etiqueta ERP («Facturado») o letra ERP (F/P/A…).
     */
    public static function normalizarCodigoCabecera(?string $estadopedido, ?string $estadoErp = null): string
    {
        $raw = trim((string) $estadopedido);
        $etiquetas = self::etiquetasCabecera();
        if ($raw !== '' && array_key_exists($raw, $etiquetas)) {
            return $raw;
        }

        $porEtiqueta = array_flip($etiquetas);
        if ($raw !== '' && isset($porEtiqueta[$raw])) {
            return (string) $porEtiqueta[$raw];
        }

        $erp = strtoupper(trim((string) ($estadoErp ?? '')));
        return match ($erp) {
            PedidoEstadoErpSupport::FACTURADO => self::CAB_FACTURADO,
            PedidoEstadoErpSupport::ANULADO => self::CAB_ANULADO,
            PedidoEstadoErpSupport::ENTREGADO => self::CAB_ENTREGADO,
            PedidoEstadoErpSupport::TRANSFERIDO => self::CAB_UNIFICADO,
            default => $raw !== '' ? $raw : self::CAB_PENTREGAR,
        };
    }

    public static function etiquetaCabecera(?string $estado, ?string $estadoErp = null): string
    {
        $codigo = self::normalizarCodigoCabecera($estado, $estadoErp);
        $etiquetas = self::etiquetasCabecera();

        return $etiquetas[$codigo] ?? (trim((string) $estado) !== '' ? (string) $estado : '—');
    }

    public static function badgeClassEstadoCabecera(?string $estadopedido, ?string $estadoErp = null): string
    {
        $codigo = self::normalizarCodigoCabecera($estadopedido, $estadoErp);

        return match ($codigo) {
            self::CAB_FACTURADO, self::CAB_REFACTURA => 'badge badge-success',
            self::CAB_ENTREGADO => 'badge badge-primary',
            self::CAB_ENTRPARC => 'badge badge-info',
            self::CAB_SUSPENDIDO => 'badge badge-warning',
            self::CAB_ANULADO => 'badge badge-danger',
            self::CAB_RESERVA, self::CAB_UNIFICADO => 'badge badge-secondary',
            self::CAB_PENTREGAR => 'badge badge-light border text-dark',
            default => 'badge badge-secondary',
        };
    }

    public static function esCabeceraNoFacturable(?string $estadopedido, ?string $estadoErp = null): bool
    {
        $codigo = self::normalizarCodigoCabecera($estadopedido, $estadoErp);
        if (in_array($codigo, [self::CAB_FACTURADO, self::CAB_SUSPENDIDO, self::CAB_ANULADO], true)) {
            return true;
        }

        $erp = PedidoEstadoErpSupport::normalizarEstadoCabecera($estadoErp, $estadopedido);

        return in_array($erp, [
            PedidoEstadoErpSupport::FACTURADO,
            PedidoEstadoErpSupport::ANULADO,
            PedidoEstadoErpSupport::TRANSFERIDO,
        ], true);
    }

    public static function etiquetaItem(?string $estado): string
    {
        $estado = (string) $estado;

        return self::etiquetasItem()[$estado] ?? $estado;
    }

    /**
     * Resumen de aprobación del árbol a partir de los estados de ítem (penv_estado).
     *
     * @param  iterable<int, object|array<string, mixed>>  $items
     */
    public static function etiquetaAprobacionDesdeItems(iterable $items): string
    {
        $estados = [];
        foreach ($items as $item) {
            if (is_array($item)) {
                $estados[] = (string) ($item['estado'] ?? '');
            } else {
                $estados[] = (string) ($item->estado ?? '');
            }
        }

        if ($estados === []) {
            return '—';
        }

        if (in_array(self::ITEM_RECHAZADO, $estados, true)) {
            return 'Rechazado';
        }

        foreach ($estados as $estado) {
            if ($estado === '' || $estado === self::ITEM_PENDIENTE || $estado === self::ITEM_CONDICIONAL) {
                return 'Pendiente aprobación';
            }
        }

        return 'Aprobado';
    }

    public static function badgeClassAprobacion(string $etiqueta): string
    {
        return match ($etiqueta) {
            'Aprobado' => 'badge badge-success',
            'Rechazado' => 'badge badge-danger',
            'Pendiente aprobación' => 'badge badge-warning',
            default => 'badge badge-secondary',
        };
    }
}
