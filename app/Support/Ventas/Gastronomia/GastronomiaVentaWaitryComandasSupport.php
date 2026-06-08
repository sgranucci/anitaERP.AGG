<?php

namespace App\Support\Ventas\Gastronomia;

use App\Models\Ventas\Venta;
use App\Models\Ventas\VentaGastronomiaEmision;
use App\Support\Ventas\Gastronomia\VentaGastronomiaEmisionWaitrySupport;
use Carbon\Carbon;

/**
 * Comandas Waitry vinculadas a una factura gastronomía (POS o cierre de jornada).
 */
final class GastronomiaVentaWaitryComandasSupport
{
    public const IDENTIFICADOR_PC_CIERRE_JORNADA = 'CIERRE-JORNADA-WAITRY';

    /**
     * @return list<array{
     *   waitry_order_id:int,
     *   display_id:?string,
     *   referencia_waitry:?string,
     *   total:float,
     *   medio_waitry_clave:?string,
     *   medio_waitry_label:string,
     *   placed_at:?string,
     *   placed_at_fmt:?string
     * }>
     */
    public static function comandasDesdeEmision(?VentaGastronomiaEmision $emision): array
    {
        if ($emision === null) {
            return [];
        }

        $json = $emision->waitry_comandas_json;
        if (is_array($json) && $json !== []) {
            return self::normalizarLista($json);
        }

        return self::comandaUnicaDesdeEmision($emision);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function comandasDesdeVenta(?Venta $venta): array
    {
        if ($venta === null) {
            return [];
        }

        if ($venta->relationLoaded('gastronomiaEmision')) {
            return self::comandasDesdeEmision($venta->gastronomiaEmision);
        }

        $venta->loadMissing('gastronomiaEmision.cuenta');

        return self::comandasDesdeEmision($venta->gastronomiaEmision);
    }

    public static function tieneComandasWaitry(?VentaGastronomiaEmision $emision): bool
    {
        return self::comandasDesdeEmision($emision) !== [];
    }

    public static function cantidadComandas(?VentaGastronomiaEmision $emision): int
    {
        return count(self::comandasDesdeEmision($emision));
    }

    public static function totalComandas(?VentaGastronomiaEmision $emision): float
    {
        return round(array_sum(array_map(
            static fn (array $c) => (float) ($c['total'] ?? 0),
            self::comandasDesdeEmision($emision),
        )), 2);
    }

    public static function esFacturaCierreJornadaProceso(?VentaGastronomiaEmision $emision): bool
    {
        if ($emision === null) {
            return false;
        }

        if ((string) ($emision->identificador_pc ?? '') === self::IDENTIFICADOR_PC_CIERRE_JORNADA) {
            return true;
        }

        $json = $emision->waitry_comandas_json;

        return is_array($json) && count($json) > 1;
    }

    public static function etiquetaMedioWaitry(?string $clave): string
    {
        return match ($clave) {
            CierreJornadaProcesoMedioSupport::CLAVE_QR => 'QR',
            CierreJornadaProcesoMedioSupport::CLAVE_MP => 'Mercado Pago',
            CierreJornadaProcesoMedioSupport::CLAVE_EFECTIVO => 'Efectivo',
            CierreJornadaProcesoMedioSupport::CLAVE_TOTEM => 'TOTEM',
            default => $clave !== null && $clave !== '' ? $clave : '—',
        };
    }

    /**
     * @param  list<array<string, mixed>>  $raw
     * @return list<array<string, mixed>>
     */
    private static function normalizarLista(array $raw): array
    {
        $out = [];
        foreach ($raw as $row) {
            if (! is_array($row)) {
                continue;
            }
            $orderId = (int) ($row['waitry_order_id'] ?? 0);
            if ($orderId <= 0) {
                continue;
            }
            $clave = isset($row['medio_waitry_clave']) ? (string) $row['medio_waitry_clave'] : null;
            $placedAt = isset($row['placed_at']) ? (string) $row['placed_at'] : null;

            $out[] = [
                'waitry_order_id' => $orderId,
                'display_id' => self::nullableString($row['display_id'] ?? null),
                'referencia_waitry' => self::nullableString($row['referencia_waitry'] ?? null),
                'total' => round((float) ($row['total'] ?? 0), 2),
                'medio_waitry_clave' => $clave !== '' ? $clave : null,
                'medio_waitry_label' => self::etiquetaMedioWaitry($clave),
                'placed_at' => $placedAt !== '' ? $placedAt : null,
                'placed_at_fmt' => self::formatearFechaHora($placedAt),
            ];
        }

        usort($out, static fn (array $a, array $b): int => $a['waitry_order_id'] <=> $b['waitry_order_id']);

        return $out;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function comandaUnicaDesdeEmision(VentaGastronomiaEmision $emision): array
    {
        $emision->loadMissing('cuenta');
        $orderId = VentaGastronomiaEmisionWaitrySupport::resolverOrderId($emision);
        if ($orderId <= 0) {
            return [];
        }

        $displayId = trim((string) ($emision->cuenta?->waitry_display_id ?? ''));
        $referencia = trim((string) ($emision->cuenta?->referencia_waitry ?? ''));

        return [[
            'waitry_order_id' => $orderId,
            'display_id' => $displayId !== '' ? $displayId : null,
            'referencia_waitry' => $referencia !== '' ? $referencia : null,
            'total' => round((float) ($emision->venta?->total ?? 0), 2),
            'medio_waitry_clave' => null,
            'medio_waitry_label' => '—',
            'placed_at' => null,
            'placed_at_fmt' => null,
        ]];
    }

    private static function nullableString(mixed $value): ?string
    {
        $s = trim((string) ($value ?? ''));

        return $s !== '' ? $s : null;
    }

    private static function formatearFechaHora(?string $iso): ?string
    {
        if ($iso === null || trim($iso) === '') {
            return null;
        }

        try {
            return Carbon::parse($iso)->format('d/m/Y H:i:s');
        } catch (\Throwable) {
            return trim($iso);
        }
    }
}
