<?php

namespace App\Support\Ventas;

use App\Models\Ventas\Venta;
use App\Support\Ventas\AnitaImport\ClienteCuentacorrienteAnitaImportClaveSupport;

/**
 * Identidad fiscal de la factura en la guía COT (tipo/letra/PV/número).
 * Nunca usar el PV ni la letra del remito (Ferli REM R PV 9 vs FAC A PV 12).
 */
final class CotGuiaFacturaIdentidadSupport
{
    /**
     * @return array{tipo: string, letra: string, sucursal: int, numero: int}|null
     */
    public static function desdeVenta(Venta $venta): ?array
    {
        $clave = ClienteCuentacorrienteAnitaImportClaveSupport::claveDesdeCodigoVenta(
            (string) ($venta->codigo ?? '')
        );
        if ($clave !== null) {
            [$tipo, $letra, $sucursal, $numero] = explode('|', $clave);

            return self::normalizar($tipo, $letra, (int) $sucursal, (int) $numero);
        }

        $tipo = strtoupper(trim((string) ($venta->tipotransacciones->abreviatura ?? 'FAC')));
        $sucursal = (int) ($venta->puntoventas->codigo ?? 0);
        $numero = (int) ($venta->numerocomprobante ?? 0);
        if ($numero < 1) {
            return null;
        }

        return self::normalizar(
            $tipo !== '' ? $tipo : 'FAC',
            'A',
            $sucursal,
            $numero
        );
    }

    /**
     * @return array{tipo: string, letra: string, sucursal: int, numero: int}
     */
    public static function normalizar(string $tipo, string $letra, int $sucursal, int $numero): array
    {
        $tipo = strtoupper(substr(trim($tipo), 0, 3));
        $letra = strtoupper(substr(trim($letra), 0, 1));

        return [
            'tipo' => $tipo !== '' ? $tipo : 'FAC',
            'letra' => $letra !== '' ? $letra : 'A',
            'sucursal' => $sucursal,
            'numero' => $numero,
        ];
    }

    public static function pareceRemito(string $tipo, string $letra): bool
    {
        $tipo = strtoupper(trim($tipo));
        $letra = strtoupper(trim($letra));

        return $tipo === 'REM' || $letra === 'R';
    }
}
