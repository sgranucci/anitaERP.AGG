<?php

namespace App\Services\Contable;

use App\Support\Contable\MayorConcepto\MayorConceptoMonedaConverter;
use App\Support\Contable\MayorConcepto\MayorConceptoPeriodoProcesador;
use Carbon\Carbon;

class MayorConceptoReporteService
{
    public function __construct(
        private readonly MayorConceptoPeriodoProcesador $procesador,
        private readonly MayorConceptoMonedaConverter $monedaConverter,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function generar(
        int $empresaId,
        ?string $fechaDesde,
        ?string $fechaHasta,
        ?int $mes,
        ?int $anio,
        bool $usarMesCompleto,
        int $monedaReporteId,
        bool $soloMonedaOrigen,
    ): array {
        [$desde, $hasta] = $this->resolverRangoFechas($fechaDesde, $fechaHasta, $mes, $anio, $usarMesCompleto);

        return $this->procesador->generar(
            $empresaId,
            (int) $desde->format('Ymd'),
            (int) $hasta->format('Ymd'),
            $monedaReporteId,
            $soloMonedaOrigen,
            $this->monedaConverter,
        );
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    private function resolverRangoFechas(
        ?string $fechaDesde,
        ?string $fechaHasta,
        ?int $mes,
        ?int $anio,
        bool $usarMesCompleto,
    ): array {
        if ($usarMesCompleto && $mes && $anio) {
            $desde = Carbon::createFromDate($anio, $mes, 1)->startOfDay();
            $hasta = $desde->copy()->endOfMonth();

            return [$desde, $hasta];
        }

        $desde = Carbon::parse($fechaDesde ?? now()->startOfMonth()->toDateString())->startOfDay();
        $hasta = Carbon::parse($fechaHasta ?? now()->toDateString())->startOfDay();

        if ($hasta->lt($desde)) {
            $hasta = $desde->copy();
        }

        return [$desde, $hasta];
    }
}
