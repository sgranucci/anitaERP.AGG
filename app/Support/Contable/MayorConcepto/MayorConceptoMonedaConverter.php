<?php

namespace App\Support\Contable\MayorConcepto;

use App\Queries\Configuracion\CotizacionQueryInterface;
use App\Support\Compras\RequisicionTotalesCabecera;
use Illuminate\Support\Facades\DB;

/**
 * Conversión de importes Anita (cod_mon + cotización) a la moneda del reporte.
 */
class MayorConceptoMonedaConverter
{
    /** @var array<string, int> */
    private array $monedaIdPorCodigoAnita = [];

    /** @var array<int, string> */
    private array $codigoAnitaPorMonedaId = [];

    public function __construct(
        private readonly CotizacionQueryInterface $cotizacionQuery,
    ) {
        foreach (DB::table('moneda')->get(['id', 'codigo', 'abreviatura']) as $moneda) {
            $codigo = trim((string) $moneda->codigo);
            if ($codigo === '') {
                continue;
            }
            $id = (int) $moneda->id;
            $this->monedaIdPorCodigoAnita[$codigo] = $id;
            $this->codigoAnitaPorMonedaId[$id] = $codigo;
        }
    }

    public function monedaIdDesdeCodigoAnita(string $codMon): int
    {
        $cod = trim($codMon);
        if ($cod === '') {
            return 1;
        }

        return $this->monedaIdPorCodigoAnita[$cod] ?? 1;
    }

    public function codigoAnitaDesdeMonedaId(int $monedaId): string
    {
        return $this->codigoAnitaPorMonedaId[$monedaId] ?? '1';
    }

    public function abreviaturaMoneda(int $monedaId): string
    {
        $cod = $this->codigoAnitaPorMonedaId[$monedaId] ?? '1';

        return match ($cod) {
            '1' => 'PES',
            '2' => 'DOL',
            '3' => 'EUR',
            default => strtoupper($cod),
        };
    }

    /**
     * Filtro equivalente a fl_mon_origen en l-mayorconc.c / l-mayor.c:
     * moneda del reporte siempre visible (aunque cotización 0); otra moneda solo con cotización.
     */
    public function movimientoVisible(
        string $codMonMovimiento,
        float $cotizacionMovimiento,
        int $monedaReporteId,
        bool $soloMonedaOrigen,
    ): bool {
        $codMov = trim($codMonMovimiento) !== '' ? trim($codMonMovimiento) : '1';
        $codReporte = $this->codigoAnitaDesdeMonedaId($monedaReporteId);

        if ($soloMonedaOrigen) {
            return $codMov === $codReporte;
        }

        if ($codMov === $codReporte) {
            return true;
        }

        return $cotizacionMovimiento >= 0.01;
    }

    public function convertirImporte(
        float $importe,
        string $codMonMovimiento,
        float $cotizacionMovimiento,
        int $fechaYmd,
        int $monedaReporteId,
    ): float {
        if ($importe === 0.0) {
            return 0.0;
        }

        $monedaOrigenId = $this->monedaIdDesdeCodigoAnita($codMonMovimiento);
        if ($monedaOrigenId === $monedaReporteId) {
            return $importe;
        }

        $cotizacion = $cotizacionMovimiento;
        if ($cotizacion < 0.01) {
            $cotizacion = RequisicionTotalesCabecera::cotizacionVentaPorMonedaEnFecha(
                $this->cotizacionQuery,
                $this->fechaYmdAString($fechaYmd),
                max($monedaOrigenId, $monedaReporteId),
            );
        }

        $coef = calculaCoeficienteMoneda($monedaReporteId, $monedaOrigenId, $cotizacion);

        return $importe * $coef;
    }

    private function fechaYmdAString(int $fechaYmd): string
    {
        $s = str_pad((string) $fechaYmd, 8, '0', STR_PAD_LEFT);

        return substr($s, 0, 4).'-'.substr($s, 4, 2).'-'.substr($s, 6, 2);
    }
}
