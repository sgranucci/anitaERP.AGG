<?php

namespace App\Support\Ventas\Waitry;

use App\Models\Ventas\JornadaGastronomia;
use Carbon\Carbon;

/**
 * Ventana del cierre de jornada Waitry.
 *
 * Ejemplo: jornada del 30/05 cerrada el 31/05 a las 07:00 → buscar comandas Waitry
 * desde 30/05 HH:MM (apertura) hasta 31/05 HH:MM (cierre), en fecha calendario real.
 */
final class WaitryCierreJornadaVentanaSupport
{
    /**
     * @return array{
     *   ventana: array{desde:Carbon,hasta:Carbon,etiqueta:string},
     *   rango_calendario: array{desde:string,hasta:string,etiqueta:string}
     * }
     */
    public static function resolverParaCierreJornada(
        string $fechaJornadaYmd,
        mixed $aperturaEn,
        mixed $cierreEn,
    ): array {
        $ventana = self::ventanaOperativa($fechaJornadaYmd, $aperturaEn, $cierreEn);
        $desde = $ventana['desde'];
        $hasta = $ventana['hasta'];

        $rangoDesde = $desde->copy()->startOfDay()->format('Y-m-d');
        $rangoHasta = $hasta->copy()->startOfDay()->format('Y-m-d');

        return [
            'ventana' => $ventana,
            'rango_calendario' => [
                'desde' => $rangoDesde,
                'hasta' => $rangoHasta,
                'etiqueta' => $desde->format('d/m/Y').' — '.$hasta->format('d/m/Y')
                    .' (días calendario API Waitry)',
            ],
        ];
    }

    /**
     * @return array{desde:Carbon,hasta:Carbon,etiqueta:string}
     */
    public static function ventanaOperativa(
        string $fechaJornadaYmd,
        mixed $aperturaEn,
        mixed $cierreEn,
    ): array {
        $inicioDiaJornada = Carbon::parse($fechaJornadaYmd)->startOfDay();

        $desde = self::parsearFecha($aperturaEn);
        if ($desde === null) {
            $desde = $inicioDiaJornada->copy();
        }

        $hasta = self::parsearFecha($cierreEn);
        if ($hasta === null) {
            $horaCorte = max(0, min(23, (int) config('gastronomia.cierre_totem_jornada_hora_corte_madrugada', 7)));
            $hasta = $inicioDiaJornada->copy()->addDay()->setTime($horaCorte, 0, 0);
        }

        if ($hasta->lt($desde)) {
            $hasta = $desde->copy();
        }

        return [
            'desde' => $desde,
            'hasta' => $hasta,
            'etiqueta' => $desde->format('d/m/Y H:i').' — '.$hasta->format('d/m/Y H:i'),
        ];
    }

    /**
     * Hora de corte del tramo: cierre real de la jornada o «ahora» si sigue abierta.
     */
    public static function resolverCierreHasta(JornadaGastronomia $jornada, ?Carbon $ahora = null): Carbon
    {
        $ahora = $ahora ?? now();

        if ((string) ($jornada->estado ?? '') === JornadaGastronomia::ESTADO_CERRADA
            && $jornada->cierre_en !== null) {
            return Carbon::parse($jornada->cierre_en);
        }

        return $ahora->copy();
    }

    /**
     * Instantáneo de colocación/cobro en Waitry (no confundir con venta.fechajornada Anita).
     */
    public static function instanteOrdenColocacion(array $orden): ?Carbon
    {
        foreach (['placed_at', 'created_at', 'placedAt'] as $clave) {
            $valor = $orden[$clave] ?? null;
            if ($valor === null || $valor === '') {
                continue;
            }
            try {
                return Carbon::parse((string) $valor);
            } catch (\Throwable) {
                continue;
            }
        }

        $ts = $orden['timestamp']['date'] ?? null;
        if ($ts !== null && $ts !== '') {
            try {
                return Carbon::parse((string) $ts);
            } catch (\Throwable) {
                return null;
            }
        }

        return null;
    }

    /**
     * Tramo operativo: orderId &gt; tope anterior (en listado) + placed_at dentro de [apertura, cierre].
     *
     * @param  array<string, mixed>  $orden
     */
    public static function ordenDentroVentanaOperativa(array $orden, Carbon $desde, Carbon $hasta): bool
    {
        $ts = self::instanteOrdenColocacion($orden);
        if ($ts === null) {
            return false;
        }

        if ($ts->lt($desde)) {
            return false;
        }

        return ! $ts->gt($hasta);
    }

    /**
     * @param  array<string, mixed>  $orden
     */
    public static function perteneceTramoOrderId(int $orderId, int $desdeExclusive): bool
    {
        return $orderId > $desdeExclusive;
    }

    private static function parsearFecha(mixed $valor): ?Carbon
    {
        if ($valor === null || $valor === '') {
            return null;
        }

        try {
            return Carbon::parse($valor);
        } catch (\Throwable) {
            return null;
        }
    }
}
