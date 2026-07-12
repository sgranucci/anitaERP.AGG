<?php

declare(strict_types=1);

namespace App\Support\Caja\AnitaSync;

/**
 * Líneas rendcarton (cartones vendidos de la rendición bingo).
 */
final class RendicionBingoCartonAnitaMapper
{
    public static function camposInsert(): string
    {
        return 'rendc_nro_oper, rendc_tipo_oper, rendc_carton, rendc_valor, rendc_cantidad, rendc_total, rendc_fecha';
    }

    /**
     * @param  array{carton:int, valor:float, cantidad:int, total:float}  $linea
     * @param  array<string, mixed>  $ctx
     */
    public static function valoresInsert(array $linea, array $ctx): string
    {
        $m = RendicionBingoCabeceraAnitaMapper::class;

        return implode(', ', [
            (string) $m::entero($ctx['nro_oper'] ?? 0),
            "'".$m::texto($ctx['tipo_oper'] ?? '', 1)."'",
            (string) $m::entero($linea['carton'] ?? 0),
            $m::decimal($linea['valor'] ?? 0),
            (string) $m::entero($linea['cantidad'] ?? 0),
            $m::decimal($linea['total'] ?? 0),
            (string) $m::entero($ctx['fecha_entera'] ?? 0),
        ]);
    }

    /**
     * @param  array{carton:int, valor:float, cantidad:int, total:float}  $linea
     * @param  array<string, mixed>  $ctx
     */
    public static function valoresUpdate(array $linea, array $ctx): string
    {
        $m = RendicionBingoCabeceraAnitaMapper::class;

        return implode(', ', [
            'rendc_valor = '.$m::decimal($linea['valor'] ?? 0),
            'rendc_cantidad = '.$m::entero($linea['cantidad'] ?? 0),
            'rendc_total = '.$m::decimal($linea['total'] ?? 0),
            'rendc_fecha = '.$m::entero($ctx['fecha_entera'] ?? 0),
        ]);
    }

    public static function wherePorOperacion(int $nroOper, string $tipoOper): string
    {
        $tipoOper = RendicionBingoCabeceraAnitaMapper::texto(substr($tipoOper, 0, 1), 1);

        return " WHERE rendc_nro_oper = {$nroOper} AND rendc_tipo_oper = '{$tipoOper}'";
    }

    public static function wherePorOperacionYCarton(int $nroOper, string $tipoOper, int $carton): string
    {
        return self::wherePorOperacion($nroOper, $tipoOper)
            .' AND rendc_carton = '.RendicionBingoCabeceraAnitaMapper::entero($carton);
    }
}
