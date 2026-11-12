<?php

declare(strict_types=1);

namespace App\Support\Caja\Flash;

use App\Support\Caja\TesoreriaCrownSalaSupport;

/**
 * Mapa sala ERP → sucursal Anita + INFORMIXSERVER (camino A facturación juego).
 * Orden y salas operativas = TesoreriaCrownSalaSupport (sin El Faro).
 */
final class FlashCrownResultadoAnitaSalasSupport
{
    /**
     * @return list<array{codigo: string, sucursal: int, ifx_server: string, ifx_fallback?: string}>
     */
    public static function todas(): array
    {
        $mapa = [
            'CIP' => ['codigo' => 'CIP', 'sucursal' => 15, 'ifx_server' => 'cncadmin'],
            // LGR local a veces sin permiso; AC (acncadmin) tiene suc 16.
            'LGR' => ['codigo' => 'LGR', 'sucursal' => 16, 'ifx_server' => 'lgncadmin', 'ifx_fallback' => 'acncadmin'],
            'VIE' => ['codigo' => 'VIE', 'sucursal' => 17, 'ifx_server' => 'vncadmin'],
            'GRO' => ['codigo' => 'GRO', 'sucursal' => 18, 'ifx_server' => 'grncadmin'],
            'CHO' => ['codigo' => 'CHO', 'sucursal' => 35, 'ifx_server' => 'ccncadmin'],
            'CAT' => ['codigo' => 'CAT', 'sucursal' => 44, 'ifx_server' => 'cancadmin'],
            'VRE' => ['codigo' => 'VRE', 'sucursal' => 201, 'ifx_server' => 'vrncadmin'],
            'CSA' => ['codigo' => 'CSA', 'sucursal' => 202, 'ifx_server' => 'csncadmin'],
        ];

        $salas = [];
        foreach (TesoreriaCrownSalaSupport::ORDEN_OPERATIVA as $codigo) {
            if (isset($mapa[$codigo])) {
                $salas[] = $mapa[$codigo];
            }
        }

        return $salas;
    }

    /**
     * @return array{codigo: string, sucursal: int, ifx_server: string, ifx_fallback?: string}|null
     */
    public static function porCodigo(string $codigo): ?array
    {
        $codigo = strtoupper(trim($codigo));
        if (in_array($codigo, TesoreriaCrownSalaSupport::CODIGOS_CERRADAS, true)) {
            return null;
        }
        foreach (self::todas() as $fila) {
            if ($fila['codigo'] === $codigo) {
                return $fila;
            }
        }

        return null;
    }
}
