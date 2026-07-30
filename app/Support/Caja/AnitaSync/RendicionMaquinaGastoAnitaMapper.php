<?php

declare(strict_types=1);

namespace App\Support\Caja\AnitaSync;

/**
 * Líneas rendmapgasto (apertura de gastos por operación).
 */
final class RendicionMaquinaGastoAnitaMapper
{
    public static function camposInsert(): string
    {
        return implode(', ', [
            self::colNro(),
            self::colOrden(),
            self::colCodigo(),
            self::colImporte(),
        ]);
    }

    /**
     * @param  array{orden:int, codigo:int, importe:float}  $linea
     * @param  array<string, mixed>  $ctx
     */
    public static function valoresInsert(array $linea, array $ctx): string
    {
        return implode(', ', [
            (string) RendicionMaquinaCabeceraAnitaMapper::entero($ctx['nro_oper'] ?? 0),
            (string) RendicionMaquinaCabeceraAnitaMapper::entero($linea['orden'] ?? 0),
            (string) RendicionMaquinaCabeceraAnitaMapper::entero($linea['codigo'] ?? 0),
            RendicionMaquinaCabeceraAnitaMapper::decimal($linea['importe'] ?? 0),
        ]);
    }

    public static function wherePorOperacion(int $nroOper): string
    {
        return ' WHERE '.self::colNro().' = '.$nroOper;
    }

    private static function colNro(): string
    {
        return (string) config('rendicion_maquina_anita.gasto_col_nro_oper', 'renmap_nro_oper');
    }

    private static function colOrden(): string
    {
        return (string) config('rendicion_maquina_anita.gasto_col_orden', 'renmap_orden');
    }

    private static function colCodigo(): string
    {
        return (string) config('rendicion_maquina_anita.gasto_col_codigo', 'renmap_codigo');
    }

    private static function colImporte(): string
    {
        return (string) config('rendicion_maquina_anita.gasto_col_importe', 'renmap_importe');
    }
}
