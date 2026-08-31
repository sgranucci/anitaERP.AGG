<?php

namespace App\Support\Ventas\Gastronomia;

use App\Models\Ventas\JornadaGastronomia;
use App\Models\Ventas\VentaGastronomiaEmision;
use App\Services\Ventas\Gastronomia\GastronomiaCierreJornadaFacturaProcesoEmisionService;
use InvalidArgumentException;

/**
 * Fuente de verdad: facturas CIERRE-JORNADA-WAITRY ya grabadas para la jornada.
 * El snapshot puede perderse al reanalizar; las ventas no.
 */
final class CierreJornadaProcesoEmisionExistenteSupport
{
    /**
     * @return list<int>
     */
    public static function ventaIdsParaJornada(JornadaGastronomia $jornada): array
    {
        $empresaId = (int) $jornada->empresa_id;
        $fechaJornada = $jornada->fecha_jornada?->format('Y-m-d') ?? '';
        if ($empresaId <= 0 || $fechaJornada === '') {
            return [];
        }

        return VentaGastronomiaEmision::query()
            ->where('identificador_pc', GastronomiaCierreJornadaFacturaProcesoEmisionService::IDENTIFICADOR_PC_PROCESO)
            ->where('cierre_jornada_proceso_lote', '>', 0)
            ->whereHas('venta', function ($q) use ($empresaId, $fechaJornada) {
                $q->whereDate('fechajornada', $fechaJornada)
                    ->whereHas('puntoventas', static fn ($p) => $p->where('empresa_id', $empresaId));
            })
            ->orderBy('venta_id')
            ->pluck('venta_id')
            ->map(static fn ($id) => (int) $id)
            ->values()
            ->all();
    }

    public static function existeParaJornada(JornadaGastronomia $jornada): bool
    {
        return self::ventaIdsParaJornada($jornada) !== [];
    }

    public static function assertNoVentaProcesoParaJornada(JornadaGastronomia $jornada): void
    {
        $ids = self::ventaIdsParaJornada($jornada);
        if ($ids === []) {
            return;
        }

        $ref = count($ids) === 1
            ? 'venta #'.$ids[0]
            : count($ids).' facturas (venta #'.implode(', #', $ids).')';

        throw new InvalidArgumentException(
            'Ya se emitió la facturación del proceso para esta jornada ('.$ref.'). '
            .'No se puede emitir de nuevo. Si hace falta rehacerla, use «Revertir proceso».'
        );
    }
}
