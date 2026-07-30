<?php

declare(strict_types=1);

namespace App\Support\Caja\AnitaSync;

/**
 * Líneas rendvalor para rendición de máquinas.
 */
final class RendicionMaquinaValorAnitaMapper
{
    public static function camposInsert(): string
    {
        return 'rendv_nro_oper, rendv_tipo_oper, rendv_codigo, rendv_total, rendv_fecha, rendv_cotizacion';
    }

    /**
     * @param  array{codigo:int, total:float, cotizacion:float}  $linea
     * @param  array<string, mixed>  $ctx
     */
    public static function valoresInsert(array $linea, array $ctx): string
    {
        return implode(', ', [
            (string) RendicionMaquinaCabeceraAnitaMapper::entero($ctx['nro_oper'] ?? 0),
            "'".RendicionMaquinaCabeceraAnitaMapper::texto($ctx['tipo_oper'] ?? '', 1)."'",
            (string) RendicionMaquinaCabeceraAnitaMapper::entero($linea['codigo'] ?? 0),
            RendicionMaquinaCabeceraAnitaMapper::decimal($linea['total'] ?? 0),
            (string) RendicionMaquinaCabeceraAnitaMapper::entero($ctx['fecha_entera'] ?? 0),
            RendicionMaquinaCabeceraAnitaMapper::decimal($linea['cotizacion'] ?? 0),
        ]);
    }

    public static function wherePorOperacion(int $nroOper, string $tipoOper): string
    {
        $tipo = RendicionMaquinaCabeceraAnitaMapper::texto(substr($tipoOper, 0, 1), 1);

        return " WHERE rendv_nro_oper = {$nroOper} AND rendv_tipo_oper = '{$tipo}'";
    }
}
