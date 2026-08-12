<?php

namespace App\Support\Configuracion;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Punto único para leer la cotización vigente de una moneda extranjera en una fecha.
 *
 * Regla del negocio: la tabla `cotizacion` tiene filas cargadas en cero para los días sin
 * novedad (fines de semana, feriados, tramos que el cron no trajo). Una fila en cero NO es
 * una cotización: significa "ese día no hubo cambio". Por eso la lectura saltea los ceros y
 * toma la última cotización real anterior, que es la que estuvo vigente ese día.
 *
 * Sin esta regla, quien lee la tabla obtiene 0 y termina descartando el importe o —peor—
 * tratando el dólar como si fuera un peso (coeficiente 1).
 */
class CotizacionVigenteSupport
{
    public const MONEDA_LOCAL_ID = 1;

    /** @var array<string, array{valor: float, fecha: string|null, exacta: bool, hacia_adelante: bool}> */
    private static array $cache = [];

    /**
     * Cotización de venta vigente (pesos por unidad de moneda extranjera).
     *
     * @return array{valor: float, fecha: string|null, exacta: bool, hacia_adelante: bool}
     *                                                                                    valor 0.0 = no hay ninguna cotización cargada para la moneda
     */
    public static function venta(string|Carbon|null $fecha, int $monedaId): array
    {
        return self::resolver($fecha, $monedaId, 'cotizacionventa');
    }

    /**
     * @return array{valor: float, fecha: string|null, exacta: bool, hacia_adelante: bool}
     */
    public static function compra(string|Carbon|null $fecha, int $monedaId): array
    {
        return self::resolver($fecha, $monedaId, 'cotizacioncompra');
    }

    /**
     * Valor de venta vigente; 0.0 cuando la moneda no tiene ninguna cotización cargada.
     * El caller decide qué hacer con el 0 (avisar, descartar o asumir 1).
     */
    public static function ventaValor(string|Carbon|null $fecha, int $monedaId): float
    {
        return self::venta($fecha, $monedaId)['valor'];
    }

    /**
     * Valor de venta vigente con fallback 1.0, para los flujos que no pueden cortar.
     */
    public static function ventaValorOUno(string|Carbon|null $fecha, int $monedaId): float
    {
        $valor = self::ventaValor($fecha, $monedaId);

        return $valor > 0 ? $valor : 1.0;
    }

    public static function limpiarCache(): void
    {
        self::$cache = [];
    }

    /**
     * @return array{valor: float, fecha: string|null, exacta: bool, hacia_adelante: bool}
     */
    private static function resolver(string|Carbon|null $fecha, int $monedaId, string $columna): array
    {
        if ($monedaId <= self::MONEDA_LOCAL_ID) {
            return ['valor' => 1.0, 'fecha' => null, 'exacta' => true, 'hacia_adelante' => false];
        }

        $ymd = self::normalizarFechaYmd($fecha);
        if ($ymd === null) {
            return ['valor' => 0.0, 'fecha' => null, 'exacta' => false, 'hacia_adelante' => false];
        }

        $cacheKey = $columna.'|'.$monedaId.'|'.$ymd;
        if (array_key_exists($cacheKey, self::$cache)) {
            return self::$cache[$cacheKey];
        }

        // Última cotización real (> 0) con fecha menor o igual: la que estuvo vigente ese día.
        $fila = self::baseQuery($monedaId, $columna)
            ->whereDate('c.fecha', '<=', $ymd)
            ->orderByDesc('c.fecha')
            ->first();

        $haciaAdelante = false;
        if ($fila === null) {
            // Nada antes (por ejemplo, un período anterior al inicio de la carga):
            // se usa la primera cotización real disponible para no perder el importe.
            $fila = self::baseQuery($monedaId, $columna)
                ->whereDate('c.fecha', '>', $ymd)
                ->orderBy('c.fecha')
                ->first();
            $haciaAdelante = $fila !== null;
        }

        $resultado = [
            'valor' => $fila !== null ? (float) $fila->valor : 0.0,
            'fecha' => $fila !== null ? substr((string) $fila->fecha, 0, 10) : null,
            'exacta' => $fila !== null && substr((string) $fila->fecha, 0, 10) === $ymd,
            'hacia_adelante' => $haciaAdelante,
        ];

        self::$cache[$cacheKey] = $resultado;

        return $resultado;
    }

    private static function baseQuery(int $monedaId, string $columna): \Illuminate\Database\Query\Builder
    {
        return DB::table('cotizacion_moneda as cm')
            ->join('cotizacion as c', 'c.id', '=', 'cm.cotizacion_id')
            ->where('cm.moneda_id', $monedaId)
            ->where('cm.'.$columna, '>', 0)
            ->select(['c.fecha', 'cm.'.$columna.' as valor']);
    }

    private static function normalizarFechaYmd(string|Carbon|null $fecha): ?string
    {
        if ($fecha instanceof Carbon) {
            return $fecha->format('Y-m-d');
        }

        $fecha = trim((string) $fecha);
        if ($fecha === '') {
            return null;
        }

        if (preg_match('/^\d{8}$/', $fecha)) {
            return substr($fecha, 0, 4).'-'.substr($fecha, 4, 2).'-'.substr($fecha, 6, 2);
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2}/', $fecha)) {
            return substr($fecha, 0, 10);
        }

        try {
            return Carbon::parse($fecha)->format('Y-m-d');
        } catch (\Throwable) {
            return null;
        }
    }
}
