<?php

declare(strict_types=1);

namespace App\Support\Ventas\Gastronomia;

use App\Models\Ventas\VentaGastronomiaEmision;
use Illuminate\Database\Eloquent\Builder;

/**
 * Totales gastronomía por jornada contable (empresa), excluyendo PV estacionamiento.
 */
final class GastronomiaConciliacionGastroTotalDiaSupport
{
    /**
     * @return array{bruto: float, nc: float, neto: float}
     */
    public function totalesDiaEmpresa(int $empresaId, string $fechaJornada): array
    {
        $row = $this->queryEmisionesDiaEmpresa($empresaId, $fechaJornada)
            ->selectRaw(
                'SUM(CASE WHEN venta_gastronomia_emision.venta_factura_origen_id IS NULL THEN venta.total ELSE 0 END) as bruto'
                .', SUM(CASE WHEN venta_gastronomia_emision.venta_factura_origen_id IS NOT NULL THEN ABS(venta.total) ELSE 0 END) as nc'
            )
            ->first();

        $bruto = round((float) ($row->bruto ?? 0), 2);
        $nc = round((float) ($row->nc ?? 0), 2);

        return [
            'bruto' => $bruto,
            'nc' => $nc,
            'neto' => round($bruto - $nc, 2),
        ];
    }

    public function totalVentasErpBrutas(int $empresaId, string $fechaJornada): float
    {
        return $this->totalesDiaEmpresa($empresaId, $fechaJornada)['bruto'];
    }

    /**
     * @return Builder<VentaGastronomiaEmision>
     */
    private function queryEmisionesDiaEmpresa(int $empresaId, string $fechaJornada): Builder
    {
        $codigosExcluir = config('rendicion_gastronomia_anita.auditoria_diaria.puntoventa_codigos_solo_anita', []);

        return VentaGastronomiaEmision::query()
            ->join('venta', 'venta.id', '=', 'venta_gastronomia_emision.venta_id')
            ->join('puntoventa', 'puntoventa.id', '=', 'venta.puntoventa_id')
            ->where('puntoventa.empresa_id', $empresaId)
            ->where(function ($fecha) use ($fechaJornada) {
                $fecha->whereDate('venta.fechajornada', $fechaJornada)
                    ->orWhere(function ($legacy) use ($fechaJornada) {
                        $legacy->whereNull('venta.fechajornada')
                            ->whereDate('venta.fecha', $fechaJornada);
                    });
            })
            ->where(function ($excluir) use ($codigosExcluir) {
                if ($codigosExcluir !== []) {
                    $excluir->whereNotIn('puntoventa.codigo', $codigosExcluir);
                }
                $excluir->whereRaw('LOWER(puntoventa.nombre) NOT LIKE ?', ['%estacionamiento%'])
                    ->whereRaw('LOWER(puntoventa.nombre) NOT LIKE ?', ['%estac.%']);
            })
            ->whereDoesntHave('venta.estacionamientoEmision');
    }
}
