<?php

namespace App\Support\Ventas\Tiendanube;

use App\Models\Ventas\TiendanubePedido;

/**
 * Arma receptor fiscal / domicilio / letra A|B para emisión TN.
 */
final class TiendanubePedidoReceptorSupport
{
    public const LETRA_B = 'B';

    public const LETRA_A = 'A';

    /**
     * @param  array<string,mixed>  $inputReceptor
     * @return array{
     *   letra:string,
     *   cliente_id:int,
     *   venta_receptor:array{nombre:string,numerodocumento:string,domicilio:string,email:?string},
     *   arca_receptor:array{tipodoc:int,numerodocumento:string,nombre:string,domicilio:string}
     * }
     */
    public static function armar(TiendanubePedido $pedido, array $inputReceptor = [], ?string $letra = null): array
    {
        $letra = strtoupper(trim((string) ($letra ?: self::LETRA_B)));
        if ($letra !== self::LETRA_A) {
            $letra = self::LETRA_B;
        }

        $nombre = trim((string) ($inputReceptor['nombre'] ?? $pedido->customer_name ?? ''));
        $email = trim((string) ($inputReceptor['email'] ?? $pedido->customer_email ?? ''));
        $doc = preg_replace('/\D+/', '', (string) ($inputReceptor['numerodocumento']
            ?? $inputReceptor['nrodoc']
            ?? $pedido->customer_doc
            ?? '')) ?: '';
        $domicilio = trim((string) ($inputReceptor['domicilio'] ?? ''));
        if ($domicilio === '') {
            $domicilio = self::domicilioDesdePedido($pedido);
        }
        if ($nombre === '') {
            $nombre = $letra === self::LETRA_B
                ? (string) config('arca_wsfe.receptor.consumidor_final_razon_social', 'CONSUMIDOR FINAL')
                : 'Cliente Tiendanube';
        }

        if ($letra === self::LETRA_A) {
            $clienteId = (int) config('tiendanube.cliente_ri_id', config('tiendanube.cliente_contado_id', 1));
            $tipodoc = 80; // CUIT
            if ($doc === '' || strlen($doc) < 11) {
                throw new \InvalidArgumentException('Factura A requiere CUIT del comprador (11 dígitos).');
            }
        } else {
            $clienteId = (int) config('tiendanube.cliente_cf_id', 2194);
            if ($clienteId <= 0) {
                $clienteId = (int) config('tiendanube.cliente_contado_id', 1);
            }
            if ($doc !== '' && strlen($doc) === 11) {
                $tipodoc = 80;
            } elseif ($doc !== '' && strlen($doc) >= 7) {
                $tipodoc = 96; // DNI
            } else {
                $tipodoc = (int) config('arca_wsfe.receptor.consumidor_final_tipo_documento', 99);
                $doc = (string) config('arca_wsfe.receptor.consumidor_final_numero_documento', '0');
            }
        }

        $ventaReceptor = [
            'nombre' => $nombre,
            'numerodocumento' => $doc,
            'domicilio' => $domicilio,
            'email' => $email !== '' ? $email : null,
        ];
        $arcaReceptor = [
            'tipodoc' => $tipodoc,
            'numerodocumento' => $doc,
            'nombre' => $nombre,
            'domicilio' => $domicilio,
        ];

        return [
            'letra' => $letra,
            'cliente_id' => $clienteId,
            'venta_receptor' => $ventaReceptor,
            'arca_receptor' => $arcaReceptor,
        ];
    }

    public static function domicilioDesdePedido(TiendanubePedido $pedido): string
    {
        $payload = is_array($pedido->payload_json) ? $pedido->payload_json : [];
        $billing = $payload['billing_address'] ?? null;
        if (is_string($billing) && trim($billing) !== '') {
            // TN a veces manda string corto; completar con shipping
            $base = trim($billing);
            $ship = self::formatearDireccion(is_array($pedido->shipping_json) ? $pedido->shipping_json : []);
            if ($ship !== '' && ! str_contains(mb_strtolower($ship), mb_strtolower($base))) {
                return trim($base.' — '.$ship);
            }

            return $base;
        }
        if (is_array($billing)) {
            $fmt = self::formatearDireccion($billing);
            if ($fmt !== '') {
                return $fmt;
            }
        }

        return self::formatearDireccion(is_array($pedido->shipping_json) ? $pedido->shipping_json : []);
    }

    /**
     * @param  array<string,mixed>  $addr
     */
    public static function formatearDireccion(array $addr): string
    {
        $partes = [];
        $calle = trim((string) ($addr['address'] ?? $addr['street'] ?? ''));
        $nro = trim((string) ($addr['number'] ?? ''));
        $piso = trim((string) ($addr['floor'] ?? $addr['locality'] ?? ''));
        if ($calle !== '') {
            $partes[] = $nro !== '' ? $calle.' '.$nro : $calle;
        } elseif ($nro !== '') {
            $partes[] = $nro;
        }
        if ($piso !== '' && $piso !== ($addr['locality'] ?? null)) {
            $partes[] = $piso;
        }
        foreach (['city', 'province', 'zipcode', 'country'] as $k) {
            $v = trim((string) ($addr[$k] ?? ''));
            if ($v !== '') {
                $partes[] = $v;
            }
        }

        return trim(implode(', ', array_filter($partes)));
    }
}
