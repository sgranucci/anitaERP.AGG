<?php

declare(strict_types=1);

namespace App\Support\Contable\CanonMunicipal;

use App\Support\Contable\Efe\EfePosicionFinancieraSupport;
use App\Support\Contable\FlashContableReporteSupport;
use Carbon\Carbon;

/**
 * Cruce día a día: Flash col J (ventas_bingo) vs Posición fila VENTA BINGO.
 */
final class CanonMunicipalCruceSupport
{
    public function __construct(
        private readonly EfePosicionFinancieraSupport $posicionFinanciera = new EfePosicionFinancieraSupport(),
    ) {
    }

    /**
     * @return array{
     *   filas: list<array<string, mixed>>,
     *   total_flash: float,
     *   total_posicion: float,
     *   diferencia: float,
     *   cuadra: bool,
     *   dias_con_venta: int,
     *   dias_rango: int,
     *   desvios: list<string>
     * }
     */
    public function cruzar(int $empresaId, string $desde, string $hasta): array
    {
        $flash = $this->ventasFlashPorFecha($empresaId, $desde, $hasta);
        $posicion = $this->ventasPosicionPorFecha($empresaId, $desde, $hasta);

        $filas = [];
        $totalFlash = 0.0;
        $totalPos = 0.0;
        $cuadra = true;
        $diasConVenta = 0;
        $desvios = [];
        $tolerancia = CanonMunicipalCalendarioSupport::TOLERANCIA;

        $cursor = Carbon::parse($desde)->startOfDay();
        $fin = Carbon::parse($hasta)->startOfDay();
        while ($cursor->lte($fin)) {
            $ymd = $cursor->format('Y-m-d');
            $vFlash = round((float) ($flash[$ymd] ?? 0), 2);
            $vPos = round((float) ($posicion[$ymd] ?? 0), 2);
            $dif = round($vFlash - $vPos, 2);
            $ok = abs($dif) <= $tolerancia;
            if (! $ok) {
                $cuadra = false;
                $desvios[] = $ymd;
            }
            if (abs($vFlash) >= 0.01) {
                $diasConVenta++;
            }
            $totalFlash += $vFlash;
            $totalPos += $vPos;
            $filas[] = [
                'fecha' => $ymd,
                'venta_flash' => $vFlash,
                'venta_posicion' => $vPos,
                'diferencia' => $dif,
                'cuadra' => $ok,
                'canon' => round($vFlash * 0.04, 2), // alícuota se reaplica en service con ficha
            ];
            $cursor->addDay();
        }

        $totalFlash = round($totalFlash, 2);
        $totalPos = round($totalPos, 2);

        return [
            'filas' => $filas,
            'total_flash' => $totalFlash,
            'total_posicion' => $totalPos,
            'diferencia' => round($totalFlash - $totalPos, 2),
            'cuadra' => $cuadra,
            'dias_con_venta' => $diasConVenta,
            'dias_rango' => count($filas),
            'desvios' => $desvios,
        ];
    }

    /**
     * @return array<string, float>  Y-m-d => venta
     */
    private function ventasFlashPorFecha(int $empresaId, string $desde, string $hasta): array
    {
        $flashes = FlashContableReporteSupport::cargarRango([$empresaId], $desde, $hasta);
        $armado = FlashContableReporteSupport::armarDesdeFlashes(
            $flashes,
            [$empresaId],
            [],
            Carbon::parse($desde)->startOfDay(),
            Carbon::parse($hasta)->startOfDay(),
        );

        $out = [];
        foreach ($armado['filas'] ?? [] as $fila) {
            $ymd = (string) ($fila['fecha_iso'] ?? '');
            if ($ymd === '') {
                continue;
            }
            $out[$ymd] = (float) ($fila['empresas'][$empresaId]['ventas_bingo'] ?? 0);
        }

        return $out;
    }

    /**
     * @return array<string, float>  Y-m-d => venta
     */
    private function ventasPosicionPorFecha(int $empresaId, string $desde, string $hasta): array
    {
        $out = [];
        $cursor = Carbon::parse($desde)->startOfMonth();
        $finMes = Carbon::parse($hasta)->startOfMonth();

        while ($cursor->lte($finMes)) {
            $resultado = $this->posicionFinanciera->generar([
                'empresa_id' => $empresaId,
                'mes' => (int) $cursor->month,
                'anio' => (int) $cursor->year,
            ]);
            $porDia = [];
            foreach ($resultado['filas_ordenadas'] ?? [] as $fila) {
                if (($fila['etiqueta'] ?? '') === 'VENTA BINGO') {
                    $porDia = $fila['por_dia'] ?? [];
                    break;
                }
            }
            foreach ($porDia as $dia => $venta) {
                $ymd = sprintf('%04d-%02d-%02d', (int) $cursor->year, (int) $cursor->month, (int) $dia);
                if ($ymd >= $desde && $ymd <= $hasta) {
                    $out[$ymd] = (float) $venta;
                }
            }
            $cursor->addMonth();
        }

        return $out;
    }
}
