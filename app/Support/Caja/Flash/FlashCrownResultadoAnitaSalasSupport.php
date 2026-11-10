<?php

declare(strict_types=1);

namespace App\Support\Caja\Flash;

/**
 * Mapa sala ERP → sucursal Anita + INFORMIXSERVER (camino A facturación juego).
 */
final class FlashCrownResultadoAnitaSalasSupport
{
    /**
     * @return list<array{codigo: string, sucursal: int, ifx_server: string, ifx_fallback?: string}>
     */
    public static function todas(): array
    {
        return [
            ['codigo' => 'CIP', 'sucursal' => 15, 'ifx_server' => 'cncadmin'],
            // LGR local a veces sin permiso; AC (acncadmin) tiene suc 16.
            ['codigo' => 'LGR', 'sucursal' => 16, 'ifx_server' => 'lgncadmin', 'ifx_fallback' => 'acncadmin'],
            ['codigo' => 'VIE', 'sucursal' => 17, 'ifx_server' => 'vncadmin'],
            ['codigo' => 'GRO', 'sucursal' => 18, 'ifx_server' => 'grncadmin'],
            ['codigo' => 'EFA', 'sucursal' => 9, 'ifx_server' => 'efncadmin', 'ifx_fallback' => 'acncadmin'],
            ['codigo' => 'CHO', 'sucursal' => 35, 'ifx_server' => 'ccncadmin'],
            ['codigo' => 'CAT', 'sucursal' => 44, 'ifx_server' => 'cancadmin'],
            ['codigo' => 'VRE', 'sucursal' => 201, 'ifx_server' => 'vrncadmin'],
            ['codigo' => 'CSA', 'sucursal' => 202, 'ifx_server' => 'csncadmin'],
        ];
    }

    /**
     * @return array{codigo: string, sucursal: int, ifx_server: string, ifx_fallback?: string}|null
     */
    public static function porCodigo(string $codigo): ?array
    {
        $codigo = strtoupper(trim($codigo));
        foreach (self::todas() as $fila) {
            if ($fila['codigo'] === $codigo) {
                return $fila;
            }
        }

        return null;
    }
}
