<?php

declare(strict_types=1);

namespace App\Support\Caja\AnitaSync;

/**
 * Líneas rendpremio (conceptos de la rendición bingo).
 */
final class RendicionBingoPremioAnitaMapper
{
    public static function camposInsert(): string
    {
        return 'rendp_nro_oper, rendp_tipo_oper, rendp_concepto, rendp_porcentaje, rendp_pagado, rendp_fecha, rendp_real';
    }

    /**
     * @param  array{concepto:int, porcentaje:float, pagado:float, real:float}  $linea
     * @param  array<string, mixed>  $ctx
     */
    public static function valoresInsert(array $linea, array $ctx): string
    {
        $m = RendicionBingoCabeceraAnitaMapper::class;

        return implode(', ', [
            (string) $m::entero($ctx['nro_oper'] ?? 0),
            "'".$m::texto($ctx['tipo_oper'] ?? '', 1)."'",
            (string) $m::entero($linea['concepto'] ?? 0),
            $m::decimal($linea['porcentaje'] ?? 0),
            $m::decimal($linea['pagado'] ?? 0),
            (string) $m::entero($ctx['fecha_entera'] ?? 0),
            $m::decimal($linea['real'] ?? 0),
        ]);
    }

    /**
     * @param  array{concepto:int, porcentaje:float, pagado:float, real:float}  $linea
     * @param  array<string, mixed>  $ctx
     */
    public static function valoresUpdate(array $linea, array $ctx): string
    {
        $m = RendicionBingoCabeceraAnitaMapper::class;

        return implode(', ', [
            'rendp_porcentaje = '.$m::decimal($linea['porcentaje'] ?? 0),
            'rendp_pagado = '.$m::decimal($linea['pagado'] ?? 0),
            'rendp_fecha = '.$m::entero($ctx['fecha_entera'] ?? 0),
            'rendp_real = '.$m::decimal($linea['real'] ?? 0),
        ]);
    }

    public static function wherePorOperacion(int $nroOper, string $tipoOper): string
    {
        $tipoOper = RendicionBingoCabeceraAnitaMapper::texto(substr($tipoOper, 0, 1), 1);

        return " WHERE rendp_nro_oper = {$nroOper} AND rendp_tipo_oper = '{$tipoOper}'";
    }

    public static function wherePorOperacionYConcepto(int $nroOper, string $tipoOper, int $concepto): string
    {
        return self::wherePorOperacion($nroOper, $tipoOper)
            .' AND rendp_concepto = '.RendicionBingoCabeceraAnitaMapper::entero($concepto);
    }
}
