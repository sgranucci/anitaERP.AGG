<?php

declare(strict_types=1);

namespace App\Support\Contable\Anita;

use App\ApiAnita;
use Illuminate\Support\Facades\Log;

/**
 * Lectura masiva ctamov + subdiario + subhist para importación de asientos Anita → ERP.
 * Una consulta por tabla/empresa/bloque (no por asiento).
 */
final class AnitaAsientoImportBridgeReader
{
    /**
     * El bridge exporta con `UNLOAD ... DELIMITER '|'` y parte por `|` sin respetar el escape de
     * Informix, así que una descripción con `|` corre todos los campos que vienen después
     * (sistema, cotización, moneda, balancea). Por eso `*_desc_mov` va **último** en cada lista:
     * el corrimiento afecta solo al texto de la descripción y no a los datos contables.
     */
    private const CTAMOV_CAMPOS = 'ctav_empresa,ctav_nro_asiento,ctav_nro_linea,ctav_d_h,ctav_cuenta,ctav_fecha,'
        .'ctav_tipo,ctav_letra,ctav_sucursal,ctav_nro,ctav_importe,ctav_cotizacion,ctav_cod_mon,'
        .'ctav_sistema,ctav_tipo_asiento,ctav_ccosto,ctav_balancea,ctav_o_compra,ctav_asi_mon_ref,'
        .'ctav_usuario_umod,ctav_desc_mov';

    private const SUBDIARIO_CAMPOS = 'subd_empresa,subd_sistema,subd_fecha,subd_tipo,subd_letra,subd_sucursal,subd_nro,'
        .'subd_emisor,subd_tipo_mov,subd_cuenta,subd_contrapartida,subd_nro_operacion,subd_ref_tipo,subd_ref_letra,'
        .'subd_ref_sucursal,subd_ref_nro,subd_ref_sistema,subd_importe,subd_cod_mon,subd_cotizacion,'
        .'subd_nro_asiento,subd_procesado,subd_ccosto_cta,subd_ccosto_con,subd_nro_interno,subd_usuario,'
        .'subd_desc_mov';

    private const SUBHIST_CAMPOS = 'subh_empresa,subh_sistema,subh_fecha,subh_tipo,subh_letra,subh_sucursal,subh_nro,'
        .'subh_emisor,subh_tipo_mov,subh_cuenta,subh_contrapartida,subh_nro_operacion,subh_importe,subh_cod_mon,'
        .'subh_cotizacion,subh_nro_asiento,subh_ccosto_cta,subh_ccosto_con,subh_nro_interno,'
        .'subh_usuario,subh_desc_mov';

    public function __construct(
        private readonly ApiAnita $api = new ApiAnita(),
    ) {
    }

    /**
     * @return array{
     *   ctamov: list<object>,
     *   subdiario: list<object>,
     *   subhist: list<object>,
     *   errores: list<string>,
     *   timings: array<string, float|int>
     * }
     */
    public function cargarBloque(int $empresaAnita, int $fechaDesdeYmd, int $fechaHastaYmd): array
    {
        $errores = [];
        $timings = [];
        $t0 = microtime(true);

        $t = microtime(true);
        $ctamov = $this->listar(
            'ctamov',
            self::CTAMOV_CAMPOS,
            ' WHERE ctav_empresa='.$empresaAnita
            .' AND ctav_fecha BETWEEN '.$fechaDesdeYmd.' AND '.$fechaHastaYmd,
            'ctav_fecha, ctav_nro_asiento, ctav_nro_linea',
            $errores,
            'ctamov-emp'.$empresaAnita.'-'.$fechaDesdeYmd.'-'.$fechaHastaYmd,
        );
        $timings['ctamov_ms'] = round((microtime(true) - $t) * 1000, 1);
        $timings['ctamov_filas'] = count($ctamov);

        $t = microtime(true);
        $subdiario = $this->listar(
            'subdiario',
            self::SUBDIARIO_CAMPOS,
            ' WHERE subd_empresa='.$empresaAnita
            .' AND subd_fecha BETWEEN '.$fechaDesdeYmd.' AND '.$fechaHastaYmd,
            'subd_fecha, subd_nro_operacion',
            $errores,
            'subdiario-emp'.$empresaAnita.'-'.$fechaDesdeYmd.'-'.$fechaHastaYmd,
        );
        $timings['subdiario_ms'] = round((microtime(true) - $t) * 1000, 1);
        $timings['subdiario_filas'] = count($subdiario);

        $t = microtime(true);
        $subhistRaw = $this->listar(
            'subhist',
            self::SUBHIST_CAMPOS,
            ' WHERE subh_empresa='.$empresaAnita
            .' AND subh_fecha BETWEEN '.$fechaDesdeYmd.' AND '.$fechaHastaYmd,
            'subh_fecha, subh_nro_operacion',
            $errores,
            'subhist-emp'.$empresaAnita.'-'.$fechaDesdeYmd.'-'.$fechaHastaYmd,
        );
        $subhist = array_map(fn (object $fila) => $this->remapearSubhistComoSubdiario($fila), $subhistRaw);
        $timings['subhist_ms'] = round((microtime(true) - $t) * 1000, 1);
        $timings['subhist_filas'] = count($subhist);
        $timings['total_ms'] = round((microtime(true) - $t0) * 1000, 1);

        return [
            'ctamov' => $ctamov,
            'subdiario' => $subdiario,
            'subhist' => $subhist,
            'errores' => $errores,
            'timings' => $timings,
        ];
    }

    /**
     * @param  list<string>  $errores
     * @return list<object>
     */
    private function listar(
        string $tabla,
        string $campos,
        string $whereArmado,
        string $orderBy,
        array &$errores,
        string $etiqueta,
    ): array {
        $payload = [
            'acc' => 'list',
            'sistema' => 'contab',
            'tabla' => $tabla,
            'campos' => $campos,
            'whereArmado' => $whereArmado,
            'orderBy' => $orderBy,
        ];

        // El bridge a veces responde "[]" con filas reales (UNLOAD concurrente).
        // Reintentar vacíos; vacío legítimo (ej. subdiario en mes cerrado) se acepta al último intento.
        $intentos = max(3, (int) config('gastronomia.conciliacion_diaria_reporte.anita_reintentos_bridge', 3));

        for ($i = 1; $i <= $intentos; $i++) {
            $t0 = microtime(true);
            $raw = $this->api->apiCall($payload);
            $msg = ApiAnita::extraerMensajeError($raw);

            if ($msg !== null) {
                if ($i === $intentos) {
                    $errores[] = $etiqueta.': '.$msg;
                    Log::warning('anita_asiento_import.bridge', [
                        'etiqueta' => $etiqueta,
                        'error' => $msg,
                        'intento' => $i,
                    ]);

                    return [];
                }
                usleep(250000 * $i);
                continue;
            }

            $filas = ApiAnita::decodificarListaFilas($raw);
            if ($filas !== [] || $i === $intentos) {
                Log::info('anita_asiento_import.bridge', [
                    'etiqueta' => $etiqueta,
                    'filas' => count($filas),
                    'ms' => round((microtime(true) - $t0) * 1000, 1),
                    'intento' => $i,
                ]);

                return $filas;
            }

            usleep(250000 * $i);
        }

        return [];
    }

    private function remapearSubhistComoSubdiario(object $fila): object
    {
        $out = [];
        foreach ((array) $fila as $clave => $valor) {
            if (str_starts_with($clave, 'subh_')) {
                $out['subd_'.substr($clave, 5)] = $valor;
            } else {
                $out[$clave] = $valor;
            }
        }

        $out['subd_ref_tipo'] = $out['subd_ref_tipo'] ?? '';
        $out['subd_ref_letra'] = $out['subd_ref_letra'] ?? ' ';
        $out['subd_ref_sucursal'] = $out['subd_ref_sucursal'] ?? 0;
        $out['subd_ref_nro'] = $out['subd_ref_nro'] ?? 0;
        $out['subd_ref_sistema'] = $out['subd_ref_sistema'] ?? '';
        $out['subd_procesado'] = $out['subd_procesado'] ?? 'S';
        $out['subd_origen_subhist'] = true;

        return (object) $out;
    }
}
