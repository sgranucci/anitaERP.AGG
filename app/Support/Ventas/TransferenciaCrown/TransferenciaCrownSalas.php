<?php

declare(strict_types=1);

namespace App\Support\Ventas\TransferenciaCrown;

/**
 * Salas de la transferencia de gastronomía (p-tr-ven.c).
 */
final class TransferenciaCrownSalas
{
    /**
     * @return list<array{codigo: string, nombre: string, ifx_server: string}>
     */
    public static function gastronomia(): array
    {
        $raw = config('ventas_transferencia_crown.salas_gastronomia', []);
        if (! is_array($raw)) {
            return [];
        }

        $out = [];
        foreach ($raw as $sala) {
            if (! is_array($sala)) {
                continue;
            }
            $codigo = strtoupper(trim((string) ($sala['codigo'] ?? '')));
            $nombre = trim((string) ($sala['nombre'] ?? ''));
            $server = trim((string) ($sala['ifx_server'] ?? ''));
            if ($codigo === '' || $nombre === '' || $server === '') {
                continue;
            }
            $out[] = [
                'codigo' => $codigo,
                'nombre' => $nombre,
                'ifx_server' => $server,
            ];
        }

        return $out;
    }

    /**
     * @return array{codigo: string, nombre: string, ifx_server: string}|null
     */
    public static function gastronomiaPorCodigo(string $codigo): ?array
    {
        $codigo = strtoupper(trim($codigo));
        foreach (self::gastronomia() as $sala) {
            if ($sala['codigo'] === $codigo) {
                return $sala;
            }
        }

        return null;
    }
}
