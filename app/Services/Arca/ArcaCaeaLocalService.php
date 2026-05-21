<?php

namespace App\Services\Arca;

use App\Models\Ventas\ArcaCaea;
use App\Support\Ventas\CaeaQuincenaSupport;
use Carbon\Carbon;

/**
 * Resolución de CAEA desde la tabla local arca_caea (pedido quincenal ARCA en anitaERP).
 * La importación desde Anita es solo manual y por única vez: arca:importar-caea-anita.
 */
class ArcaCaeaLocalService
{
    /**
     * @return array{cae: string, fechavencimientocae: string}|null
     */
    public function buscarCaeaParaFactura(string $nroinscripcion, Carbon|string $fechaFactura): ?array
    {
        $registro = $this->buscarRegistroVigente($nroinscripcion, $fechaFactura);

        return $registro !== null ? $this->toArrayFactura($registro, $fechaFactura) : null;
    }

    public function buscarRegistroVigente(string $nroinscripcion, Carbon|string $fechaFactura): ?ArcaCaea
    {
        $cuit = preg_replace('/\D+/', '', $nroinscripcion) ?? '';
        if ($cuit === '') {
            return null;
        }

        $po = CaeaQuincenaSupport::periodoOrdenDesdeFecha($fechaFactura);
        $fechas = CaeaQuincenaSupport::fechasQuincena($po['periodo'], $po['orden']);

        return ArcaCaea::query()
            ->where('cuit', $cuit)
            ->whereIn('estado', [ArcaCaea::ESTADO_OK, ArcaCaea::ESTADO_OBSERVACION])
            ->whereNotNull('nro_caea')
            ->where('fecha_vigencia_desde', '<=', $fechas['hasta']->format('Y-m-d'))
            ->where('fecha_vigencia_hasta', '>=', $fechas['desde']->format('Y-m-d'))
            ->orderByDesc('id')
            ->first();
    }

    /**
     * @return array{cae: string, fechavencimientocae: string}
     */
    private function toArrayFactura(ArcaCaea $registro, Carbon|string $fechaFactura): array
    {
        $po = CaeaQuincenaSupport::periodoOrdenDesdeFecha($fechaFactura);
        $fechas = CaeaQuincenaSupport::fechasQuincena($po['periodo'], $po['orden']);
        $vto = $registro->fecha_vigencia_hasta
            ? $registro->fecha_vigencia_hasta->format('Ymd')
            : $fechas['hasta']->format('Ymd');

        return [
            'cae' => (string) $registro->nro_caea,
            'fechavencimientocae' => $vto,
        ];
    }
}
