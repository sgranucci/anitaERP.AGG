<?php

declare(strict_types=1);

namespace App\Support\Ventas\GastronomiaAnitaImport;

use App\ApiAnita;
use App\Models\Ventas\Venta;
use stdClass;

/**
 * Detecta facturas Anita de estacionamiento compartiendo numeración con gastronomía (ej. PV 00020).
 * Criterio: resvta.resv_host contiene "estac" (pc-estac4, estacionamiento, etc.).
 */
final class GastronomiaAnitaImportEstacionamientoSupport
{
    public static function esHostEstacionamiento(?string $host): bool
    {
        $host = mb_strtolower(trim((string) $host));
        if ($host === '') {
            return false;
        }

        return str_contains($host, 'estac')
            || str_contains($host, 'estacionamiento')
            || str_contains($host, 'parking');
    }

    public static function esResvtaEstacionamiento(?stdClass $resvta): bool
    {
        if ($resvta === null) {
            return false;
        }

        return self::esHostEstacionamiento((string) ($resvta->resv_host ?? ''));
    }

    /**
     * Venta ya facturada en el circuito estacionamiento ERP (host en venta_estacionamiento_emision).
     */
    public static function esVentaEstacionamientoEnErp(Venta $venta): bool
    {
        if ($venta->relationLoaded('estacionamientoEmision')) {
            return $venta->estacionamientoEmision !== null;
        }

        return $venta->estacionamientoEmision()->exists();
    }

    public static function esLeyendaEstacionamiento(?string $leyenda): bool
    {
        $texto = mb_strtolower(trim((string) $leyenda));

        return $texto !== '' && str_starts_with($texto, 'estacionamiento');
    }

    /**
     * No vincular / importar a gastronomía: Anita resvta, emisión estacionamiento ERP o leyenda estacionamiento.
     */
    public static function debeOmitirCircuitoGastronomia(Venta $venta, ?stdClass $resvta = null): bool
    {
        if (self::esVentaEstacionamientoEnErp($venta)) {
            return true;
        }

        if (self::esLeyendaEstacionamiento($venta->leyenda ?? null)) {
            return true;
        }

        return self::esResvtaEstacionamiento($resvta);
    }

    /**
     * @param  list<int>  $numeros  Si vacío, no consulta Anita.
     * @return array<int, true> Números con resv_host de estacionamiento.
     */
    public static function numerosEstacionamientoEnSucursal(
        int $sucursal,
        string|int|null $empresaCodigo = null,
        array $numeros = [],
        int $empresaId = 0,
    ): array {
        if ($sucursal <= 0 || $numeros === []) {
            return [];
        }

        $numeros = array_values(array_unique(array_filter(array_map('intval', $numeros), static fn (int $n): bool => $n > 0)));
        if ($numeros === []) {
            return [];
        }

        $api = new ApiAnita;
        $map = [];

        foreach (array_chunk($numeros, 200) as $lote) {
            $in = implode(',', $lote);
            $where = " WHERE resv_sucursal = '".$sucursal."'"
                ." AND resv_letra = 'B' "
                ." AND resv_nro IN (".$in.") ";

            $parsed = ApiAnita::parsearRespuestaLista($api->apiCall(
                GastronomiaAnitaImportBridgeSupport::mergePayload([
                'acc' => 'list',
                'tabla' => 'resvta',
                'campos' => 'resv_tipo,resv_nro,resv_host',
                'whereArmado' => $where,
                'orderBy' => 'resv_nro',
            ], $empresaId)));

            foreach ($parsed['filas'] ?? [] as $row) {
                if (! self::esResvtaEstacionamiento($row)) {
                    continue;
                }
                $n = (int) ($row->resv_nro ?? 0);
                if ($n > 0) {
                    $map[$n] = true;
                }
            }
        }

        return $map;
    }

    public static function esComprobanteEstacionamientoEnAnita(
        int $sucursal,
        int $numero,
        string|int|null $empresaCodigo = null,
        int $empresaId = 0,
    ): bool {
        $map = self::numerosEstacionamientoEnSucursal($sucursal, $empresaCodigo, [$numero], $empresaId);

        return isset($map[$numero]);
    }
}
