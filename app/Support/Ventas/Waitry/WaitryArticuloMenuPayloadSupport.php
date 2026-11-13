<?php

namespace App\Support\Ventas\Waitry;

/**
 * Alta y suspensión de un artículo de catálogo en Waitry.
 * Jerarquía: menu > categoría > item (menus.category_ids y categories.entities).
 * Preparar: menus y categories vacíos → Sin Menú.
 * No preparar: menú y categoría NO-PREPARAR con el ítem en entities.
 * La suspensión usa syncItemsPOSWebhook: suspend_until es timestamp Unix.
 */
final class WaitryArticuloMenuPayloadSupport
{
    /** 2040-01-01 00:00:00 UTC. */
    public const SUSPENDER_HASTA = 2208988800;

    public const CATEGORIA_NO_PREPARAR_ID = 'NO-PREPARAR';

    public const CATEGORIA_NO_PREPARAR = 'NO-PREPARAR';

    /**
     * @return array{menus:list<array<string,mixed>>,categories:list<array<string,mixed>>,items:list<array<string,mixed>>}
     */
    public static function alta(string $sku, string $nombre, bool $preparar = true): array
    {
        $item = self::item($sku, $nombre);

        if ($preparar) {
            return [
                'menus' => [],
                'categories' => [],
                'items' => [$item],
            ];
        }

        $categoriaId = self::CATEGORIA_NO_PREPARAR_ID;

        return [
            'menus' => [
                [
                    'id' => $categoriaId,
                    'title' => self::CATEGORIA_NO_PREPARAR,
                    'category_ids' => [$categoriaId],
                ],
            ],
            'categories' => [
                [
                    'id' => $categoriaId,
                    'title' => self::CATEGORIA_NO_PREPARAR,
                    'entities' => [
                        ['type' => 'ITEM', 'id' => $item['id']],
                    ],
                ],
            ],
            'items' => [$item],
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
     * @return array{id:string,title:string,price_info:array{price:int}}
     */
    private static function item(string $sku, string $nombre): array
    {
        $id = self::itemId($sku);
        $titulo = trim($nombre) !== '' ? trim($nombre) : $id;

        return [
            'id' => $id,
            'title' => mb_substr($titulo, 0, 180),
            'price_info' => [
                'price' => 0,
            ],
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
