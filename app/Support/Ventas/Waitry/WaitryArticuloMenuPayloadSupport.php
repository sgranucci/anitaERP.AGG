<?php

namespace App\Support\Ventas\Waitry;

/**
 * Alta y suspensión de un artículo de catálogo en Waitry.
 * El alta con Menú y Categorías vacíos cae en Sin Menú.
 * No preparar manda el ítem en la categoría que Waitry deja fuera del filtro de cocina.
 * La suspensión usa syncItemsPOSWebhook: suspend_until es timestamp Unix.
 */
final class WaitryArticuloMenuPayloadSupport
{
    /** 2040-01-01 00:00:00 UTC. */
    public const SUSPENDER_HASTA = 2208988800;

    public const CATEGORIA_NO_PREPARAR_ID = 'NO-PREPARAR';

    public const CATEGORIA_NO_PREPARAR = 'No preparar';

    /**
     * @return array{Menu:list<mixed>,Categories:list<array<string,mixed>>,Items:list<array<string,mixed>>}
     */
    public static function alta(string $sku, string $nombre, bool $preparar = true): array
    {
        $item = self::item($sku, $nombre);
        $categorias = [];

        if (! $preparar) {
            $categorias[] = [
                'id' => self::CATEGORIA_NO_PREPARAR_ID,
                'externalId' => self::CATEGORIA_NO_PREPARAR_ID,
                'name' => self::CATEGORIA_NO_PREPARAR,
                'title' => self::CATEGORIA_NO_PREPARAR,
                'entities' => [
                    ['type' => 'ITEM', 'id' => $item['id']],
                ],
            ];
        }

        return [
            'Menu' => [],
            'Categories' => $categorias,
            'Items' => [$item],
        ];
    }

    /**
     * @return array{itemId:string,suspension_info:array{suspend_until:int}}
     */
    public static function baja(string $sku): array
    {
        return [
            'itemId' => self::itemId($sku),
            'suspension_info' => [
                'suspend_until' => self::SUSPENDER_HASTA,
            ],
        ];
    }

    /**
     * @return array{itemId:string,suspension_info:array{suspend_until:null}}
     */
    public static function reactivar(string $sku): array
    {
        return [
            'itemId' => self::itemId($sku),
            'suspension_info' => [
                'suspend_until' => null,
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{ok:bool,mensaje:string}
     */
    public static function interpretar(array $data): array
    {
        if (array_key_exists('ok', $data) && $data['ok'] === false) {
            return ['ok' => false, 'mensaje' => self::mensaje($data)];
        }

        $response = $data['response'] ?? null;
        if (is_string($response) && str_contains(mb_strtolower($response), 'error')) {
            return ['ok' => false, 'mensaje' => $response];
        }

        if (is_array($response)) {
            $error = $response['error'] ?? $response['message'] ?? null;
            if (is_string($error) && $error !== '' && ($response['result'] ?? true) === false) {
                return ['ok' => false, 'mensaje' => $error];
            }
        }

        return ['ok' => true, 'mensaje' => ''];
    }

    /**
     * @return array{id:string,externalId:string,name:string,title:string}
     */
    private static function item(string $sku, string $nombre): array
    {
        $id = self::itemId($sku);
        $titulo = trim($nombre) !== '' ? trim($nombre) : $id;

        return [
            'id' => $id,
            'externalId' => $id,
            'name' => mb_substr($titulo, 0, 180),
            'title' => mb_substr($titulo, 0, 180),
        ];
    }

    private static function itemId(string $sku): string
    {
        return mb_strtoupper(trim($sku), 'UTF-8');
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private static function mensaje(array $data): string
    {
        foreach (['msg', 'message', 'error'] as $clave) {
            $valor = $data[$clave] ?? null;
            if (is_string($valor) && trim($valor) !== '') {
                return trim($valor);
            }
        }

        return 'Waitry rechazó el envío.';
    }
}
