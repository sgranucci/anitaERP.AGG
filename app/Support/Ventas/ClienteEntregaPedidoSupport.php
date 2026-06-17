<?php

namespace App\Support\Ventas;

use App\Models\Ventas\Cliente;
use App\Models\Ventas\Cliente_Entrega;
use App\Models\Ventas\Pedido;
use Illuminate\Support\Collection;

/**
 * Reglas de negocio: lugar de entrega obligatorio si el cliente tiene domicilios de entrega cargados.
 */
final class ClienteEntregaPedidoSupport
{
    public static function entregasDeCliente(int $clienteId): Collection
    {
        if ($clienteId <= 0) {
            return collect();
        }

        return Cliente_Entrega::query()
            ->where('cliente_id', $clienteId)
            ->orderBy('nombre')
            ->get();
    }

    public static function clienteTieneLugaresEntrega(int $clienteId): bool
    {
        return self::entregasDeCliente($clienteId)->isNotEmpty();
    }

    /**
     * @return array{error: string}|null
     */
    public static function validarSeleccionParaCliente(int $clienteId, ?int $clienteEntregaId): ?array
    {
        if (! self::clienteTieneLugaresEntrega($clienteId)) {
            return null;
        }

        if (($clienteEntregaId ?? 0) <= 0) {
            return ['error' => 'Debe seleccionar un lugar de entrega del cliente'];
        }

        $pertenece = Cliente_Entrega::query()
            ->where('cliente_id', $clienteId)
            ->where('id', $clienteEntregaId)
            ->exists();

        if (! $pertenece) {
            return ['error' => 'El lugar de entrega seleccionado no pertenece al cliente'];
        }

        return null;
    }

    /**
     * @return array{error: string}|null
     */
    public static function validarPedido(Cliente $cliente, Pedido $pedido): ?array
    {
        return self::validarSeleccionParaCliente(
            (int) $cliente->id,
            (int) ($pedido->cliente_entrega_id ?? 0) ?: null
        );
    }

    public static function resolverEntrega(?int $clienteEntregaId): ?Cliente_Entrega
    {
        if (($clienteEntregaId ?? 0) <= 0) {
            return null;
        }

        return Cliente_Entrega::query()->find($clienteEntregaId);
    }
}
