<?php

declare(strict_types=1);

namespace App\Services\Contable\Sicore;

use App\ApiAnita;
use App\Models\Contable\Sicore_Config;
use App\Support\Contable\Sicore\SicoreEmpresaAnitaSupport;
use App\Support\Contable\Sicore\SicoreFormatoV8Support;

final class SicoreSueldosDatosService
{
    /**
     * @return list<array<string, mixed>>
     */
    public function generar(int $empresaId, string $fechaDesde, string $fechaHasta, Sicore_Config $config): array
    {
        $empresaAnita = SicoreEmpresaAnitaSupport::codigoEmpresaAnita($empresaId);
        $desdeAnita = (int) str_replace('-', '', $fechaDesde);
        $hastaAnita = (int) str_replace('-', '', $fechaHasta);

        $conceptoRet = (int) ($config->concepto_retencion_sueldos ?? 0);
        $conceptoDev = (int) ($config->concepto_devolucion_sueldos ?? 0);
        $regimen = (int) ($config->codigo_regimen ?? 160);

        $empleados = $this->listarEmpleados($empresaAnita);
        $filas = [];

        foreach ($empleados as $emp) {
            $emp = (array) $emp;
            $legajo = (int) ($emp['emp_legajo'] ?? 0);
            if ($legajo <= 0) {
                continue;
            }

            $totRet = 0.0;
            $totDev = 0.0;
            $totBase = 0.0;

            $this->acumularAuxrec('auxrec', $empresaAnita, $legajo, $desdeAnita, $hastaAnita, $conceptoRet, $conceptoDev, $totRet, $totDev, $totBase);
            $this->acumularAuxrec('auxhist', $empresaAnita, $legajo, $desdeAnita, $hastaAnita, $conceptoRet, $conceptoDev, $totRet, $totDev, $totBase);

            $cuit = SicoreFormatoV8Support::normalizarCuit((string) ($emp['emp_afil_jubil'] ?? ''));
            $nombre = substr(trim((string) ($emp['emp_nombre'] ?? '')), 0, 30);
            $fechaIso = $fechaHasta;

            if (abs($totRet) >= 0.001) {
                $filas[] = $this->filaSueldo($config, $regimen, $cuit, $nombre, $fechaIso, $totRet, 7, $legajo);
            }
            if ($conceptoDev > 0 && abs($totDev) >= 0.001) {
                $filas[] = $this->filaSueldo($config, $regimen, $cuit, $nombre, $fechaIso, $totDev, 8, $legajo);
            }
        }

        return $filas;
    }

    /**
     * @return list<object|array<string, mixed>>
     */
    private function listarEmpleados(int $empresaAnita): array
    {
        $api = new ApiAnita();
        $payload = [
            'acc' => 'list',
            'sistema' => 'sueldos',
            'tabla' => 'empleado',
            'campos' => 'emp_empresa, emp_legajo, emp_nombre, emp_afil_jubil',
            'whereArmado' => ' WHERE emp_empresa = '.$empresaAnita,
            'orderBy' => 'emp_legajo',
        ];

        return ApiAnita::decodificarListaFilas($api->apiCall($payload));
    }

    private function acumularAuxrec(
        string $tabla,
        int $empresaAnita,
        int $legajo,
        int $desdeAnita,
        int $hastaAnita,
        int $conceptoRet,
        int $conceptoDev,
        float &$totRet,
        float &$totDev,
        float &$totBase,
    ): void {
        if ($conceptoRet <= 0 && $conceptoDev <= 0) {
            return;
        }

        $api = new ApiAnita();
        $payload = [
            'acc' => 'list',
            'sistema' => 'sueldos',
            'tabla' => $tabla,
            'campos' => 'aux_empresa, aux_legajo, aux_codigo, aux_haberes, aux_deduc, aux_total, aux_fecha',
            'whereArmado' => ' WHERE aux_empresa = '.$empresaAnita
                .' AND aux_legajo = '.$legajo
                .' AND aux_fecha >= '.$desdeAnita
                .' AND aux_fecha <= '.$hastaAnita,
            'orderBy' => 'aux_fecha',
        ];

        foreach (ApiAnita::decodificarListaFilas($api->apiCall($payload)) as $row) {
            $row = (array) $row;
            $codigo = (int) ($row['aux_codigo'] ?? 0);
            $monto = (float) ($row['aux_haberes'] ?? 0) + (float) ($row['aux_deduc'] ?? 0);

            if ($codigo === $conceptoRet) {
                $totRet += $monto;
                $totBase += (float) ($row['aux_total'] ?? 0);
            } elseif ($conceptoDev > 0 && $codigo === $conceptoDev) {
                $totDev += $monto;
                $totBase += (float) ($row['aux_total'] ?? 0);
            }
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function filaSueldo(
        Sicore_Config $config,
        int $regimen,
        string $cuit,
        string $nombre,
        string $fechaIso,
        float $importe,
        int $codComp,
        int $legajo,
    ): array {
        $importe = round($importe, 2);
        $base = $codComp === 8 ? -abs($importe) : abs($importe);

        return [
            'origen' => 'sueldos',
            'sicore_config_id' => (int) $config->id,
            'cod_regimen' => $regimen,
            'cod_impuesto' => (int) $config->codigo_impuesto,
            'cod_operacion' => (int) ($config->codigo_operacion ?? 1),
            'cod_comp' => $codComp,
            'fecha_comp' => $fechaIso,
            'nro_comp' => 0,
            'importe_comp' => abs($importe),
            'base_calculo' => $base,
            'fecha_retencion' => $fechaIso,
            'cod_condicion' => 1,
            'importe' => $importe,
            'porc_excl' => 0.0,
            'fecha_boletin' => '',
            'cod_documento' => 80,
            'nro_documento' => $cuit,
            'nro_cert' => 0,
            'razon_social' => $nombre,
            'referencia' => 'Legajo '.$legajo,
        ];
    }
}
