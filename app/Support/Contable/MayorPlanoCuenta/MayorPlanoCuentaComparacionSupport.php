<?php

namespace App\Support\Contable\MayorPlanoCuenta;

/**
 * Compara mayor plano AnitaERP vs export CSV Anita l-mayor.
 */
class MayorPlanoCuentaComparacionSupport
{
    public function __construct(
        private readonly MayorPlanoCuentaAnitaCsvReader $csvReader = new MayorPlanoCuentaAnitaCsvReader(),
    ) {
    }

    /**
     * @param  list<array<string, mixed>>  $lineasErp
     * @param  array<string, mixed>  $resultadoErp
     * @param  array<string, mixed>  $csvAnita
     * @return array<string, mixed>
     */
    public function comparar(array $lineasErp, array $resultadoErp, array $csvAnita, float $tolerancia = 0.05): array
    {
        $lineasAnita = $csvAnita['lineas'] ?? [];
        $erpDetalle = array_values(array_filter($lineasErp, fn ($f) => ($f['tipo_fila'] ?? 'detalle') === 'detalle'));

        $diffLineas = $this->compararLineas($erpDetalle, $lineasAnita, $tolerancia);
        $diffTotalesCuenta = $this->compararTotalesCuenta($resultadoErp, $csvAnita, $tolerancia);
        $diffSaldos = $this->compararSaldosIniciales($resultadoErp, $csvAnita['saldos_iniciales'] ?? [], $tolerancia);

        $totalesErp = $resultadoErp['totales'] ?? [];
        $totAnitaDebe = round(array_sum(array_column($lineasAnita, 'debe')), 2);
        $totAnitaHaber = round(array_sum(array_column($lineasAnita, 'haber')), 2);

        return [
            'parametros' => $resultadoErp['parametros'] ?? [],
            'metadata_anita' => $csvAnita['metadata'] ?? [],
            'resumen' => [
                'lineas_erp' => count($erpDetalle),
                'lineas_anita' => count($lineasAnita),
                'coincidencias_lineas' => $diffLineas['coincidencias'],
                'solo_erp' => count($diffLineas['solo_erp']),
                'solo_anita' => count($diffLineas['solo_anita']),
                'diferencias_importe' => count($diffLineas['diferencias_importe']),
                'cuentas_erp' => (int) ($totalesErp['cuentas'] ?? 0),
                'cuentas_anita_saldo_ini' => count($csvAnita['saldos_iniciales'] ?? []),
                'total_debe_erp' => round((float) ($totalesErp['debe'] ?? 0), 2),
                'total_haber_erp' => round((float) ($totalesErp['haber'] ?? 0), 2),
                'total_debe_anita' => $totAnitaDebe,
                'total_haber_anita' => $totAnitaHaber,
                'delta_debe' => round((float) ($totalesErp['debe'] ?? 0) - $totAnitaDebe, 2),
                'delta_haber' => round((float) ($totalesErp['haber'] ?? 0) - $totAnitaHaber, 2),
                'cuadra_totales' => abs((float) ($totalesErp['debe'] ?? 0) - $totAnitaDebe) <= $tolerancia
                    && abs((float) ($totalesErp['haber'] ?? 0) - $totAnitaHaber) <= $tolerancia,
                'cuadra_lineas' => count($diffLineas['solo_erp']) === 0 && count($diffLineas['solo_anita']) === 0,
                'tolerancia' => $tolerancia,
            ],
            'lineas' => $diffLineas,
            'totales_cuenta' => $diffTotalesCuenta,
            'saldos_iniciales' => $diffSaldos,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function leerCsvAnita(string $ruta): array
    {
        return $this->csvReader->leer($ruta);
    }

    /**
     * @param  list<array<string, mixed>>  $lineasErp
     * @param  list<array<string, mixed>>  $lineasAnita
     * @return array<string, mixed>
     */
    private function compararLineas(array $lineasErp, array $lineasAnita, float $tolerancia): array
    {
        $erpPorClave = $this->agruparPorClave($lineasErp);
        $anitaPorClave = $this->agruparPorClave($lineasAnita);

        $todasClaves = array_unique(array_merge(array_keys($erpPorClave), array_keys($anitaPorClave)));
        sort($todasClaves);

        $coincidencias = 0;
        $soloErp = [];
        $soloAnita = [];
        $diferenciasImporte = [];

        foreach ($todasClaves as $clave) {
            $cntErp = $erpPorClave[$clave]['cantidad'] ?? 0;
            $cntAnita = $anitaPorClave[$clave]['cantidad'] ?? 0;
            $match = min($cntErp, $cntAnita);
            $coincidencias += $match;

            if ($cntErp > $cntAnita) {
                foreach (array_slice($erpPorClave[$clave]['ejemplos'], $cntAnita) as $ln) {
                    $soloErp[] = $this->filaDiff('solo_erp', $clave, $ln, null);
                }
            }

            if ($cntAnita > $cntErp) {
                foreach (array_slice($anitaPorClave[$clave]['ejemplos'], $cntErp) as $ln) {
                    $soloAnita[] = $this->filaDiff('solo_anita', $clave, null, $ln);
                }
            }

            $pares = min($cntErp, $cntAnita);
            for ($i = 0; $i < $pares; $i++) {
                $erp = $erpPorClave[$clave]['ejemplos'][$i];
                $anita = $anitaPorClave[$clave]['ejemplos'][$i];
                $dDebe = round((float) ($erp['debe'] ?? 0) - (float) ($anita['debe'] ?? 0), 2);
                $dHaber = round((float) ($erp['haber'] ?? 0) - (float) ($anita['haber'] ?? 0), 2);
                if (abs($dDebe) > $tolerancia || abs($dHaber) > $tolerancia) {
                    $diferenciasImporte[] = [
                        'clave' => $clave,
                        'debe_erp' => $erp['debe'] ?? 0,
                        'debe_anita' => $anita['debe'] ?? 0,
                        'haber_erp' => $erp['haber'] ?? 0,
                        'haber_anita' => $anita['haber'] ?? 0,
                        'delta_debe' => $dDebe,
                        'delta_haber' => $dHaber,
                        'erp' => $erp,
                        'anita' => $anita,
                    ];
                }
            }
        }

        return [
            'coincidencias' => $coincidencias,
            'solo_erp' => $soloErp,
            'solo_anita' => $soloAnita,
            'diferencias_importe' => $diferenciasImporte,
        ];
    }

    /**
     * @param  array<string, mixed>  $resultadoErp
     * @param  array<string, mixed>  $csvAnita
     * @return list<array<string, mixed>>
     */
    private function compararTotalesCuenta(array $resultadoErp, array $csvAnita, float $tolerancia): array
    {
        $porCuentaErp = [];
        foreach ($resultadoErp['secciones'] ?? [] as $sec) {
            $c = (int) ($sec['cuenta'] ?? 0);
            $porCuentaErp[$c] = [
                'cuenta_codigo' => $sec['cuenta_codigo'] ?? '',
                'debe' => (float) ($sec['total_debe'] ?? 0),
                'haber' => (float) ($sec['total_haber'] ?? 0),
            ];
        }

        $porCuentaAnita = [];
        foreach ($csvAnita['lineas'] ?? [] as $ln) {
            $c = (int) ($ln['cuenta'] ?? 0);
            if (! isset($porCuentaAnita[$c])) {
                $porCuentaAnita[$c] = ['debe' => 0.0, 'haber' => 0.0, 'cuenta_codigo' => $ln['cuenta_codigo'] ?? ''];
            }
            $porCuentaAnita[$c]['debe'] += (float) ($ln['debe'] ?? 0);
            $porCuentaAnita[$c]['haber'] += (float) ($ln['haber'] ?? 0);
        }

        foreach ($porCuentaAnita as $c => $row) {
            $porCuentaAnita[$c]['debe'] = round($row['debe'], 2);
            $porCuentaAnita[$c]['haber'] = round($row['haber'], 2);
        }

        $diffs = [];
        $cuentas = array_unique(array_merge(array_keys($porCuentaErp), array_keys($porCuentaAnita)));
        sort($cuentas);

        foreach ($cuentas as $cuenta) {
            $erp = $porCuentaErp[$cuenta] ?? ['debe' => 0, 'haber' => 0, 'cuenta_codigo' => ''];
            $anita = $porCuentaAnita[$cuenta] ?? ['debe' => 0, 'haber' => 0, 'cuenta_codigo' => ''];
            $dDebe = round($erp['debe'] - $anita['debe'], 2);
            $dHaber = round($erp['haber'] - $anita['haber'], 2);
            if (abs($dDebe) > $tolerancia || abs($dHaber) > $tolerancia) {
                $diffs[] = [
                    'cuenta' => $cuenta,
                    'cuenta_codigo' => $erp['cuenta_codigo'] ?: ($anita['cuenta_codigo'] ?? ''),
                    'debe_erp' => $erp['debe'],
                    'debe_anita' => $anita['debe'],
                    'haber_erp' => $erp['haber'],
                    'haber_anita' => $anita['haber'],
                    'delta_debe' => $dDebe,
                    'delta_haber' => $dHaber,
                ];
            }
        }

        return $diffs;
    }

    /**
     * @param  array<string, mixed>  $resultadoErp
     * @param  list<array<string, mixed>>  $saldosAnita
     * @return list<array<string, mixed>>
     */
    private function compararSaldosIniciales(array $resultadoErp, array $saldosAnita, float $tolerancia): array
    {
        $porCuentaErp = [];
        foreach ($resultadoErp['secciones'] ?? [] as $sec) {
            $c = (int) ($sec['cuenta'] ?? 0);
            $porCuentaErp[$c] = (float) ($sec['saldo_ejercicio_inicial'] ?? $sec['saldo_inicial'] ?? 0);
        }

        $porCuentaAnita = [];
        foreach ($saldosAnita as $s) {
            $c = (int) ($s['cuenta'] ?? 0);
            $porCuentaAnita[$c] = (float) ($s['saldo_ejercicio'] ?? 0);
        }

        $diffs = [];
        $cuentas = array_unique(array_merge(array_keys($porCuentaErp), array_keys($porCuentaAnita)));
        sort($cuentas);

        foreach ($cuentas as $cuenta) {
            $erp = $porCuentaErp[$cuenta] ?? 0.0;
            $anita = $porCuentaAnita[$cuenta] ?? 0.0;
            $delta = round($erp - $anita, 2);
            if (abs($delta) > $tolerancia) {
                $diffs[] = [
                    'cuenta' => $cuenta,
                    'saldo_erp' => $erp,
                    'saldo_anita' => $anita,
                    'delta' => $delta,
                ];
            }
        }

        return $diffs;
    }

    /**
     * @param  list<array<string, mixed>>  $lineas
     * @return array<string, array{cantidad: int, ejemplos: list<array<string, mixed>>}>
     */
    private function agruparPorClave(array $lineas): array
    {
        $grupos = [];

        foreach ($lineas as $ln) {
            $clave = $this->claveLinea($ln);
            if (! isset($grupos[$clave])) {
                $grupos[$clave] = ['cantidad' => 0, 'ejemplos' => []];
            }
            $grupos[$clave]['cantidad']++;
            $grupos[$clave]['ejemplos'][] = $ln;
        }

        return $grupos;
    }

    /**
     * @param  array<string, mixed>  $ln
     */
    private function claveLinea(array $ln): string
    {
        $cuenta = (int) ($ln['cuenta'] ?? 0);
        $fecha = (int) ($ln['fecha'] ?? 0);
        if ($fecha <= 0 && ! empty($ln['fecha_fmt'])) {
            $parts = explode('/', (string) $ln['fecha_fmt']);
            if (count($parts) === 3) {
                $y = (int) $parts[2];
                $y += $y < 100 ? ($y >= 70 ? 1900 : 2000) : 0;
                $fecha = (int) sprintf('%04d%02d%02d', $y, (int) $parts[1], (int) $parts[0]);
            }
        }

        $nro = (int) ($ln['nro_asiento'] ?? 0);
        $debe = number_format((float) ($ln['debe'] ?? 0), 2, '.', '');
        $haber = number_format((float) ($ln['haber'] ?? 0), 2, '.', '');
        $desc = mb_strtolower(trim((string) ($ln['descripcion'] ?? '')));
        $comp = trim((string) ($ln['comprobante'] ?? ''));

        return implode('|', [$cuenta, $fecha, $nro, $debe, $haber, $desc, $comp]);
    }

    /**
     * @param  array<string, mixed>|null  $erp
     * @param  array<string, mixed>|null  $anita
     * @return array<string, mixed>
     */
    private function filaDiff(string $tipo, string $clave, ?array $erp, ?array $anita): array
    {
        $src = $erp ?? $anita ?? [];

        return [
            'tipo' => $tipo,
            'clave' => $clave,
            'cuenta_codigo' => $src['cuenta_codigo'] ?? '',
            'fecha_fmt' => $src['fecha_fmt'] ?? '',
            'nro_asiento' => $src['nro_asiento'] ?? '',
            'descripcion' => mb_substr((string) ($src['descripcion'] ?? ''), 0, 40),
            'debe' => $src['debe'] ?? 0,
            'haber' => $src['haber'] ?? 0,
        ];
    }

    /**
     * @param  array<string, mixed>  $informe
     */
    public function guardarInforme(array $informe, string $directorio): string
    {
        if (! is_dir($directorio)) {
            mkdir($directorio, 0775, true);
        }

        $path = rtrim($directorio, '/').'/mayor_plano_comparacion_'.date('Ymd_His').'.json';
        file_put_contents($path, json_encode($informe, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        return $path;
    }
}
