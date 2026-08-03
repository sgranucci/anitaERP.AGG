<?php

declare(strict_types=1);

namespace App\Support\Caja\Remesa;

use App\Models\Caja\Remesa;

/**
 * Suma remesas internas confirmadas en ERP (reemplazo progresivo de rememae Anita).
 */
final class RemesaInternaErpSupport
{
    /**
     * @return array{
     *   vale_rep_fondo: float,
     *   origen: string,
     *   remesas: list<array{nro: int, importe: float, cuenta: string}>
     * }
     */
    public static function leeRemesaInterna(int $empresaId, string $fechaYmd): array
    {
        $vacio = [
            'vale_rep_fondo' => 0.0,
            'origen' => 'ninguno',
            'remesas' => [],
        ];

        if ($empresaId <= 0 || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $fechaYmd)) {
            return $vacio;
        }

        $filas = Remesa::query()
            ->with(['lineasOrigen.cuentacaja:id,codigo'])
            ->where('empresa_id', $empresaId)
            ->whereDate('fecha', $fechaYmd)
            ->where('tipo', RemesaSupport::TIPO_INTERNA)
            ->where('estado', RemesaSupport::ESTADO_CONFIRMADA)
            ->orderBy('numero')
            ->orderBy('id')
            ->get(['id', 'numero', 'importe_origen', 'importe_destino']);

        if ($filas->isEmpty()) {
            return $vacio;
        }

        $total = 0.0;
        $detalle = [];
        foreach ($filas as $remesa) {
            $importe = round((float) ($remesa->importe_origen ?: $remesa->importe_destino), 2);
            if (abs($importe) < 0.00001) {
                continue;
            }
            $total += $importe;
            $cuenta = '';
            $lineaOrigen = $remesa->lineasOrigen->first();
            if ($lineaOrigen !== null && $lineaOrigen->cuentacaja !== null) {
                $cuenta = trim((string) ($lineaOrigen->cuentacaja->codigo ?? ''));
            }
            $detalle[] = [
                'nro' => (int) ($remesa->numero ?? $remesa->id),
                'importe' => $importe,
                'cuenta' => $cuenta,
            ];
        }

        $total = round($total, 2);

        return [
            'vale_rep_fondo' => $total,
            'origen' => $detalle === [] ? 'ninguno' : 'remesa_erp',
            'remesas' => $detalle,
        ];
    }
}
