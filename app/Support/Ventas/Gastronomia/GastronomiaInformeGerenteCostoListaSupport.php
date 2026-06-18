<?php

namespace App\Support\Ventas\Gastronomia;

use Carbon\Carbon;

/**
 * Listas Anita stkpre para costos del informe gerente: base 5000 + número de mes (ej. junio → 5006, mayo → 5005).
 * El costo se lee solo por lista (prem_lista); no se filtra por fecha de vigencia en stkpre.
 */
final class GastronomiaInformeGerenteCostoListaSupport
{
    public static function baseListaCosto(): int
    {
        return max(1, (int) config('gastronomia.informe_gerente_costo_lista_base', 5000));
    }

    /**
     * @return array{
     *   lista_actual:string,
     *   lista_anterior:string,
     *   mes_actual:int,
     *   mes_anterior:int,
     *   mes_actual_label:string,
     *   mes_anterior_label:string
     * }
     */
    public static function listasDesdeFechaJornada(string $fechaJornada): array
    {
        $fecha = Carbon::parse($fechaJornada);
        $mesActual = (int) $fecha->format('n');
        $mesAnterior = $mesActual === 1 ? 12 : $mesActual - 1;
        $base = self::baseListaCosto();

        return [
            'lista_actual' => (string) ($base + $mesActual),
            'lista_anterior' => (string) ($base + $mesAnterior),
            'mes_actual' => $mesActual,
            'mes_anterior' => $mesAnterior,
            'mes_actual_label' => self::nombreMes($mesActual),
            'mes_anterior_label' => self::nombreMes($mesAnterior),
        ];
    }

    /**
     * Variación porcentual entre costo anterior y actual: ((actual − anterior) / anterior) × 100.
     */
    public static function porcentajeDiferenciaCosto(?float $costoAnterior, ?float $costoActual): ?float
    {
        if ($costoAnterior === null || $costoActual === null || abs($costoAnterior) <= 0.0001) {
            return null;
        }

        return round((($costoActual - $costoAnterior) / $costoAnterior) * 100, 2);
    }

    private static function nombreMes(int $mes): string
    {
        $meses = [
            1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril',
            5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto',
            9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre',
        ];

        return $meses[$mes] ?? (string) $mes;
    }
}
