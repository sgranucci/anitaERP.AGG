<?php

namespace App\Support\Ventas\Tiendanube;

/**
 * Tiendas Tiendanube configuradas (Ferli + otras). El token vive en .env, no en la base.
 */
final class TiendanubeTiendasSupport
{
    /**
     * Definiciones del config, aunque falte el token.
     *
     * @return list<array{clave:string,nombre:string,store_id:string,access_token:string,stock_access_token:string}>
     */
    public static function definidas(): array
    {
        $raw = config('tiendanube.tiendas', []);
        if (! is_array($raw)) {
            return [];
        }

        $out = [];
        foreach ($raw as $row) {
            if (! is_array($row)) {
                continue;
            }
            $storeId = trim((string) ($row['store_id'] ?? ''));
            $nombre = trim((string) ($row['nombre'] ?? ''));
            if ($storeId === '' || $nombre === '') {
                continue;
            }
            $out[] = [
                'clave' => trim((string) ($row['clave'] ?? $storeId)),
                'nombre' => $nombre,
                'store_id' => $storeId,
                'access_token' => trim((string) ($row['access_token'] ?? '')),
                'stock_access_token' => trim((string) ($row['stock_access_token'] ?? '')),
            ];
        }

        return $out;
    }

    /**
     * Tiendas con store_id y access_token. Listas para llamar a la API.
     *
     * @return list<array{clave:string,nombre:string,store_id:string,access_token:string,stock_access_token:string}>
     */
    public static function configuradas(): array
    {
        return array_values(array_filter(
            self::definidas(),
            static fn (array $t): bool => $t['access_token'] !== ''
        ));
    }

    /**
     * @return array{clave:string,nombre:string,store_id:string,access_token:string,stock_access_token:string}|null
     */
    public static function porStoreId(string $storeId): ?array
    {
        $storeId = trim($storeId);
        if ($storeId === '') {
            return null;
        }
        foreach (self::configuradas() as $tienda) {
            if ($tienda['store_id'] === $storeId) {
                return $tienda;
            }
        }

        return null;
    }

    /**
     * Token para actualizar stock y precios. Si la tienda tiene el de inventario, ese;
     * si no, el de facturación.
     */
    public static function tokenEscrituraProductos(string $storeId): ?string
    {
        $tienda = self::porStoreId($storeId);
        if ($tienda === null) {
            return null;
        }
        if ($tienda['stock_access_token'] !== '') {
            return $tienda['stock_access_token'];
        }

        return $tienda['access_token'] !== '' ? $tienda['access_token'] : null;
    }

    public static function nombre(?string $storeId): string
    {
        $storeId = trim((string) $storeId);
        if ($storeId === '') {
            return '';
        }
        foreach (self::definidas() as $tienda) {
            if ($tienda['store_id'] === $storeId) {
                return $tienda['nombre'];
            }
        }

        return $storeId;
    }

    /**
     * Datos de pantalla, sin token.
     *
     * @return list<array{clave:string,nombre:string,store_id:string}>
     */
    public static function paraVista(): array
    {
        $out = [];
        foreach (self::configuradas() as $tienda) {
            $out[] = [
                'clave' => $tienda['clave'],
                'nombre' => $tienda['nombre'],
                'store_id' => $tienda['store_id'],
            ];
        }

        return $out;
    }
}
