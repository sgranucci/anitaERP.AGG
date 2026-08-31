<?php

namespace App\Support\Sueldos;

use App\ApiAnita;
use RuntimeException;

/**
 * Lee el asiento de sueldos de Anita (asimae / asicon / asicta).
 * asimae = cabecera; asicta = cuenta + D/H por línea; asicon = conceptos que alimentan cada línea.
 */
final class SueldosAsientoAnitaReader
{
    public const ASIENTO_SUELDOS = 1;

    public const ASIENTO_PREVISION = 2;

    /**
     * @return array{
     *   cabeceras: array<int, array{nro: int, titulo: string, centro_costos: string, ccosto_contab: int}>,
     *   lineas: array<int, array<int, array{nro: int, linea: int, cuenta: string, dh: string}>>,
     *   conceptos: list<array{nro: int, linea: int, concepto: int, linea_con: int, signo: string}>
     * }
     */
    public function leer(): array
    {
        $api = new ApiAnita();

        $cabeceras = [];
        foreach ($this->listar($api, 'asimae', 'asim_nro_asiento,asim_ccosto_contab,asim_centro_costos,asim_titulo', 'asim_nro_asiento') as $fila) {
            $nro = (int) ($fila->asim_nro_asiento ?? 0);
            if ($nro <= 0) {
                continue;
            }
            $cabeceras[$nro] = [
                'nro' => $nro,
                'titulo' => trim((string) ($fila->asim_titulo ?? '')),
                'centro_costos' => trim((string) ($fila->asim_centro_costos ?? '')),
                'ccosto_contab' => (int) ($fila->asim_ccosto_contab ?? 0),
            ];
        }

        $lineas = [];
        foreach ($this->listar($api, 'asicta', 'asic_nro_asiento,asic_linea,asic_cuenta,asic_d_h', 'asic_nro_asiento,asic_linea') as $fila) {
            $nro = (int) ($fila->asic_nro_asiento ?? 0);
            $linea = (int) ($fila->asic_linea ?? -1);
            $cuenta = trim((string) ($fila->asic_cuenta ?? ''));
            $dh = strtoupper(trim((string) ($fila->asic_d_h ?? '')));
            if ($nro <= 0 || $cuenta === '' || ! in_array($dh, ['D', 'H'], true)) {
                continue;
            }
            $lineas[$nro][$linea] = [
                'nro' => $nro,
                'linea' => $linea,
                'cuenta' => $cuenta,
                'dh' => $dh,
            ];
        }

        $conceptos = [];
        foreach ($this->listar($api, 'asicon', 'asico_nro_asiento,asico_linea,asico_concepto,asico_linea_con,asico_signo', 'asico_nro_asiento,asico_linea,asico_concepto,asico_linea_con') as $fila) {
            $nro = (int) ($fila->asico_nro_asiento ?? 0);
            $concepto = (int) ($fila->asico_concepto ?? 0);
            if ($nro <= 0 || $concepto <= 0) {
                continue;
            }
            $conceptos[] = [
                'nro' => $nro,
                'linea' => (int) ($fila->asico_linea ?? 0),
                'concepto' => $concepto,
                'linea_con' => (int) ($fila->asico_linea_con ?? 0),
                'signo' => trim((string) ($fila->asico_signo ?? '+')) === '-' ? '-' : '+',
            ];
        }

        return [
            'cabeceras' => $cabeceras,
            'lineas' => $lineas,
            'conceptos' => $conceptos,
        ];
    }

    /**
     * @return list<object>
     */
    private function listar(ApiAnita $api, string $tabla, string $campos, string $orderBy): array
    {
        $parsed = ApiAnita::parsearRespuestaLista($api->apiCall([
            'acc' => 'list',
            'sistema' => 'sueldos',
            'tabla' => $tabla,
            'campos' => $campos,
            'orderBy' => $orderBy,
        ]));

        if (! empty($parsed['error_lectura'])) {
            throw new RuntimeException('Anita '.$tabla.': '.(string) $parsed['error_lectura']);
        }

        return $parsed['filas'];
    }
}
