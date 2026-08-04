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
     * Prefijo de columnas Informix según tabla.
     * auxrec → aux_*; auxhist → auxh_* (nómina normal: liquidación actual + histórico)
     * auxconf → axco_*; auxconfh → auxcoh_* (nómina confidencial: actual + histórico)
     *
     * @var array<string, string>
     */
    private const PREFIJO_COLUMNAS = [
        'auxrec' => 'aux_',
        'auxhist' => 'auxh_',
        'auxconf' => 'axco_',
        'auxconfh' => 'auxcoh_',
    ];

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

        if ($conceptoRet <= 0 && $conceptoDev <= 0) {
            return [];
        }

        /** @var array<int, array{ret: float, dev: float, base: float}> $acumulado */
        $acumulado = [];
        $this->acumularTabla('auxrec', $empresaAnita, $desdeAnita, $hastaAnita, $conceptoRet, $conceptoDev, $acumulado);
        $this->acumularTabla('auxhist', $empresaAnita, $desdeAnita, $hastaAnita, $conceptoRet, $conceptoDev, $acumulado);
        // Nómina confidencial: las ganancias (4ta cat.) se liquidan en auxconf (actual) y
        // se archivan en auxconfh (histórico), igual que auxrec/auxhist en la nómina normal.
        $this->acumularTabla('auxconf', $empresaAnita, $desdeAnita, $hastaAnita, $conceptoRet, $conceptoDev, $acumulado);
        $this->acumularTabla('auxconfh', $empresaAnita, $desdeAnita, $hastaAnita, $conceptoRet, $conceptoDev, $acumulado);

        if ($acumulado === []) {
            return [];
        }

        $empleados = $this->mapearEmpleados($empresaAnita, array_keys($acumulado));
        $filas = [];
        $fechaIso = $fechaHasta;

        foreach ($acumulado as $legajo => $totales) {
            $emp = $empleados[$legajo] ?? null;
            $cuit = SicoreFormatoV8Support::normalizarCuit((string) ($emp['emp_afil_jubil'] ?? ''));
            $nombre = substr(trim((string) ($emp['emp_nombre'] ?? ('Legajo '.$legajo))), 0, 30);

            if (abs($totales['ret']) >= 0.001) {
                $filas[] = $this->filaSueldo(
                    $config,
                    $regimen,
                    $cuit,
                    $nombre,
                    $fechaIso,
                    $totales['ret'],
                    SicoreFormatoV8Support::COD_COMP_LIQUIDACION,
                    $legajo,
                );
            }
            // Incluye concepto_devolucion y créditos del concepto retención (deducción negativa).
            if (abs($totales['dev']) >= 0.001) {
                $filas[] = $this->filaSueldo(
                    $config,
                    $regimen,
                    $cuit,
                    $nombre,
                    $fechaIso,
                    abs($totales['dev']),
                    SicoreFormatoV8Support::COD_COMP_DEVOLUCION,
                    $legajo,
                );
            }
        }

        return $filas;
    }

    /**
     * @param  list<int>  $legajos
     * @return array<int, array<string, mixed>>
     */
    private function mapearEmpleados(int $empresaAnita, array $legajos): array
    {
        $legajos = array_values(array_unique(array_filter(array_map('intval', $legajos), static fn (int $l) => $l > 0)));
        if ($legajos === []) {
            return [];
        }

        $api = new ApiAnita();
        $mapa = [];

        // Informix / bridge: IN por lotes para no saturar whereArmado.
        foreach (array_chunk($legajos, 200) as $lote) {
            $payload = [
                'acc' => 'list',
                'sistema' => 'sueldos',
                'tabla' => 'empleado',
                'campos' => 'emp_empresa, emp_legajo, emp_nombre, emp_afil_jubil',
                'whereArmado' => ' WHERE emp_empresa = '.$empresaAnita
                    .' AND emp_legajo IN ('.implode(',', $lote).')',
                'orderBy' => 'emp_legajo',
            ];

            foreach (ApiAnita::decodificarListaFilas($api->apiCall($payload)) as $row) {
                $row = (array) $row;
                $legajo = (int) ($row['emp_legajo'] ?? 0);
                if ($legajo > 0) {
                    $mapa[$legajo] = $row;
                }
            }
        }

        return $mapa;
    }

    /**
     * @param  array<int, array{ret: float, dev: float, base: float}>  $acumulado
     */
    private function acumularTabla(
        string $tabla,
        int $empresaAnita,
        int $desdeAnita,
        int $hastaAnita,
        int $conceptoRet,
        int $conceptoDev,
        array &$acumulado,
    ): void {
        $prefijo = self::PREFIJO_COLUMNAS[$tabla] ?? null;
        if ($prefijo === null) {
            return;
        }

        $colEmpresa = $prefijo.'empresa';
        $colLegajo = $prefijo.'legajo';
        $colCodigo = $prefijo.'codigo';
        $colHaberes = $prefijo.'haberes';
        $colDeduc = $prefijo.'deduc';
        $colTotal = $prefijo.'total';
        $colFecha = $prefijo.'fecha';

        $codigos = array_values(array_filter([$conceptoRet, $conceptoDev], static fn (int $c) => $c > 0));
        if ($codigos === []) {
            return;
        }

        $api = new ApiAnita();
        $payload = [
            'acc' => 'list',
            'sistema' => 'sueldos',
            'tabla' => $tabla,
            'campos' => implode(', ', [$colEmpresa, $colLegajo, $colCodigo, $colHaberes, $colDeduc, $colTotal, $colFecha]),
            'whereArmado' => ' WHERE '.$colEmpresa.' = '.$empresaAnita
                .' AND '.$colFecha.' >= '.$desdeAnita
                .' AND '.$colFecha.' <= '.$hastaAnita
                .' AND '.$colCodigo.' IN ('.implode(',', $codigos).')',
            'orderBy' => $colFecha,
        ];

        foreach (ApiAnita::decodificarListaFilas($api->apiCall($payload)) as $row) {
            $row = (array) $row;
            $legajo = (int) ($row[$colLegajo] ?? 0);
            $codigo = (int) ($row[$colCodigo] ?? 0);
            if ($legajo <= 0 || $codigo <= 0) {
                continue;
            }

            $monto = (float) ($row[$colHaberes] ?? 0) + (float) ($row[$colDeduc] ?? 0);
            if (! isset($acumulado[$legajo])) {
                $acumulado[$legajo] = ['ret' => 0.0, 'dev' => 0.0, 'base' => 0.0];
            }

            if ($codigo === $conceptoRet) {
                // Créditos/devoluciones suelen liquidarse en el mismo concepto de
                // retención (ej. 1650) con deducción negativa, no en concepto_devolucion.
                // SICORE 787 (4ta cat.): retención → cod_comp 7; crédito → cod_comp 8
                // (no confundir con NC proveedores = 3).
                if ($monto < -0.001) {
                    $acumulado[$legajo]['dev'] += abs($monto);
                } elseif ($monto > 0.001) {
                    $acumulado[$legajo]['ret'] += $monto;
                }
                $acumulado[$legajo]['base'] += (float) ($row[$colTotal] ?? 0);
            } elseif ($conceptoDev > 0 && $codigo === $conceptoDev) {
                $acumulado[$legajo]['dev'] += $monto;
                $acumulado[$legajo]['base'] += (float) ($row[$colTotal] ?? 0);
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
        $base = $codComp === SicoreFormatoV8Support::COD_COMP_DEVOLUCION
            ? -abs($importe)
            : abs($importe);

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
