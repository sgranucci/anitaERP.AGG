<?php

namespace App\Support\Ventas;

use App\Models\Ventas\Cliente;
use App\Support\Configuracion\EntornoEmpresaSupport;

/**
 * Cliente interno DESPACHO (pedido de reposición; no se factura).
 * Id en config('cliente.CLIENTE_DESPACHO_ID') / CLIENTE_DESPACHO_ID.
 */
final class ClienteDespachoSupport
{
    public static function id(): int
    {
        return (int) config('cliente.CLIENTE_DESPACHO_ID', 0);
    }

    public static function es(?int $clienteId): bool
    {
        $id = self::id();

        return $id > 0 && (int) $clienteId === $id;
    }

    public static function circuitoHabilitado(): bool
    {
        return EntornoEmpresaSupport::esElBierzo() && self::id() > 0;
    }

    public static function esPedidoDespacho(?int $clienteId): bool
    {
        return self::circuitoHabilitado() && self::es($clienteId);
    }

    public static function codigoErp(): string
    {
        $id = self::id();
        if ($id <= 0) {
            return '';
        }

        return ltrim((string) (Cliente::query()->whereKey($id)->value('codigo') ?? ''), '0');
    }

    public static function esCodigoAnita(?string $codigo): bool
    {
        $esperado = self::codigoErp();
        $dado = ltrim(trim((string) $codigo), '0');

        return $esperado !== '' && $dado !== '' && $dado === $esperado;
    }

    public static function pedidoPuedeTransferirse(mixed $pedido): bool
    {
        if (! is_object($pedido)) {
            return false;
        }
        if (! self::esPedidoDespacho((int) ($pedido->cliente_id ?? 0))) {
            return false;
        }

        $estado = PedidoEstadoErpSupport::normalizarEstadoCabecera(
            $pedido->estado ?? null,
            $pedido->estadopedido ?? null
        );

        return ! in_array($estado, [
            PedidoEstadoErpSupport::TRANSFERIDO,
            PedidoEstadoErpSupport::FACTURADO,
            PedidoEstadoErpSupport::ANULADO,
        ], true);
    }
}
