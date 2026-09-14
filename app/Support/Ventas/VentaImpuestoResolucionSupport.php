<?php

namespace App\Support\Ventas;

use App\Models\Ventas\Venta;
use App\Models\Ventas\Venta_Impuesto;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * En Ferli (y dumps con FK checks off) hay filas de venta_impuesto con venta_id=0
 * aunque el bloque coincide con la venta por importe Total + created_at.
 * Este support reatacha esos renglones para consulta PDF / pantalla.
 */
final class VentaImpuestoResolucionSupport
{
    /** Segundos de tolerancia entre created_at de venta y del Total. */
    private const TOLERANCIA_SEGUNDOS = 120;

    /**
     * Asegura $venta->venta_impuestos poblado (relación o huérfanos resueltos).
     */
    public static function adjuntarAVenta(Venta $venta): Venta
    {
        if ($venta->relationLoaded('venta_impuestos') && $venta->venta_impuestos->isNotEmpty()) {
            return $venta;
        }

        $directos = $venta->relationLoaded('venta_impuestos')
            ? $venta->venta_impuestos
            : $venta->venta_impuestos()->orderBy('id')->get();

        if ($directos->isNotEmpty()) {
            $venta->setRelation('venta_impuestos', $directos);

            return $venta;
        }

        $huerfanos = self::buscarBloqueHuerfano($venta);
        $venta->setRelation('venta_impuestos', $huerfanos);

        return $venta;
    }

    /**
     * @return Collection<int, Venta_Impuesto>
     */
    public static function buscarBloqueHuerfano(Venta $venta): Collection
    {
        $ventaId = (int) $venta->id;
        $total = round((float) ($venta->total ?? 0), 2);
        if ($ventaId <= 0 || $total == 0.0) {
            return collect();
        }

        $createdRaw = $venta->created_at ?? null;
        $created = null;
        if ($createdRaw) {
            try {
                $created = $createdRaw instanceof \Carbon\CarbonInterface
                    ? $createdRaw->copy()
                    : \Carbon\Carbon::parse((string) $createdRaw);
            } catch (\Throwable) {
                $created = null;
            }
        }

        $query = Venta_Impuesto::query()
            ->where('venta_id', 0)
            ->where('concepto', 'Total')
            ->whereRaw('ROUND(importe, 2) = ?', [$total]);

        if ($created) {
            $desde = $created->copy()->subSeconds(self::TOLERANCIA_SEGUNDOS)->toDateTimeString();
            $hasta = $created->copy()->addSeconds(self::TOLERANCIA_SEGUNDOS)->toDateTimeString();
            $query->whereBetween('created_at', [$desde, $hasta]);
        }

        $totales = $query->orderBy('id')->get();
        if ($totales->isEmpty()) {
            // Sin ventana temporal: solo si el importe Total es único.
            $totales = Venta_Impuesto::query()
                ->where('venta_id', 0)
                ->where('concepto', 'Total')
                ->whereRaw('ROUND(importe, 2) = ?', [$total])
                ->orderBy('id')
                ->get();
            if ($totales->count() !== 1) {
                return collect();
            }
        }

        $totalRow = $totales->first();
        $prevTotalId = (int) (Venta_Impuesto::query()
            ->where('id', '<', $totalRow->id)
            ->where('concepto', 'Total')
            ->orderByDesc('id')
            ->value('id') ?? 0);

        return Venta_Impuesto::query()
            ->where('venta_id', 0)
            ->where('id', '>', $prevTotalId)
            ->where('id', '<=', $totalRow->id)
            ->orderBy('id')
            ->get();
    }

    /**
     * Dry-run / métricas de reparación por matching.
     *
     * @return array{asignables: int, ambiguas: int, sin_match: int, muestra: list<array<string, mixed>>}
     */
    public static function estimarReparacion(int $limiteMuestra = 10): array
    {
        $ventasSin = DB::select(
            'SELECT v.id, v.total, v.created_at, v.codigo
             FROM venta v
             LEFT JOIN venta_impuesto vi ON vi.venta_id = v.id
             WHERE vi.id IS NULL
             ORDER BY v.id DESC'
        );

        $asignables = 0;
        $ambiguas = 0;
        $sinMatch = 0;
        $muestra = [];

        foreach ($ventasSin as $row) {
            $venta = new Venta([
                'total' => $row->total,
                'created_at' => $row->created_at,
            ]);
            $venta->id = (int) $row->id;
            $venta->exists = true;

            $bloque = self::buscarBloqueHuerfano($venta);
            if ($bloque->isEmpty()) {
                $sinMatch++;
                continue;
            }
            $asignables++;
            if (count($muestra) < $limiteMuestra) {
                $muestra[] = [
                    'venta_id' => (int) $row->id,
                    'codigo' => $row->codigo,
                    'total' => $row->total,
                    'lineas_impuesto' => $bloque->count(),
                    'ids' => $bloque->pluck('id')->all(),
                ];
            }
        }

        return [
            'asignables' => $asignables,
            'ambiguas' => $ambiguas,
            'sin_match' => $sinMatch,
            'muestra' => $muestra,
        ];
    }
}
