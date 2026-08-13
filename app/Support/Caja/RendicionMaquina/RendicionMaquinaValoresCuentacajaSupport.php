<?php

namespace App\Support\Caja\RendicionMaquina;

use App\Models\Caja\Cuentacaja;
use App\Support\Caja\CotizacionTesoreriaConsultaSupport;

/**
 * Valores de rendición de máquinas: moneda y cotización salen de la cuenta de caja
 * (cuentacaja.moneda_id), no de valormae.
 *
 * Moneda extranjera (moneda_id > 1) se pasa a pesos con la cotización vigente de
 * cotizacion_tesoreria (día, última anterior o primera posterior).
 */
final class RendicionMaquinaValoresCuentacajaSupport
{
    /**
     * @param  list<array<string, mixed>>  $lineas
     * @return list<array<string, mixed>>
     */
    public static function enriquecerLineas(array $lineas, string $fechaYmd, int $empresaId): array
    {
        if ($lineas === []) {
            return [];
        }

        $ids = [];
        foreach ($lineas as $linea) {
            $id = (int) ($linea['cuentacaja_id'] ?? 0);
            if ($id > 0) {
                $ids[] = $id;
            }
        }
        $ids = array_values(array_unique($ids));

        $monedaPorCuenta = [];
        if ($ids !== []) {
            $monedaPorCuenta = Cuentacaja::query()
                ->whereIn('id', $ids)
                ->pluck('moneda_id', 'id')
                ->map(static fn ($v) => (int) ($v ?? 1))
                ->all();
        }

        $out = [];
        foreach ($lineas as $linea) {
            $id = (int) ($linea['cuentacaja_id'] ?? 0);
            $monedaId = (int) ($monedaPorCuenta[$id] ?? $linea['moneda_id'] ?? 1);
            if ($monedaId <= 0) {
                $monedaId = CotizacionTesoreriaConsultaSupport::MONEDA_PESOS_CODIGO;
            }
            $linea['moneda_id'] = $monedaId;
            $linea['cotizacion'] = self::cotizacionParaLinea($linea, $fechaYmd, $empresaId, $monedaId);
            $out[] = $linea;
        }

        return $out;
    }

    public static function esMonedaExtranjera(int $monedaId): bool
    {
        return $monedaId > CotizacionTesoreriaConsultaSupport::MONEDA_PESOS_CODIGO;
    }

    public static function montoEnPesos(int $monedaId, float $monto, float $cotizacion): float
    {
        if (! self::esMonedaExtranjera($monedaId)) {
            return round($monto, 2);
        }

        $cot = $cotizacion > 0 ? $cotizacion : 1.0;

        return round($monto * $cot, 2);
    }

    public static function cotizacionUsable(?float $cotizacion, int $monedaId): bool
    {
        if ($cotizacion === null || $cotizacion <= 0) {
            return false;
        }
        if (self::esMonedaExtranjera($monedaId)) {
            // 0 ó 1 en ME no es cotización (regla cotización vigente).
            return $cotizacion > 1.0001;
        }

        return true;
    }

    /**
     * @param  array<string, mixed>  $linea
     */
    private static function cotizacionParaLinea(
        array $linea,
        string $fechaYmd,
        int $empresaId,
        int $monedaId,
    ): float {
        $enviada = array_key_exists('cotizacion', $linea) && $linea['cotizacion'] !== null
            ? (float) $linea['cotizacion']
            : null;

        if (self::cotizacionUsable($enviada, $monedaId)) {
            return round((float) $enviada, 6);
        }

        if (! self::esMonedaExtranjera($monedaId)) {
            return 1.0;
        }

        if ($fechaYmd === '' || $empresaId <= 0) {
            return 1.0;
        }

        return CotizacionTesoreriaConsultaSupport::calculaVenta($fechaYmd, $monedaId, $empresaId);
    }
}
