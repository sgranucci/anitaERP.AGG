<?php

namespace App\Support\Ventas\Waitry;

/**
 * Cuerpos de alta y baja de un producto Waitry.
 *
 * Alta: Menu y Categories vacíos, solo Items. Waitry lo crea en Sin Menú.
 * Baja: la guía pública solo publica la URL. El cuerpo usa el mismo arreglo
 * Items e identifica el producto por externalId con active=false.
 */
final class WaitryArticuloMenuPayloadSupport
{
    /**
     * @return array{Menu:array<int, mixed>,Categories:array<int, mixed>,Items:list<array{externalId:string,name:string}>}
     */
    public static function alta(string $sku, string $nombre): array
    {
        return [
            'Menu' => [],
            'Categories' => [],
            'Items' => [self::item($sku, $nombre)],
        ];
    }

    /**
     * @return array{Items:list<array{externalId:string,name:string,active:bool}>}
     */
    public static function baja(string $sku, string $nombre): array
    {
        $item = self::item($sku, $nombre);
        $item['active'] = false;

        return [
            'Items' => [$item],
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{ok:bool,mensaje:string}
     */
    public static function interpretar(array $data): array
    {
        if (($data['ok'] ?? null) === false) {
            return ['ok' => false, 'mensaje' => self::mensaje($data, 'Waitry rechazó el pedido.')];
        }

        $response = $data['response'] ?? null;
        if (is_string($response) && stripos($response, 'error') !== false) {
            return ['ok' => false, 'mensaje' => trim($response)];
        }

        if (is_array($response)) {
            $error = trim((string) ($response['error'] ?? $response['message'] ?? ''));
            if ($error !== '') {
                return ['ok' => false, 'mensaje' => $error];
            }
        }

        return ['ok' => true, 'mensaje' => ''];
    }

    /**
     * @return array{externalId:string,name:string}
     */
    private static function item(string $sku, string $nombre): array
    {
        $sku = mb_strtoupper(trim($sku), 'UTF-8');
        $nombre = trim($nombre);
        if ($nombre === '') {
            $nombre = $sku;
        }

        return [
            'externalId' => $sku,
            'name' => mb_substr($nombre, 0, 180),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private static function mensaje(array $data, string $defecto): string
    {
        $msg = $data['msg'] ?? $data['message'] ?? $data['error'] ?? $defecto;
        if (is_array($msg)) {
            $msg = json_encode($msg, JSON_UNESCAPED_UNICODE);
        }

        $msg = trim((string) $msg);

        return $msg !== '' ? $msg : $defecto;
    }
}
