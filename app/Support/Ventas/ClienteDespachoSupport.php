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

    /**
     * El Bierzo: DESPACHO solo carga pedidos (reposición); no entra en factura de ventas, pedido ni remito.
     */
    public static function noFacturable(?int $clienteId): bool
    {
        return self::esPedidoDespacho($clienteId);
    }

    public static function mensajeNoFacturable(): string
    {
        return 'El cliente DESPACHO no se factura. Use Transferir al despacho.';
    }

    /**
     * @return array{error: string}|null
     */
    public static function errorNoFacturable(?int $clienteId): ?array
    {
        if (! self::noFacturable($clienteId)) {
            return null;
        }

        return ['error' => self::mensajeNoFacturable()];
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
