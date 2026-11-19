<?php

namespace App\Support\Ventas;

use App\Models\Ventas\Descuentoventa;
use Illuminate\Support\Collection;

/**
 * Anita (El Bierzo) graba la bonificación 10x1 / 15x2 como renglón aparte
 * (penv_fl_bonif=1, precio 0). El ERP usa descuentoventa_id en la línea paga.
 */
final class PedidoAnitaBonificacionSupport
{
    private static ?Collection $catalogo = null;

    public static function esLineaBonificacionAnita(object $row): bool
    {
        $flag = trim((string) ($row->penv_fl_bonif ?? ''));
        if ($flag === '1' || strtoupper($flag) === 'S' || strtoupper($flag) === 'T') {
            return true;
        }

        return self::float($row->penv_precio ?? 0) <= 0.00001
            && self::float($row->penv_pieza ?? 0) > 0;
    }

    /**
     * Resuelve el descuento ERP (15 x 1, 10 x 2, …) a partir de piezas pagas vs regalo.
     */
    public static function resolverDescuentoventaId(float $piezaPaga, float $piezaBonif): ?int
    {
        if ($piezaPaga <= 0.00001 || $piezaBonif <= 0.00001) {
            return null;
        }

        $mejorId = null;
        $mejorDiff = PHP_FLOAT_MAX;

        foreach (self::catalogoActivos() as $dto) {
            $venta = (float) ($dto->cantidadventa ?? 0);
            $regalo = (float) ($dto->cantidaddescuento ?? 0);
            if ($venta <= 0 || $regalo <= 0) {
                continue;
            }

            $gruposPago = $piezaPaga / $venta;
            $gruposRegalo = $piezaBonif / $regalo;
            $diff = abs($gruposPago - $gruposRegalo);
            if ($diff > 0.05) {
                continue;
            }
            if ($gruposPago <= 0) {
                continue;
            }
            if ($diff < $mejorDiff) {
                $mejorDiff = $diff;
                $mejorId = (int) $dto->id;
            }
        }

        return $mejorId;
    }

    /**
     * @return Collection<int, Descuentoventa>
     */
    private static function catalogoActivos(): Collection
    {
        if (self::$catalogo === null) {
            self::$catalogo = Descuentoventa::query()
                ->where('estado', 'ACTIVO')
                ->where('tipodescuento', 'POR CANTIDAD VENDIDA')
                ->orderBy('id')
                ->get(['id', 'cantidadventa', 'cantidaddescuento', 'porcentajedescuento']);
        }

        return self::$catalogo;
    }

    private static function float(mixed $v): float
    {
        if ($v === null || $v === '') {
            return 0.0;
        }

        return (float) $v;
    }
}
