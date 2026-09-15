<?php

namespace App\Support\Ventas\CertificadoSanitario;

use App\Models\Configuracion\Localidad;
use App\Models\Configuracion\Provincia;
use App\Models\Ventas\Cliente;
use App\Models\Ventas\Cliente_Entrega;
use App\Models\Ventas\Pedido;
use App\Models\Ventas\Zonavta;

/**
 * Ubicación SENASA del pedido: si hay cliente_entrega_id (lugar de archivo, no texto libre),
 * mandan zona y localidad de esa entrega; si no, zona del pedido y localidad del cliente.
 */
final class CertificadoSanitarioUbicacionPedidoSupport
{
    /**
     * @return array{
     *   zona: ?Zonavta,
     *   localidad: ?Localidad,
     *   provincia: ?Provincia,
     *   desde_entrega: bool
     * }
     */
    public static function resolver(?Pedido $pedido, ?Cliente $cliente): array
    {
        $zona = $pedido?->zonavtas;
        $localidad = $cliente?->localidades;
        $provincia = $localidad?->provincias ?? $cliente?->provincias;
        $desdeEntrega = false;

        $entrega = self::entregaDeArchivo($pedido);
        if ($entrega === null) {
            return [
                'zona' => $zona,
                'localidad' => $localidad,
                'provincia' => $provincia,
                'desde_entrega' => false,
            ];
        }

        if ($entrega->zonavtas) {
            $zona = $entrega->zonavtas;
            $desdeEntrega = true;
        }

        if ($entrega->localidades) {
            $localidad = $entrega->localidades;
            $provincia = $localidad->provincias ?? $entrega->provincias ?? $provincia;
            $desdeEntrega = true;
        } elseif ($entrega->provincias) {
            $provincia = $entrega->provincias;
            $desdeEntrega = true;
        }

        return [
            'zona' => $zona,
            'localidad' => $localidad,
            'provincia' => $provincia,
            'desde_entrega' => $desdeEntrega,
        ];
    }

    /**
     * Solo cuenta lugar de entrega de archivo (FK), no texto libre en pedido.lugarentrega.
     */
    public static function entregaDeArchivo(?Pedido $pedido): ?Cliente_Entrega
    {
        if ($pedido === null || (int) ($pedido->cliente_entrega_id ?? 0) <= 0) {
            return null;
        }

        $entrega = $pedido->relationLoaded('cliente_entregas')
            ? $pedido->cliente_entregas
            : $pedido->cliente_entregas()->with(['localidades.provincias', 'provincias', 'zonavtas'])->first();

        return $entrega instanceof Cliente_Entrega ? $entrega : null;
    }
}
