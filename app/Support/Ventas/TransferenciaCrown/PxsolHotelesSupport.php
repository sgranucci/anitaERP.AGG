<?php

declare(strict_types=1);

namespace App\Support\Ventas\TransferenciaCrown;

use RuntimeException;

/**
 * Hoteles PXSOL configurados para la transferencia por API.
 */
final class PxsolHotelesSupport
{
    /**
     * @return list<array{
     *   codigo: string,
     *   nombre: string,
     *   hotel_id: int,
     *   token: string,
     *   puntoventas: list<array{codigo: string, nombre: string, fiscal: int}>
     * }>
     */
    public static function listar(): array
    {
        $out = [];
        foreach ((array) config('ventas_transferencia_crown.pxsol_hoteles', []) as $hotel) {
            if (! is_array($hotel)) {
                continue;
            }
            $codigo = strtoupper(trim((string) ($hotel['codigo'] ?? '')));
            $hotelId = (int) ($hotel['hotel_id'] ?? 0);
            if ($codigo === '' || $hotelId <= 0) {
                continue;
            }
            // Token propio del hotel (config). Sin fallback a otro hotel: un token ajeno da 401.
            $token = trim((string) ($hotel['token'] ?? ''));
            $pvs = [];
            foreach ((array) ($hotel['puntoventas'] ?? []) as $pv) {
                if (! is_array($pv)) {
                    continue;
                }
                $pvCodigo = preg_replace('/\D+/', '', (string) ($pv['codigo'] ?? '')) ?? '';
                $fiscal = (int) ($pv['fiscal'] ?? $pvCodigo);
                if ($pvCodigo === '' || $fiscal <= 0) {
                    continue;
                }
                $pvs[] = [
                    'codigo' => str_pad($pvCodigo, 5, '0', STR_PAD_LEFT),
                    'nombre' => trim((string) ($pv['nombre'] ?? ('PV '.$fiscal))),
                    'fiscal' => $fiscal,
                ];
            }
            $out[] = [
                'codigo' => $codigo,
                'nombre' => trim((string) ($hotel['nombre'] ?? $codigo)),
                'hotel_id' => $hotelId,
                'token' => $token,
                'puntoventas' => $pvs,
            ];
        }

        return $out;
    }

    /**
     * @return array{
     *   codigo: string,
     *   nombre: string,
     *   hotel_id: int,
     *   token: string,
     *   puntoventas: list<array{codigo: string, nombre: string, fiscal: int}>
     * }
     */
    public static function resolver(string $codigo): array
    {
        $codigo = strtoupper(trim($codigo));
        foreach (self::listar() as $hotel) {
            if ($hotel['codigo'] === $codigo) {
                if ($hotel['token'] === '') {
                    throw new RuntimeException('El hotel '.$hotel['nombre'].' no tiene token de PXSOL configurado.');
                }

                return $hotel;
            }
        }

        throw new RuntimeException('Hotel PXSOL no configurado: '.$codigo);
    }
}
