<?php

declare(strict_types=1);

namespace App\Support\Ventas\TransferenciaCrown;

use App\Support\Ventas\TipotransaccionCodigoAfipSupport;

/**
 * Normaliza un comprobante de las dos transferencias Crown.
 */
final class TransferenciaCrownComprobante
{
    /**
     * @param  array<string, mixed>  $fila
     */
    public static function esCredito(array $fila): bool
    {
        return str_starts_with(strtoupper((string) ($fila['tipo'] ?? '')), 'NC');
    }

    /**
     * Abreviatura del tipo en el ERP. NDB de ARCA no existe: se graba como NDV.
     */
    public static function abreviaturaErp(string $tipoAnita): string
    {
        $tipoAnita = strtoupper(trim($tipoAnita));

        return $tipoAnita === 'NDB' ? 'NDV' : $tipoAnita;
    }

    public static function codigoAfip(string $tipoAnita, string $letra): int
    {
        $tipo = strtoupper(trim($tipoAnita));
        $letra = strtoupper(trim($letra));
        if ($tipo === 'FAH') {
            $tipo = 'FAC';
        } elseif ($tipo === 'NCH') {
            $tipo = 'NCD';
        } elseif ($tipo === 'NDB') {
            $tipo = 'NDB';
        } elseif ($tipo === 'TIC') {
            return match ($letra) {
                'A' => 81,
                'B' => 82,
                'C' => 111,
                default => 83,
            };
        }

        return TipotransaccionCodigoAfipSupport::codigoAfipDesdeAnitaTipoLetra($tipo, $letra);
    }

    /**
     * @param  array<string, mixed>  $fila
     */
    public static function codigoVisible(array $fila): string
    {
        return sprintf(
            '%s %s %05d-%08d',
            strtoupper((string) $fila['tipo']),
            strtoupper((string) $fila['letra']),
            (int) $fila['sucursal'],
            (int) $fila['numero'],
        );
    }

    /**
     * @param  array<string, mixed>  $fila
     */
    public static function neto(array $fila): float
    {
        return round(
            (float) ($fila['gravado21'] ?? 0)
            + (float) ($fila['gravado105'] ?? 0)
            + (float) ($fila['gravado27'] ?? 0)
            + (float) ($fila['exento'] ?? 0)
            + (float) ($fila['noi'] ?? 0),
            2
        );
    }

    /**
     * @param  array<string, mixed>  $fila
     */
    public static function iva(array $fila): float
    {
        return round(
            (float) ($fila['iva21'] ?? 0)
            + (float) ($fila['iva105'] ?? 0)
            + (float) ($fila['iva27'] ?? 0),
            2
        );
    }

    /**
     * @param  list<array{dh: string, importe: float}>  $lineas
     */
    public static function desbalance(array $lineas): float
    {
        $saldo = 0.0;
        foreach ($lineas as $linea) {
            $importe = round((float) ($linea['importe'] ?? 0), 2);
            $saldo += (($linea['dh'] ?? 'D') === 'H') ? -$importe : $importe;
        }

        return round($saldo, 2);
    }
}
