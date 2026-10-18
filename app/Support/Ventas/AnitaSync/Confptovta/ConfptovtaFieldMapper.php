<?php

declare(strict_types=1);

namespace App\Support\Ventas\AnitaSync\Confptovta;

use App\Support\Ventas\GastronomiaCrown\GastronomiaCrownPosModos;

/**
 * Mapeo de letras Anita (confptovta / a-confptovta.c) → enums ERP Crown POS.
 */
final class ConfptovtaFieldMapper
{
    /** @var array<string, string> */
    private const MODO_USO = [
        'R' => GastronomiaCrownPosModos::RESTO,
        'S' => GastronomiaCrownPosModos::SALA,
        'C' => GastronomiaCrownPosModos::COMANDERA,
        'O' => GastronomiaCrownPosModos::RESTOSOLO,
        // índices de pantalla a-confptovta (por si llegan sin cambia_var_graba)
        '0' => GastronomiaCrownPosModos::RESTO,
        '1' => GastronomiaCrownPosModos::SALA,
        '2' => GastronomiaCrownPosModos::COMANDERA,
        '3' => GastronomiaCrownPosModos::RESTOSOLO,
    ];

    /** @var array<string, string> */
    private const POLITICA_COMANDA = [
        'C' => GastronomiaCrownPosModos::POLITICA_COMANDA_SOLA,
        'F' => GastronomiaCrownPosModos::POLITICA_FACTURA_Y_COMANDA,
        'A' => GastronomiaCrownPosModos::POLITICA_FACTURA_Y_COMANDA,
        'N' => GastronomiaCrownPosModos::POLITICA_SIN_COMANDA,
        'S' => GastronomiaCrownPosModos::POLITICA_SIN_COMANDA,
        '0' => GastronomiaCrownPosModos::POLITICA_COMANDA_SOLA,
        '1' => GastronomiaCrownPosModos::POLITICA_FACTURA_Y_COMANDA,
        '2' => GastronomiaCrownPosModos::POLITICA_SIN_COMANDA,
    ];

    public static function host(object|array $row): ?string
    {
        $host = trim((string) self::val($row, 'cpvta_host'));

        return $host !== '' ? $host : null;
    }

    public static function ubicacionDescripcion(object|array $row): string
    {
        return trim((string) self::val($row, 'cpvta_ubicacion'));
    }

    public static function modoPos(object|array $row): string
    {
        $letra = strtoupper(trim((string) self::val($row, 'cpvta_modo_uso')));

        return self::MODO_USO[$letra] ?? GastronomiaCrownPosModos::COMANDERA;
    }

    public static function politicaComanda(object|array $row): string
    {
        $letra = strtoupper(trim((string) self::val($row, 'cpvta_comanda')));
        if ($letra === '') {
            return GastronomiaCrownPosModos::POLITICA_SIN_COMANDA;
        }

        return self::POLITICA_COMANDA[$letra] ?? GastronomiaCrownPosModos::POLITICA_FACTURA_Y_COMANDA;
    }

    public static function habilitaCanjeGst(object|array $row): bool
    {
        return strtoupper(trim((string) self::val($row, 'cpvta_canjea'))) === 'S';
    }

    public static function habilitaHotel(object|array $row): bool
    {
        $concepto = (int) self::val($row, 'cpvta_conc_hotel');
        $salidaHotel = trim((string) self::val($row, 'cpvta_salida_hotel'));
        $listaHotel = (int) self::val($row, 'cpvta_lista_hotel');

        return $concepto > 0 || $salidaHotel !== '' || $listaHotel > 0;
    }

    public static function enteroCodigo(object|array $row, string $campo): int
    {
        return (int) self::val($row, $campo);
    }

    public static function textoSalida(object|array $row, string $campo): string
    {
        return trim((string) self::val($row, $campo));
    }

    private static function val(object|array $row, string $key): mixed
    {
        if (is_array($row)) {
            return $row[$key] ?? null;
        }

        return $row->{$key} ?? null;
    }
}
