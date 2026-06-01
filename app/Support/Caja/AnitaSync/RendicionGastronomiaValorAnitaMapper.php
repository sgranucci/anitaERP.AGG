<?php

namespace App\Support\Caja\AnitaSync;

use App\Models\Caja\Cuentacaja;
use App\Models\Caja\RendicionGastronomiaMovimientoCaja;

/**
 * Líneas rendvalor (medios de pago rendidos).
 */
final class RendicionGastronomiaValorAnitaMapper
{
    /**
     * @param  iterable<int, RendicionGastronomiaMovimientoCaja>  $movimientos
     * @return list<array{codigo: int, total: float, cotizacion: float}>
     */
    public static function lineasAgregadas(int $empresaId, iterable $movimientos): array
    {
        $acum = [];

        foreach ($movimientos as $mov) {
            $cuentaId = (int) ($mov->cuentacaja_id ?? 0);
            if ($cuentaId <= 0) {
                continue;
            }

            $cuenta = $mov->cuentacaja ?? Cuentacaja::query()->find($cuentaId);
            if ($cuenta === null) {
                continue;
            }

            if (RendicionGastronomiaRendvalorCodigoSupport::omitirEnRendvalorAnita($cuenta)) {
                continue;
            }

            $codigo = RendicionGastronomiaRendvalorCodigoSupport::codigoDesdeCuentacaja($empresaId, $cuenta);
            $monto = round((float) $mov->monto, 2);
            if (abs($monto) < 0.005) {
                continue;
            }

            if (! isset($acum[$codigo])) {
                $acum[$codigo] = [
                    'codigo' => $codigo,
                    'total' => 0.0,
                    'cotizacion' => round((float) ($mov->cotizacion ?? 1), 4),
                ];
            }
            $acum[$codigo]['total'] = round($acum[$codigo]['total'] + $monto, 2);
        }

        return array_values($acum);
    }

    public static function camposInsert(): string
    {
        return '
            rendv_nro_oper,
            rendv_tipo_oper,
            rendv_codigo,
            rendv_total,
            rendv_fecha,
            rendv_cotizacion
        ';
    }

    /**
     * @param  array{codigo:int,total:float,cotizacion:float}  $linea
     * @param  array<string, mixed>  $ctx
     */
    public static function valoresInsert(array $linea, array $ctx): string
    {
        return "
            '".RendicionGastronomiaCabeceraAnitaMapper::entero($ctx['nro_oper'] ?? 0)."',
            '".RendicionGastronomiaCabeceraAnitaMapper::texto($ctx['tipo_oper'] ?? '', 1)."',
            '".RendicionGastronomiaCabeceraAnitaMapper::entero($linea['codigo'] ?? 0)."',
            '".RendicionGastronomiaCabeceraAnitaMapper::decimal($linea['total'] ?? 0)."',
            '".RendicionGastronomiaCabeceraAnitaMapper::entero($ctx['fecha_entera'] ?? 0)."',
            '".RendicionGastronomiaCabeceraAnitaMapper::decimal($linea['cotizacion'] ?? 0)."'
        ";
    }

    public static function wherePorOperacion(int $nroOper, string $tipoOper): string
    {
        return " WHERE rendv_nro_oper = '".$nroOper."' AND rendv_tipo_oper = '"
            .RendicionGastronomiaCabeceraAnitaMapper::texto($tipoOper, 1)."' ";
    }

    public static function wherePorOperacionYCodigo(int $nroOper, string $tipoOper, int $codigo): string
    {
        return self::wherePorOperacion($nroOper, $tipoOper)
            ." AND rendv_codigo = '".RendicionGastronomiaCabeceraAnitaMapper::entero($codigo)."' ";
    }

    /**
     * @param  array{codigo:int,total:float,cotizacion:float}  $linea
     * @param  array<string, mixed>  $ctx
     */
    public static function valoresUpdate(array $linea, array $ctx): string
    {
        return 'rendv_total = '.RendicionGastronomiaCabeceraAnitaMapper::decimal($linea['total'] ?? 0)
            .', rendv_fecha = '.RendicionGastronomiaCabeceraAnitaMapper::entero($ctx['fecha_entera'] ?? 0)
            .', rendv_cotizacion = '.RendicionGastronomiaCabeceraAnitaMapper::decimal($linea['cotizacion'] ?? 0);
    }
}
