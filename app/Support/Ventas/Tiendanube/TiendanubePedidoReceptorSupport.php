<?php

namespace App\Support\Ventas\Tiendanube;

use App\Models\Configuracion\Provincia;
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

        $nombre = trim((string) ($inputReceptor['nombre'] ?? ''));
        if ($nombre === '') {
            $nombre = self::nombreDesdePedido($pedido);
        }
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

    public static function nombreDesdePedido(TiendanubePedido $pedido): string
    {
        $guardado = trim((string) ($pedido->customer_name ?? ''));
        if ($guardado !== '') {
            return $guardado;
        }

        $payload = is_array($pedido->payload_json) ? $pedido->payload_json : [];

        return self::nombreDesdeOrder($payload);
    }

    /**
     * @param  array<string,mixed>  $order
     */
    public static function nombreDesdeOrder(array $order): string
    {
        $customer = is_array($order['customer'] ?? null) ? $order['customer'] : [];
        $shipping = is_array($order['shipping_address'] ?? null) ? $order['shipping_address'] : [];
        $billing = $order['billing_address'] ?? null;
        $candidatos = [
            $customer['name'] ?? null,
            $order['contact_name'] ?? null,
            $shipping['name'] ?? null,
            $order['billing_name'] ?? null,
        ];
        if (is_array($billing)) {
            $candidatos[] = trim(((string) ($billing['name'] ?? '')).' '.((string) ($billing['last_name'] ?? '')));
        }
        foreach ($candidatos as $candidato) {
            $candidato = trim((string) $candidato);
            if ($candidato !== '') {
                return $candidato;
            }
        }

        return '';
    }

    public static function provinciaTextoDesdePedido(TiendanubePedido $pedido): string
    {
        $payload = is_array($pedido->payload_json) ? $pedido->payload_json : [];
        $desdePayload = self::provinciaTextoDesdeOrder($payload);
        if ($desdePayload !== '') {
            return $desdePayload;
        }

        $shipping = is_array($pedido->shipping_json) ? $pedido->shipping_json : [];

        return trim((string) ($shipping['province'] ?? ''));
    }

    /**
     * @param  array<string,mixed>  $order
     */
    public static function provinciaTextoDesdeOrder(array $order): string
    {
        $shipping = is_array($order['shipping_address'] ?? null) ? $order['shipping_address'] : [];
        $billing = is_array($order['billing_address'] ?? null) ? $order['billing_address'] : [];
        foreach ([
            $order['billing_province'] ?? null,
            $shipping['province'] ?? null,
            $billing['province'] ?? null,
        ] as $provincia) {
            $provincia = trim((string) $provincia);
            if ($provincia !== '') {
                return $provincia;
            }
        }

        return '';
    }

    public static function provinciaIdDesdePedido(TiendanubePedido $pedido): ?int
    {
        return self::provinciaIdDesdeTexto(self::provinciaTextoDesdePedido($pedido));
    }

    public static function provinciaIdDesdeTexto(string $texto): ?int
    {
        $norm = self::normalizarProvincia($texto);
        if ($norm === '') {
            return null;
        }
        $aliases = [
            'caba' => 'capitalfederal',
            'ciudadautonomadebuenosaires' => 'capitalfederal',
            'ciudaddebuenosaires' => 'capitalfederal',
        ];
        if (isset($aliases[$norm])) {
            $norm = $aliases[$norm];
        }
        if (str_starts_with($norm, 'provinciade')) {
            $norm = substr($norm, strlen('provinciade'));
        }

        foreach (Provincia::query()->get(['id', 'nombre']) as $provincia) {
            if (self::normalizarProvincia((string) $provincia->nombre) === $norm) {
                return (int) $provincia->id;
            }
        }

        return null;
    }

    private static function normalizarProvincia(string $texto): string
    {
        $texto = mb_strtolower(trim($texto));
        $texto = strtr($texto, [
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n',
        ]);

        return preg_replace('/[^a-z0-9]+/', '', $texto) ?? '';
    }
}
