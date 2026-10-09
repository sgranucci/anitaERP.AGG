<?php

declare(strict_types=1);

namespace App\Support\Ventas\FacturacionLocal;

use App\Models\Ventas\FacturacionLocalNotaCredito;
use App\Models\Ventas\Venta;

/**
 * Notas de crédito de una factura Local y el tope: la suma no supera el total de la factura.
 */
final class FacturacionLocalNotasCreditoFacturaSupport
{
    public const TOLERANCIA = 0.01;

    public static function registrar(int $ventaFacturaId, int $ventaNcId): void
    {
        if ($ventaFacturaId <= 0 || $ventaNcId <= 0) {
            return;
        }

        FacturacionLocalNotaCredito::query()->firstOrCreate(
            ['venta_nc_id' => $ventaNcId],
            ['venta_factura_id' => $ventaFacturaId],
        );
    }

    /**
     * @return list<int>
     */
    public static function ids(int $ventaFacturaId): array
    {
        if ($ventaFacturaId <= 0) {
            return [];
        }

        return FacturacionLocalNotaCredito::query()
            ->where('venta_factura_id', $ventaFacturaId)
            ->orderBy('id')
            ->pluck('venta_nc_id')
            ->map(static fn ($id): int => (int) $id)
            ->filter(static fn (int $id): bool => $id > 0)
            ->values()
            ->all();
    }

    public static function facturaIdDeNota(int $ventaNcId): ?int
    {
        if ($ventaNcId <= 0) {
            return null;
        }

        $id = FacturacionLocalNotaCredito::query()
            ->where('venta_nc_id', $ventaNcId)
            ->value('venta_factura_id');

        return $id !== null && (int) $id > 0 ? (int) $id : null;
    }

    public static function esNotaCredito(int $ventaId): bool
    {
        return self::facturaIdDeNota($ventaId) !== null;
    }

    public static function totalAcreditado(int $ventaFacturaId): float
    {
        $ids = self::ids($ventaFacturaId);
        if ($ids === []) {
            return 0.0;
        }

        $suma = 0.0;
        foreach (Venta::query()->whereIn('id', $ids)->pluck('total') as $total) {
            $suma = round($suma + abs((float) $total), 2);
        }

        return $suma;
    }

    public static function disponible(float $totalFactura, float $acreditado): float
    {
        return round(abs($totalFactura) - $acreditado, 2);
    }

    public static function mensajeSiSupera(float $totalFactura, float $acreditado, float $importeNuevo): ?string
    {
        $factura = round(abs($totalFactura), 2);
        $nuevo = round(abs($importeNuevo), 2);
        $suma = round($acreditado + $nuevo, 2);
        if ($suma - $factura <= self::TOLERANCIA) {
            return null;
        }

        $queda = round(max(0.0, $factura - $acreditado), 2);

        return 'La suma de las notas de crédito ($ '
            .number_format($acreditado, 2, ',', '.')
            .' ya acreditado + $ '
            .number_format($nuevo, 2, ',', '.')
            .' de esta nota) supera el total de la factura ($ '
            .number_format($factura, 2, ',', '.')
            .'). Disponible: $ '
            .number_format($queda, 2, ',', '.')
            .'.';
    }

    /**
     * @param  list<int>  $ventaFacturaIds
     * @return array<int, list<array{id:int,codigo:string,total:float}>>
     */
    public static function resumenPorFacturas(array $ventaFacturaIds): array
    {
        $ids = array_values(array_filter(array_map('intval', $ventaFacturaIds), static fn (int $id): bool => $id > 0));
        if ($ids === []) {
            return [];
        }

        $filas = FacturacionLocalNotaCredito::query()
            ->whereIn('venta_factura_id', $ids)
            ->with('notaCredito:id,codigo,total')
            ->orderBy('id')
            ->get();

        $out = [];
        foreach ($filas as $fila) {
            $facturaId = (int) $fila->venta_factura_id;
            $nc = $fila->notaCredito;
            $out[$facturaId][] = [
                'id' => (int) $fila->venta_nc_id,
                'codigo' => trim((string) ($nc->codigo ?? '')) !== ''
                    ? (string) $nc->codigo
                    : '#'.$fila->venta_nc_id,
                'total' => round(abs((float) ($nc->total ?? 0)), 2),
            ];
        }

        return $out;
    }
}
