<?php

declare(strict_types=1);

namespace App\Support\Caja\Flash;

use App\ApiAnita;
use App\Models\Caja\Bingo\RendicionBingoCaja;
use App\Support\Contable\Sicore\SicoreEmpresaAnitaSupport;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Totales bingo del flash: ERP (rendicion_bingo_caja) con fallback Informix rendbingo (a-flash.c).
 */
final class FlashCajaBingoTotalesSupport
{
    /**
     * @return array{bingo_cant_carton: int, bingo_total_venta: float, bingo_resultado: float, fuente: string}
     */
    public static function resolver(int $empresaId, string $fechaSql): array
    {
        $erp = self::desdeErp($empresaId, $fechaSql);
        if (self::tieneDatos($erp)) {
            return array_merge($erp, ['fuente' => 'erp']);
        }

        if (! filter_var(config('bingo.flash_fallback_anita', true), FILTER_VALIDATE_BOOLEAN)) {
            return array_merge($erp, ['fuente' => 'erp']);
        }

        $anita = self::desdeAnita($empresaId, $fechaSql);
        if (self::tieneDatos($anita)) {
            Log::info('Flash bingo: fallback rendbingo Anita', [
                'empresa_id' => $empresaId,
                'fecha' => $fechaSql,
                'bingo_cant_carton' => $anita['bingo_cant_carton'],
                'bingo_total_venta' => $anita['bingo_total_venta'],
                'bingo_resultado' => $anita['bingo_resultado'],
            ]);

            return array_merge($anita, ['fuente' => 'anita_rendbingo']);
        }

        return array_merge($erp, ['fuente' => 'erp']);
    }

    /**
     * @return array{bingo_cant_carton: int, bingo_total_venta: float, bingo_resultado: float}
     */
    public static function desdeErp(int $empresaId, string $fechaSql): array
    {
        $rendiciones = RendicionBingoCaja::query()
            ->where('empresa_id', $empresaId)
            ->whereDate('fecha_jornada', $fechaSql)
            ->get();

        $bingoCant = 0;
        $bingoVenta = 0.0;
        $bingoResultado = 0.0;

        foreach ($rendiciones as $rendicion) {
            $bingoCant += (int) ($rendicion->cant_cartones ?? 0);
            $bingoVenta = round($bingoVenta + (float) ($rendicion->total_cartones ?? 0), 2);
            $bingoResultado = round(
                $bingoResultado
                + (float) ($rendicion->deposito ?? 0)
                - (float) ($rendicion->sobrante_faltante ?? 0)
                - (float) ($rendicion->vales ?? 0)
                - (float) ($rendicion->refuerzo_prestamo ?? 0)
                - (float) ($rendicion->redondeo ?? 0),
                2
            );
        }

        return [
            'bingo_cant_carton' => $bingoCant,
            'bingo_total_venta' => $bingoVenta,
            'bingo_resultado' => $bingoResultado,
        ];
    }

    /**
     * Suma cabeceras rendbingo del día (misma fórmula que a-flash.c).
     *
     * @return array{bingo_cant_carton: int, bingo_total_venta: float, bingo_resultado: float}
     */
    public static function desdeAnita(int $empresaId, string $fechaSql): array
    {
        $empresaAnita = SicoreEmpresaAnitaSupport::codigoEmpresaAnita($empresaId);
        if ($empresaAnita <= 0) {
            return self::vacios();
        }

        $fechaEntera = (int) Carbon::parse($fechaSql)->format('Ymd');
        $sistema = (string) config('rendicion_bingo_anita.sistema', 'caja');
        $tabla = (string) config('rendicion_bingo_anita.tabla_cabecera', 'rendbingo');

        try {
            $api = new ApiAnita;
            $rows = ApiAnita::decodificarListaFilas($api->apiCall([
                'acc' => 'list',
                'sistema' => $sistema,
                'tabla' => $tabla,
                'campos' => implode(',', [
                    'rendb_cant_carton',
                    'rendb_total_carton',
                    'rendb_deposito',
                    'rendb_sobrante',
                    'rendb_vales',
                    'rendb_redondeo',
                    'rendb_refuer_prest',
                ]),
                'whereArmado' => ' WHERE rendb_empresa='.$empresaAnita
                    .' AND rendb_fecha='.$fechaEntera,
            ]));

            $bingoCant = 0;
            $bingoVenta = 0.0;
            $bingoResultado = 0.0;

            foreach ($rows as $row) {
                $bingoCant += (int) ($row->rendb_cant_carton ?? 0);
                $bingoVenta = round($bingoVenta + (float) ($row->rendb_total_carton ?? 0), 2);
                $bingoResultado = round(
                    $bingoResultado
                    + (float) ($row->rendb_deposito ?? 0)
                    - (float) ($row->rendb_sobrante ?? 0)
                    - (float) ($row->rendb_vales ?? 0)
                    - (float) ($row->rendb_refuer_prest ?? 0)
                    - (float) ($row->rendb_redondeo ?? 0),
                    2
                );
            }

            return [
                'bingo_cant_carton' => $bingoCant,
                'bingo_total_venta' => $bingoVenta,
                'bingo_resultado' => $bingoResultado,
            ];
        } catch (Throwable $e) {
            Log::warning('Flash bingo rendbingo Anita: '.$e->getMessage(), [
                'empresa_id' => $empresaId,
                'empresa_anita' => $empresaAnita,
                'fecha' => $fechaSql,
            ]);

            return self::vacios();
        }
    }

    /**
     * @param  array{bingo_cant_carton?: int, bingo_total_venta?: float, bingo_resultado?: float}  $totales
     */
    private static function tieneDatos(array $totales): bool
    {
        return ((int) ($totales['bingo_cant_carton'] ?? 0)) > 0
            || abs((float) ($totales['bingo_total_venta'] ?? 0)) >= 0.01
            || abs((float) ($totales['bingo_resultado'] ?? 0)) >= 0.01;
    }

    /**
     * @return array{bingo_cant_carton: int, bingo_total_venta: float, bingo_resultado: float}
     */
    private static function vacios(): array
    {
        return [
            'bingo_cant_carton' => 0,
            'bingo_total_venta' => 0.0,
            'bingo_resultado' => 0.0,
        ];
    }
}
