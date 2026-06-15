<?php

namespace App\Support\Contable\MayorConcepto;

/**
 * Cruza el mayor por concepto imputado contra el mayor plano de cuentas de disponibilidad.
 *
 * Plano Debe/Haber: movimientos reales de la cuenta caja/banco en subdiario + ctamov.
 * Imput. Debe/Haber: mismo criterio, pero sumando las líneas del mayor por concepto que
 * pasan por esa cuenta (disp_debe/disp_haber). En un pago OPP el gasto va en Debe en
 * pantalla, pero el banco sale en Haber — por eso no se usa el Debe/Haber visible.
 */
class MayorConceptoAuditoriaSupport
{
    /**
     * Efecto sobre la cuenta de disponibilidad a partir de una línea del reporte.
     *
     * @return array{0: float, 1: float} [disp_debe, disp_haber]
     */
    public function dispDebeHaberDesdeLinea(array $linea): array
    {
        $cuentaMostrada = (int) ($linea['cuenta'] ?? 0);
        $cuentaDisp = (int) ($linea['cuenta_disponibilidad'] ?? 0);
        $debe = (float) ($linea['debe'] ?? 0);
        $haber = (float) ($linea['haber'] ?? 0);

        if ($cuentaDisp <= 0) {
            return [0.0, 0.0];
        }

        if (isset($linea['disp_debe']) || isset($linea['disp_haber'])) {
            return [(float) ($linea['disp_debe'] ?? 0), (float) ($linea['disp_haber'] ?? 0)];
        }

        if ($cuentaMostrada === $cuentaDisp) {
            return [$debe, $haber];
        }

        return [$haber, $debe];
    }
    /**
     * @param  array<string, mixed>  $resultado
     * @return array{
     *   cuadra: bool,
     *   diferencia_debe: float,
     *   diferencia_haber: float,
     *   filas: list<array<string, mixed>>
     * }
     */
    public function auditar(array $resultado): array
    {
        $cuentas = [];
        $planoLista = $resultado['mayor_plano_disponibilidad'] ?? [];
        if (is_array($planoLista)) {
            foreach ($planoLista as $cuenta => $fila) {
                if (is_array($fila)) {
                    $c = (int) ($fila['cuenta'] ?? $cuenta);
                    if ($c > 0) {
                        $cuentas[$c] = $fila;
                    }
                }
            }
        }
        $imputado = $this->totalesImputadosPorDisponibilidad($resultado);
        $totalesConceptoPorDisp = $this->totalesConceptoPorDisponibilidad($resultado);

        $todasCuentas = array_unique(array_merge(array_keys($cuentas), array_keys($imputado)));

        $filas = [];
        $diffDebe = 0.0;
        $diffHaber = 0.0;

        foreach ($todasCuentas as $cuenta) {
            $p = $cuentas[$cuenta] ?? [
                'cuenta' => $cuenta,
                'cuenta_codigo' => '',
                'cuenta_nombre' => '',
                'debe' => 0.0,
                'haber' => 0.0,
            ];

            $imp = $imputado[$cuenta] ?? ['debe' => 0.0, 'haber' => 0.0, 'lineas' => 0];

            $planoDebe = (float) ($p['debe'] ?? 0);
            $planoHaber = (float) ($p['haber'] ?? 0);
            $impDebe = (float) ($imp['debe'] ?? 0);
            $impHaber = (float) ($imp['haber'] ?? 0);

            $dDebe = round($planoDebe - $impDebe, 2);
            $dHaber = round($planoHaber - $impHaber, 2);

            $conceptos = $totalesConceptoPorDisp[$cuenta] ?? [];

            $filas[] = [
                'cuenta' => $cuenta,
                'cuenta_codigo' => $p['cuenta_codigo'] ?? '',
                'cuenta_nombre' => $p['cuenta_nombre'] ?? '',
                'plano_debe' => round($planoDebe, 2),
                'plano_haber' => round($planoHaber, 2),
                'imputado_debe' => round($impDebe, 2),
                'imputado_haber' => round($impHaber, 2),
                'diferencia_debe' => $dDebe,
                'diferencia_haber' => $dHaber,
                'lineas_imputadas' => (int) ($imp['lineas'] ?? 0),
                'conceptos_imputados' => count($conceptos),
                'cuadra' => abs($dDebe) < 0.05 && abs($dHaber) < 0.05,
            ];

            $diffDebe += $dDebe;
            $diffHaber += $dHaber;
        }

        usort($filas, fn ($a, $b) => $a['cuenta'] <=> $b['cuenta']);

        return [
            'cuadra' => abs($diffDebe) < 0.05 && abs($diffHaber) < 0.05
                && array_reduce($filas, fn ($ok, $f) => $ok && ($f['cuadra'] ?? false), true),
            'diferencia_debe' => round($diffDebe, 2),
            'diferencia_haber' => round($diffHaber, 2),
            'filas' => $filas,
            'nota' => 'Plano = subdiario+ctamov de la cuenta caja/banco. Imput. = mayor por concepto totalizado por esa cuenta (todos los conceptos), usando el movimiento real del banco.',
        ];
    }

    /**
     * Cruza imputación ERP (total por cuenta de disponibilidad) vs export Anita l_mayor.
     *
     * @param  array<string, mixed>  $resultado
     * @param  array<int, array<string, mixed>>  $totalesAnitaPorCuenta
     * @return array{
     *   cuadra: bool,
     *   diferencia_debe: float,
     *   diferencia_haber: float,
     *   cuentas_descuadradas: int,
     *   filas: list<array<string, mixed>>,
     *   nota: string
     * }
     */
    public function auditarContraMayorPlanoAnita(array $resultado, array $totalesAnitaPorCuenta): array
    {
        $imputado = $this->totalesImputadosPorDisponibilidad($resultado);
        $planoBridge = $resultado['mayor_plano_disponibilidad'] ?? [];

        $todasCuentas = array_unique(array_merge(
            array_keys($imputado),
            array_keys($totalesAnitaPorCuenta),
        ));
        sort($todasCuentas);

        $filas = [];
        $diffDebe = 0.0;
        $diffHaber = 0.0;
        $descuadradas = 0;

        foreach ($todasCuentas as $cuenta) {
            if ($cuenta <= 0 || $cuenta > MayorConceptoMemoriaMotor::LIMITE_DISPONIBILIDAD) {
                continue;
            }

            $imp = $imputado[$cuenta] ?? ['debe' => 0.0, 'haber' => 0.0, 'lineas' => 0];
            $anita = $totalesAnitaPorCuenta[$cuenta] ?? ['debe' => 0.0, 'haber' => 0.0];
            $bridge = is_array($planoBridge[$cuenta] ?? null) ? $planoBridge[$cuenta] : ['debe' => 0.0, 'haber' => 0.0];

            $impDebe = (float) ($imp['debe'] ?? 0);
            $impHaber = (float) ($imp['haber'] ?? 0);
            $anitaDebe = (float) ($anita['debe'] ?? 0);
            $anitaHaber = (float) ($anita['haber'] ?? 0);
            $bridgeDebe = (float) ($bridge['debe'] ?? 0);
            $bridgeHaber = (float) ($bridge['haber'] ?? 0);

            if ((int) ($imp['lineas'] ?? 0) <= 0
                && abs($anitaDebe) < 0.05 && abs($anitaHaber) < 0.05
                && abs($bridgeDebe) < 0.05 && abs($bridgeHaber) < 0.05) {
                continue;
            }

            $dDebe = round($anitaDebe - $impDebe, 2);
            $dHaber = round($anitaHaber - $impHaber, 2);
            $dBridgeDebe = round($anitaDebe - $bridgeDebe, 2);
            $dBridgeHaber = round($anitaHaber - $bridgeHaber, 2);
            $cuadra = abs($dDebe) < 0.05 && abs($dHaber) < 0.05;

            if (! $cuadra) {
                $descuadradas++;
            }

            $filas[] = [
                'cuenta' => $cuenta,
                'cuenta_codigo' => (string) ($anita['cuenta_codigo'] ?? $bridge['cuenta_codigo'] ?? ''),
                'cuenta_nombre' => (string) ($anita['cuenta_nombre'] ?? $bridge['cuenta_nombre'] ?? ''),
                'anita_debe' => round($anitaDebe, 2),
                'anita_haber' => round($anitaHaber, 2),
                'imputado_debe' => round($impDebe, 2),
                'imputado_haber' => round($impHaber, 2),
                'plano_bridge_debe' => round($bridgeDebe, 2),
                'plano_bridge_haber' => round($bridgeHaber, 2),
                'diferencia_debe' => $dDebe,
                'diferencia_haber' => $dHaber,
                'diff_anita_bridge_debe' => $dBridgeDebe,
                'diff_anita_bridge_haber' => $dBridgeHaber,
                'lineas_imputadas' => (int) ($imp['lineas'] ?? 0),
                'cuadra' => $cuadra,
            ];

            $diffDebe += $dDebe;
            $diffHaber += $dHaber;
        }

        return [
            'cuadra' => $descuadradas === 0 && abs($diffDebe) < 0.05 && abs($diffHaber) < 0.05,
            'diferencia_debe' => round($diffDebe, 2),
            'diferencia_haber' => round($diffHaber, 2),
            'cuentas_descuadradas' => $descuadradas,
            'filas' => $filas,
            'nota' => 'Objetivo final: imputación ERP totalizada por cuenta de disponibilidad = export Anita l_mayor (Debe/Haber del mes). Incluye columna bridge para detectar si la diferencia viene del motor o del puente.',
        ];
    }

    /**
     * @param  array<string, mixed>  $resultado
     * @return array<int, array{debe: float, haber: float, lineas: int}>
     */
    private function totalesImputadosPorDisponibilidad(array $resultado): array
    {
        $porCuenta = [];

        foreach ($resultado['secciones'] ?? [] as $seccion) {
            foreach ($seccion['cuentas'] ?? [] as $cuentaBlock) {
                foreach ($cuentaBlock['lineas'] ?? [] as $ln) {
                    $disp = (int) ($ln['cuenta_disponibilidad'] ?? 0);
                    if ($disp <= 0) {
                        continue;
                    }

                    if (! isset($porCuenta[$disp])) {
                        $porCuenta[$disp] = ['debe' => 0.0, 'haber' => 0.0, 'lineas' => 0];
                    }

                    [$dispDebe, $dispHaber] = $this->dispDebeHaberDesdeLinea($ln);
                    $porCuenta[$disp]['debe'] += $dispDebe;
                    $porCuenta[$disp]['haber'] += $dispHaber;
                    $porCuenta[$disp]['lineas']++;
                }
            }
        }

        foreach ($porCuenta as $cuenta => $row) {
            $porCuenta[$cuenta]['debe'] = round($row['debe'], 2);
            $porCuenta[$cuenta]['haber'] = round($row['haber'], 2);
        }

        return $porCuenta;
    }

    /**
     * @param  array<string, mixed>  $resultado
     * @return array<int, array<int, array{concepto_id: int, concepto_nombre: string, debe: float, haber: float, lineas: int}>>
     */
    private function totalesConceptoPorDisponibilidad(array $resultado): array
    {
        $porCuenta = [];

        foreach ($resultado['secciones'] ?? [] as $seccion) {
            $conceptoId = (int) ($seccion['concepto_id'] ?? 0);
            $conceptoNombre = (string) ($seccion['concepto_nombre'] ?? '');

            foreach ($seccion['cuentas'] ?? [] as $cuentaBlock) {
                foreach ($cuentaBlock['lineas'] ?? [] as $ln) {
                    $disp = (int) ($ln['cuenta_disponibilidad'] ?? 0);
                    if ($disp <= 0) {
                        continue;
                    }

                    if (! isset($porCuenta[$disp][$conceptoId])) {
                        $porCuenta[$disp][$conceptoId] = [
                            'concepto_id' => $conceptoId,
                            'concepto_nombre' => $conceptoNombre,
                            'debe' => 0.0,
                            'haber' => 0.0,
                            'lineas' => 0,
                        ];
                    }

                    [$dispDebe, $dispHaber] = $this->dispDebeHaberDesdeLinea($ln);
                    $porCuenta[$disp][$conceptoId]['debe'] += $dispDebe;
                    $porCuenta[$disp][$conceptoId]['haber'] += $dispHaber;
                    $porCuenta[$disp][$conceptoId]['lineas']++;
                }
            }
        }

        return $porCuenta;
    }

    /**
     * Cruza totales visibles del mayor por concepto (contrapartidas) contra el plano acotado
     * a movimientos originados en operaciones que tocan disponibilidad.
     *
     * Caja/banco (≤114000000) se audita aparte por cuenta_disponibilidad (auditar()).
     *
     * @param  array<string, mixed>  $resultado
     * @return array{
     *   cuadra: bool,
     *   diferencia_debe: float,
     *   diferencia_haber: float,
     *   filas: list<array<string, mixed>>,
     *   cuentas_descuadradas: int
     * }
     */
    public function auditarMayorPlanoAnalitico(array $resultado): array
    {
        $planoLista = $resultado['mayor_plano_contrapartidas_disponibilidad'] ?? [];
        $imputado = $this->totalesVisiblesContrapartidasDesdeDisp($resultado);

        $todasCuentas = array_unique(array_merge(
            array_keys($imputado),
            array_map(
                fn ($f) => (int) ($f['cuenta'] ?? 0),
                is_array($planoLista) ? array_values($planoLista) : [],
            ),
        ));
        sort($todasCuentas);

        $filas = [];
        $diffDebe = 0.0;
        $diffHaber = 0.0;
        $descuadradas = 0;

        foreach ($todasCuentas as $cuenta) {
            if ($cuenta <= 0 || $cuenta <= MayorConceptoMemoriaMotor::LIMITE_DISPONIBILIDAD) {
                continue;
            }

            $imp = $imputado[$cuenta] ?? ['debe' => 0.0, 'haber' => 0.0, 'lineas' => 0];
            $p = is_array($planoLista[$cuenta] ?? null) ? $planoLista[$cuenta] : [
                'cuenta' => $cuenta,
                'cuenta_codigo' => '',
                'cuenta_nombre' => '',
                'debe' => 0.0,
                'haber' => 0.0,
            ];
            $planoDebe = (float) ($p['debe'] ?? 0);
            $planoHaber = (float) ($p['haber'] ?? 0);
            $impDebe = (float) ($imp['debe'] ?? 0);
            $impHaber = (float) ($imp['haber'] ?? 0);

            if ((int) ($imp['lineas'] ?? 0) <= 0 && abs($planoDebe) < 0.05 && abs($planoHaber) < 0.05) {
                continue;
            }

            $dDebe = round($planoDebe - $impDebe, 2);
            $dHaber = round($planoHaber - $impHaber, 2);
            $cuadra = abs($dDebe) < 0.05 && abs($dHaber) < 0.05;

            if (! $cuadra) {
                $descuadradas++;
            }

            $filas[] = [
                'cuenta' => $cuenta,
                'cuenta_codigo' => $p['cuenta_codigo'] ?? '',
                'cuenta_nombre' => $p['cuenta_nombre'] ?? '',
                'plano_debe' => round($planoDebe, 2),
                'plano_haber' => round($planoHaber, 2),
                'imputado_debe' => round($impDebe, 2),
                'imputado_haber' => round($impHaber, 2),
                'diferencia_debe' => $dDebe,
                'diferencia_haber' => $dHaber,
                'lineas_imputadas' => (int) ($imp['lineas'] ?? 0),
                'movimientos_plano' => (int) ($p['movimientos'] ?? 0),
                'cuadra' => $cuadra,
            ];

            $diffDebe += $dDebe;
            $diffHaber += $dHaber;
        }

        return [
            'cuadra' => $descuadradas === 0 && abs($diffDebe) < 0.05 && abs($diffHaber) < 0.05,
            'diferencia_debe' => round($diffDebe, 2),
            'diferencia_haber' => round($diffHaber, 2),
            'cuentas_descuadradas' => $descuadradas,
            'filas' => $filas,
            'nota' => 'Contrapartidas (>114000000) generadas desde operaciones que tocan disponibilidad. Plano = acumulado al procesar cada línea (excluye remanente y cuentas caja/banco). Imputado = totales visibles del reporte en el mismo alcance.',
        ];
    }

    /**
     * Totales Debe/Haber visibles solo en contrapartidas originadas por operaciones de disponibilidad.
     *
     * @param  array<string, mixed>  $resultado
     * @return array<int, array{debe: float, haber: float, lineas: int}>
     */
    private function totalesVisiblesContrapartidasDesdeDisp(array $resultado): array
    {
        $porCuenta = [];

        foreach ($resultado['secciones'] ?? [] as $seccion) {
            foreach ($seccion['cuentas'] ?? [] as $cuentaBlock) {
                foreach ($cuentaBlock['lineas'] ?? [] as $ln) {
                    $cuenta = (int) ($ln['cuenta'] ?? 0);
                    if ($cuenta <= MayorConceptoMemoriaMotor::LIMITE_DISPONIBILIDAD) {
                        continue;
                    }

                    if (($ln['origen'] ?? '') === 'Remanente mayor plano') {
                        continue;
                    }

                    if (array_key_exists('desde_operacion_disponibilidad', $ln)
                        && ! ($ln['desde_operacion_disponibilidad'] ?? false)) {
                        continue;
                    }

                    if (! isset($porCuenta[$cuenta])) {
                        $porCuenta[$cuenta] = ['debe' => 0.0, 'haber' => 0.0, 'lineas' => 0];
                    }

                    $porCuenta[$cuenta]['debe'] += (float) ($ln['debe'] ?? 0);
                    $porCuenta[$cuenta]['haber'] += (float) ($ln['haber'] ?? 0);
                    $porCuenta[$cuenta]['lineas']++;
                }
            }
        }

        foreach ($porCuenta as $cuenta => $row) {
            $porCuenta[$cuenta]['debe'] = round($row['debe'], 2);
            $porCuenta[$cuenta]['haber'] = round($row['haber'], 2);
        }

        return $porCuenta;
    }

    /**
     * @param  array<string, mixed>  $resultado
     * @return array<int, array{debe: float, haber: float, lineas: int}>
     */
    private function totalesVisiblesPorCuenta(array $resultado): array
    {
        $porCuenta = [];

        foreach ($resultado['secciones'] ?? [] as $seccion) {
            foreach ($seccion['cuentas'] ?? [] as $cuentaBlock) {
                $cuenta = (int) ($cuentaBlock['cuenta'] ?? 0);
                if ($cuenta <= 0) {
                    continue;
                }

                if (! isset($porCuenta[$cuenta])) {
                    $porCuenta[$cuenta] = ['debe' => 0.0, 'haber' => 0.0, 'lineas' => 0];
                }

                $porCuenta[$cuenta]['debe'] += (float) ($cuentaBlock['total_debe'] ?? 0);
                $porCuenta[$cuenta]['haber'] += (float) ($cuentaBlock['total_haber'] ?? 0);
                $porCuenta[$cuenta]['lineas'] += count($cuentaBlock['lineas'] ?? []);
            }
        }

        foreach ($porCuenta as $cuenta => $row) {
            $porCuenta[$cuenta]['debe'] = round($row['debe'], 2);
            $porCuenta[$cuenta]['haber'] = round($row['haber'], 2);
        }

        return $porCuenta;
    }
}
