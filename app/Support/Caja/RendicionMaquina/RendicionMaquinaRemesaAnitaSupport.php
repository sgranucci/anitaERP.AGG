<?php

declare(strict_types=1);

namespace App\Support\Caja\RendicionMaquina;

use App\ApiAnita;
use App\Support\Contable\Sicore\SicoreEmpresaAnitaSupport;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Equivalente a REMEM_lee_remesa_interna() del C (a-rendmaquina.c).
 *
 * Suma remem_importe de remesas internas (tipo I) de la fecha/empresa.
 * Solo aplica al turno mañana; T/N/C dejan vale_rep_fondo en 0.
 */
final class RendicionMaquinaRemesaAnitaSupport
{
    public const TIPO_INTERNA = 'I';

    /**
     * @return array{
     *   vale_rep_fondo: float,
     *   origen: string,
     *   remesas: list<array{nro: int, importe: float, cuenta: string}>
     * }
     */
    public static function leeRemesaInterna(int $empresaId, string $fechaYmd): array
    {
        $vacio = [
            'vale_rep_fondo' => 0.0,
            'origen' => 'ninguno',
            'remesas' => [],
        ];

        if ($empresaId <= 0 || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $fechaYmd)) {
            return $vacio;
        }

        $empresaAnita = SicoreEmpresaAnitaSupport::codigoEmpresaAnita($empresaId);
        if ($empresaAnita <= 0) {
            return $vacio;
        }

        $fechaEntera = (int) str_replace('-', '', $fechaYmd);
        $tabla = (string) config('rendicion_maquina_anita.tabla_remesa', 'rememae');
        $sistema = (string) config('rendicion_maquina_anita.sistema', 'caja');

        try {
            $raw = (new ApiAnita)->apiCall([
                'acc' => 'list',
                'sistema' => $sistema,
                'tabla' => $tabla,
                'campos' => 'remem_nro_remesa,remem_fecha,remem_empresa,remem_tipo_remesa,remem_importe,remem_cuenta,remem_estado',
                'whereArmado' => ' WHERE remem_fecha='.$fechaEntera
                    .' AND remem_empresa='.$empresaAnita
                    ." AND remem_tipo_remesa='".self::TIPO_INTERNA."'",
            ]);
            $filas = ApiAnita::decodificarListaFilas(is_string($raw) ? $raw : json_encode($raw));
        } catch (Throwable $e) {
            Log::warning('RendicionMaquina remesa Anita: '.$e->getMessage(), [
                'empresa_id' => $empresaId,
                'fecha' => $fechaYmd,
            ]);

            return $vacio;
        }

        $total = 0.0;
        $detalle = [];
        foreach ($filas as $fila) {
            $fila = is_object($fila) ? $fila : (object) $fila;
            $importe = round((float) ($fila->remem_importe ?? 0), 2);
            if (abs($importe) < 0.00001) {
                continue;
            }
            $total += $importe;
            $detalle[] = [
                'nro' => (int) ($fila->remem_nro_remesa ?? 0),
                'importe' => $importe,
                'cuenta' => trim((string) ($fila->remem_cuenta ?? '')),
            ];
        }

        $total = round($total, 2);

        return [
            'vale_rep_fondo' => $total,
            'origen' => $detalle === [] ? 'ninguno' : 'rememae',
            'remesas' => $detalle,
        ];
    }
}
