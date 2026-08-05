<?php

namespace App\Support\Caja;

use App\Models\Caja\CotizacionTesoreria;
use App\Models\Configuracion\Moneda;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * Lectura operativa de cotizacion_tesoreria (Anita cotiz_tes) por empresa.
 *
 * Paridad con p-vtamaquina / p-vtagastro / a-rendmaquina:
 * - Solo tasa de venta (cambio_venta_N)
 * - Fecha exacta; si no hay, última anterior; si no, última disponible (misma empresa)
 * - Moneda pesos (código/id 1) → 1
 */
class CotizacionTesoreriaConsultaSupport
{
    public const MONEDA_PESOS_CODIGO = 1;

    public const EMPRESA_DEFAULT = 1;

    /**
     * Cotización venta para moneda ERP (por id), fecha y empresa.
     * Si no hay tasa usable, retorna null (el caller decide fallback 1).
     */
    public static function ventaPorMonedaId(string|Carbon $fecha, int $monedaId, int $empresaId = self::EMPRESA_DEFAULT): ?float
    {
        if ($monedaId <= self::MONEDA_PESOS_CODIGO) {
            return 1.0;
        }

        $codigoAnita = self::codigoAnitaDesdeMonedaId($monedaId);
        if ($codigoAnita === null) {
            return null;
        }

        return self::ventaPorCodigoAnita($fecha, $codigoAnita, $empresaId);
    }

    /**
     * Cotización venta por código Anita de moneda (2..9).
     */
    public static function ventaPorCodigoAnita(
        string|Carbon $fecha,
        int $codigoAnita,
        int $empresaId = self::EMPRESA_DEFAULT,
    ): ?float {
        if ($codigoAnita === self::MONEDA_PESOS_CODIGO) {
            return 1.0;
        }
        if (! in_array($codigoAnita, CotizacionTesoreriaMonedasSupport::CODIGOS, true)) {
            return null;
        }

        $row = self::filaParaFecha($fecha, $empresaId);
        if ($row === null) {
            return null;
        }

        $valor = $row->tasaVenta($codigoAnita);
        if ($valor === null || $valor <= 0) {
            return null;
        }

        return (float) $valor;
    }

    /**
     * Misma firma útil que CotizacionService::leeCotizacionDiaria (solo venta efectiva).
     *
     * @return array{cotizacionventa: float|null, cotizacioncompra: float|null, fecha_usada: string|null}
     */
    public static function leeDiaria(
        string|Carbon $fecha,
        int $monedaId,
        int $empresaId = self::EMPRESA_DEFAULT,
    ): array {
        $row = self::filaParaFecha($fecha, $empresaId);
        $fechaUsada = $row?->fecha?->format('Y-m-d');

        if ($monedaId <= self::MONEDA_PESOS_CODIGO) {
            return [
                'cotizacionventa' => 1.0,
                'cotizacioncompra' => 1.0,
                'fecha_usada' => $fechaUsada,
            ];
        }

        $codigoAnita = self::codigoAnitaDesdeMonedaId($monedaId);
        if ($codigoAnita === null || $row === null) {
            return [
                'cotizacionventa' => null,
                'cotizacioncompra' => null,
                'fecha_usada' => $fechaUsada,
            ];
        }

        $venta = $row->tasaVenta($codigoAnita);
        $compra = $row->tasaCompra($codigoAnita);

        return [
            'cotizacionventa' => ($venta !== null && $venta > 0) ? (float) $venta : null,
            'cotizacioncompra' => ($compra !== null && $compra > 0) ? (float) $compra : null,
            'fecha_usada' => $fechaUsada,
        ];
    }

    /**
     * Cotización venta con fallback 1.0 (como calculaCotizacionVenta de sistema).
     */
    public static function calculaVenta(
        string|Carbon $fecha,
        int $monedaId,
        int $empresaId = self::EMPRESA_DEFAULT,
        ?float $cotizacion = null,
    ): float {
        if ($cotizacion !== null && $cotizacion > 0) {
            return (float) $cotizacion;
        }

        $valor = self::ventaPorMonedaId($fecha, $monedaId, $empresaId);

        return ($valor !== null && $valor > 0) ? $valor : 1.0;
    }

    public static function filaParaFecha(string|Carbon $fecha, int $empresaId = self::EMPRESA_DEFAULT): ?CotizacionTesoreria
    {
        $ymd = self::normalizarFechaYmd($fecha);
        if ($ymd === null) {
            return null;
        }

        $empresaId = $empresaId > 0 ? $empresaId : self::EMPRESA_DEFAULT;
        $anita = (int) str_replace('-', '', $ymd);

        $exacta = CotizacionTesoreria::query()
            ->where('empresa_id', $empresaId)
            ->where(function ($q) use ($ymd, $anita) {
                $q->where('fecha', $ymd)->orWhere('fecha_anita', $anita);
            })
            ->orderByDesc('fecha')
            ->first();

        if ($exacta !== null) {
            return $exacta;
        }

        $anterior = CotizacionTesoreria::query()
            ->where('empresa_id', $empresaId)
            ->where(function ($q) use ($ymd, $anita) {
                $q->where('fecha', '<', $ymd)
                    ->orWhere('fecha_anita', '<', $anita);
            })
            ->orderByDesc('fecha')
            ->orderByDesc('fecha_anita')
            ->first();

        if ($anterior !== null) {
            return $anterior;
        }

        return CotizacionTesoreria::query()
            ->where('empresa_id', $empresaId)
            ->orderByDesc('fecha')
            ->orderByDesc('fecha_anita')
            ->first();
    }

    public static function codigoAnitaDesdeMonedaId(int $monedaId): ?int
    {
        if ($monedaId <= 0) {
            return null;
        }

        return Cache::remember(
            'cotizacion_tesoreria.moneda_codigo.'.$monedaId,
            300,
            static function () use ($monedaId): ?int {
                $codigo = Moneda::query()->whereKey($monedaId)->value('codigo');
                if ($codigo === null || $codigo === '') {
                    return null;
                }
                $n = (int) $codigo;

                return $n > 0 ? $n : null;
            }
        );
    }

    private static function normalizarFechaYmd(string|Carbon $fecha): ?string
    {
        if ($fecha instanceof Carbon) {
            return $fecha->format('Y-m-d');
        }

        $fecha = trim($fecha);
        if ($fecha === '') {
            return null;
        }

        if (preg_match('/^\d{8}$/', $fecha)) {
            return substr($fecha, 0, 4).'-'.substr($fecha, 4, 2).'-'.substr($fecha, 6, 2);
        }

        try {
            return Carbon::parse($fecha)->format('Y-m-d');
        } catch (\Throwable) {
            return null;
        }
    }
}
