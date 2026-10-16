<?php

declare(strict_types=1);

namespace App\Support\Ventas;

use App\Models\Ventas\Venta;
use App\Models\Ventas\Venta_Impuesto;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * El pie de la venta a veces no trae la percepción IIBB aunque el asiento
 * (importado del subdiario) y el total del comprobante sí la incluyen.
 * Completa el renglón solo en memoria, para la factura y el IVA ventas.
 */
final class VentaPercepcionIibbAsientoSupport
{
    /**
     * @param  iterable<int, Venta>  $ventas
     */
    public static function completarPieColeccion(iterable $ventas): void
    {
        $pendientes = [];
        foreach ($ventas as $venta) {
            if (! $venta instanceof Venta) {
                continue;
            }
            if (self::yaTienePercepcion($venta->venta_impuestos ?? collect())) {
                continue;
            }
            $pendientes[] = $venta;
        }
        if ($pendientes === []) {
            return;
        }

        $importes = self::importesPorVenta(array_map(
            static fn (Venta $venta): int => (int) $venta->id,
            $pendientes
        ));

        foreach ($pendientes as $venta) {
            $importe = $importes[(int) $venta->id] ?? 0.0;
            if ($importe < 0.01 || ! self::elTotalIncluye($venta, $importe)) {
                continue;
            }
            self::insertarRenglon($venta, $importe);
        }
    }

    public static function completarPie(Venta $venta): void
    {
        self::completarPieColeccion([$venta]);
    }

    /**
     * @param  list<int>  $ventaIds
     * @return array<int, float> venta_id => percepción (haber neto, positivo)
     */
    public static function importesPorVenta(array $ventaIds): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ventaIds))));
        if ($ids === []) {
            return [];
        }

        $filas = DB::table('asiento_movimiento as m')
            ->join('asiento as a', 'a.id', '=', 'm.asiento_id')
            ->join('cuentacontable as c', 'c.id', '=', 'm.cuentacontable_id')
            ->whereIn('a.venta_id', $ids)
            ->where(function ($q): void {
                $q->where('c.nombre', 'like', '%PERCEPCION IIBB%')
                    ->orWhere('c.nombre', 'like', '%PERCEPCION I.B%')
                    ->orWhere('c.nombre', 'like', '%PERCEP IIBB%')
                    ->orWhere('c.nombre', 'like', '%PERCEP. IIBB%');
            })
            ->where('c.nombre', 'not like', '%RETENC%')
            ->groupBy('a.venta_id')
            ->selectRaw('a.venta_id as venta_id, SUM(-m.monto) as percepcion')
            ->get();

        $out = [];
        foreach ($filas as $fila) {
            $importe = round(abs((float) $fila->percepcion), 2);
            if ($importe >= 0.01) {
                $out[(int) $fila->venta_id] = $importe;
            }
        }

        return $out;
    }

    /**
     * @param  Collection<int, Venta_Impuesto>|iterable<int, Venta_Impuesto>  $impuestos
     */
    private static function yaTienePercepcion(iterable $impuestos): bool
    {
        foreach ($impuestos as $imp) {
            $concepto = (string) ($imp->concepto ?? '');
            if (stripos($concepto, 'Perc.') !== false
                || stripos($concepto, 'Percepcion IIBB') !== false
                || stripos($concepto, 'Percepción IIBB') !== false
                || stripos($concepto, 'Perc. IIBB') !== false) {
                return abs((float) ($imp->importe ?? 0)) >= 0.01;
            }
        }

        return false;
    }

    private static function elTotalIncluye(Venta $venta, float $percepcion): bool
    {
        $total = 0.0;
        $resto = 0.0;
        foreach ($venta->venta_impuestos ?? [] as $imp) {
            $concepto = trim((string) ($imp->concepto ?? ''));
            $importe = abs((float) ($imp->importe ?? 0));
            if (strcasecmp($concepto, 'Total') === 0) {
                $total = $importe;
                continue;
            }
            if (strcasecmp($concepto, 'Subtotal') === 0 || str_starts_with($concepto, 'Descuento')) {
                continue;
            }
            if (stripos($concepto, 'Gravado') === 0) {
                continue;
            }
            $resto += $importe;
        }
        if ($total < 0.01) {
            $total = abs((float) ($venta->total ?? 0));
        }
        $gravado = 0.0;
        foreach ($venta->venta_impuestos ?? [] as $imp) {
            $concepto = trim((string) ($imp->concepto ?? ''));
            if (stripos($concepto, 'Gravado') === 0) {
                $gravado += abs((float) ($imp->importe ?? 0));
            }
        }

        $gap = round($total - $gravado - $resto, 2);

        return abs($gap - $percepcion) <= 0.05;
    }

    private static function insertarRenglon(Venta $venta, float $importe): void
    {
        $fila = new Venta_Impuesto([
            'venta_id' => (int) $venta->id,
            'concepto' => 'Perc. IIBB',
            'baseimponible' => 0,
            'tasa' => 0,
            'importe' => $importe,
            'provincia_id' => null,
            'impuesto_id' => null,
        ]);
        $fila->exists = false;

        $orden = collect($venta->venta_impuestos ?? [])->values();
        $idxTotal = $orden->search(
            static fn ($imp): bool => strcasecmp(trim((string) ($imp->concepto ?? '')), 'Total') === 0
        );
        if ($idxTotal === false) {
            $orden->push($fila);
        } else {
            $antes = $orden->slice(0, (int) $idxTotal)->values();
            $despues = $orden->slice((int) $idxTotal)->values();
            $orden = $antes->push($fila)->concat($despues);
        }
        $venta->setRelation('venta_impuestos', $orden->values());
    }
}
