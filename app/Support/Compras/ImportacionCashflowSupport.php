<?php

declare(strict_types=1);

namespace App\Support\Compras;

use App\Models\Compras\Importacion;
use Illuminate\Support\Collection;

/**
 * Proyección de egresos de importación para tesorería (Fase 5).
 */
final class ImportacionCashflowSupport
{
    /**
     * @return Collection<int, object{
     *   importacion_id: int,
     *   codigo: string,
     *   fecha: string,
     *   concepto: string,
     *   importe: float,
     *   naturaleza: string
     * }>
     */
    public static function proyectar(?int $empresaId = null, ?string $desde = null, ?string $hasta = null): Collection
    {
        $q = Importacion::query()
            ->with(['gastos.tipo'])
            ->whereNotIn('estado', [ImportacionEstados::ANULADA, ImportacionEstados::COSTOS_CERRADOS]);

        if ($empresaId) {
            $q->where('empresa_id', $empresaId);
        }

        $filas = collect();
        foreach ($q->get() as $imp) {
            /** @var Importacion $imp */
            foreach ($imp->gastos as $gasto) {
                $fecha = $gasto->fecha?->format('Y-m-d')
                    ?: $imp->fecha_eta?->format('Y-m-d')
                    ?: $imp->fecha_apertura?->format('Y-m-d');
                if (! $fecha) {
                    continue;
                }
                if ($desde && $fecha < $desde) {
                    continue;
                }
                if ($hasta && $fecha > $hasta) {
                    continue;
                }
                $filas->push((object) [
                    'importacion_id' => $imp->id,
                    'codigo' => $imp->codigo,
                    'fecha' => $fecha,
                    'concepto' => ($gasto->tipo->nombre ?? 'Gasto').' ('.$gasto->naturaleza.')',
                    'importe' => (float) ($gasto->importe_moneda_local ?: $gasto->importe),
                    'naturaleza' => (string) $gasto->naturaleza,
                ]);
            }
        }

        return $filas->sortBy('fecha')->values();
    }
}
