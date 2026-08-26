<?php

namespace App\Support\Ventas;

use App\Models\Ventas\Cliente;
use App\Models\Ventas\Cliente_Entrega;
use App\Models\Ventas\Pedido;
use Illuminate\Support\Collection;

/**
 * Reglas de negocio: lugar de entrega obligatorio si el cliente tiene domicilios
 * de entrega con nombre cargado. Filas en blanco (solo espacios) no cuentan.
 */
final class ClienteEntregaPedidoSupport
{
    public static function nombreEsUsable(?string $nombre): bool
    {
        return trim((string) $nombre) !== '';
    }

    public static function etiquetaDesdePartes(?string $nombre, ?string $domicilio = null, ?string $localidad = null): string
    {
        if (self::nombreEsUsable($nombre)) {
            return trim((string) $nombre);
        }

        $domicilio = trim((string) $domicilio);
        if ($domicilio !== '') {
            return $domicilio;
        }

        return trim((string) $localidad);
    }

    public static function etiquetaEntrega(Cliente_Entrega $entrega): string
    {
        return self::etiquetaDesdePartes(
            $entrega->nombre,
            $entrega->domicilio,
            $entrega->desc_localidades ?? ''
        );
    }

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

    public static function entregasNombradasDeCliente(int $clienteId): Collection
    {
        return self::entregasDeCliente($clienteId)
            ->filter(static fn (Cliente_Entrega $entrega) => self::nombreEsUsable($entrega->nombre))
            ->values();
    }

    public static function clienteTieneLugaresEntrega(int $clienteId): bool
    {
        return self::entregasNombradasDeCliente($clienteId)->isNotEmpty();
    }

    /**
     * @return array{error: string}|null
     */
    public static function validarSeleccionParaCliente(int $clienteId, ?int $clienteEntregaId, ?string $lugarentrega = null): ?array
    {
        if (($clienteEntregaId ?? 0) > 0) {
            return self::assertPerteneceAlCliente($clienteId, (int) $clienteEntregaId);
        }

        if (! self::clienteTieneLugaresEntrega($clienteId)) {
            return null;
        }

        if (self::nombreEsUsable($lugarentrega)) {
            return null;
        }

        return ['error' => 'Debe seleccionar un lugar de entrega del cliente'];
    }

    /**
     * @return array{error: string}|null
     */
    public static function validarPedido(Cliente $cliente, Pedido $pedido): ?array
    {
        return self::validarSeleccionParaCliente(
            (int) $cliente->id,
            (int) ($pedido->cliente_entrega_id ?? 0) ?: null,
            $pedido->lugarentrega ?? null
        );
    }

    /**
     * Completa cliente_entrega_id / lugarentrega en el documento (pedido o remito)
     * desde el request, el propio documento o el único domicilio del cliente.
     *
     * @return array{error: string}|null
     */
    public static function resolverParaDocumento(
        object $documento,
        int $clienteId,
        ?int $clienteEntregaIdRequest,
        ?string $lugarentregaRequest
    ): ?array {
        $id = ($clienteEntregaIdRequest ?? 0) > 0
            ? (int) $clienteEntregaIdRequest
            : ((int) ($documento->cliente_entrega_id ?? 0) ?: null);

        $textoRequest = $lugarentregaRequest !== null && self::nombreEsUsable($lugarentregaRequest)
            ? trim((string) $lugarentregaRequest)
            : null;
        $textoDocumento = self::nombreEsUsable($documento->lugarentrega ?? null)
            ? trim((string) $documento->lugarentrega)
            : null;
        $texto = $textoRequest ?? $textoDocumento;

        if (($id ?? 0) > 0) {
            $error = self::assertPerteneceAlCliente($clienteId, (int) $id);
            if ($error !== null) {
                return $error;
            }

            $entrega = self::resolverEntrega((int) $id);
            if ($entrega) {
                $documento->cliente_entrega_id = $entrega->id;
                $documento->lugarentrega = $textoRequest ?: self::etiquetaEntrega($entrega);
            }

            return null;
        }

        if ($texto !== null) {
            $documento->lugarentrega = $texto;

            return null;
        }

        $entregas = self::entregasDeCliente($clienteId);
        $nombradas = $entregas->filter(static fn (Cliente_Entrega $e) => self::nombreEsUsable($e->nombre));

        $unica = null;
        if ($nombradas->count() === 1) {
            $unica = $nombradas->first();
        } elseif ($entregas->count() === 1) {
            $unica = $entregas->first();
        }

        if ($unica) {
            $documento->cliente_entrega_id = $unica->id;
            $documento->lugarentrega = self::etiquetaEntrega($unica);

            return null;
        }

        if ($nombradas->isNotEmpty()) {
            return ['error' => 'Debe seleccionar un lugar de entrega del cliente'];
        }

        return null;
    }

    public static function persistirDocumento(object $documento): void
    {
        if (! method_exists($documento, 'isDirty') || ! method_exists($documento, 'save')) {
            return;
        }

        if ($documento->isDirty(['cliente_entrega_id', 'lugarentrega'])) {
            $documento->save();
        }
    }

    public static function resolverEntrega(?int $clienteEntregaId): ?Cliente_Entrega
    {
        if (($clienteEntregaId ?? 0) <= 0) {
            return null;
        }

        return Cliente_Entrega::query()->find($clienteEntregaId);
    }

    /**
     * @return array{error: string}|null
     */
    private static function assertPerteneceAlCliente(int $clienteId, int $clienteEntregaId): ?array
    {
        $pertenece = Cliente_Entrega::query()
            ->where('cliente_id', $clienteId)
            ->where('id', $clienteEntregaId)
            ->exists();

        if (! $pertenece) {
            return ['error' => 'El lugar de entrega seleccionado no pertenece al cliente'];
        }

        return null;
    }
}
