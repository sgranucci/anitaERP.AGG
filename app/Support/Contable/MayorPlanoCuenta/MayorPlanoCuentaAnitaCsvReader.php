<?php

namespace App\Support\Contable\MayorPlanoCuenta;

/**
 * Lee export CSV/Excel de Anita l-mayor (mayor analítico por cuenta).
 */
class MayorPlanoCuentaAnitaCsvReader
{
    /**
     * @return array{
     *   metadata: array<string, string>,
     *   lineas: list<array<string, mixed>>,
     *   totales_cuenta: list<array<string, mixed>>,
     *   saldos_iniciales: list<array<string, mixed>>
     * }
     */
    public function leer(string $ruta): array
    {
        if (! is_readable($ruta)) {
            throw new \InvalidArgumentException('No se puede leer el archivo: '.$ruta);
        }

        $handle = fopen($ruta, 'r');
        if ($handle === false) {
            throw new \RuntimeException('No se pudo abrir: '.$ruta);
        }

        $metadata = [];
        $lineas = [];
        $totalesCuenta = [];
        $saldosIniciales = [];
        $cuentaActual = 0;
        $cuentaCodigoActual = '';
        $cuentaNombreActual = '';
        $numLinea = 0;

        while (($raw = fgets($handle)) !== false) {
            $numLinea++;
            if ($numLinea <= 5) {
                $metadata['linea_'.$numLinea] = trim($this->limpiarCsvLine($raw));

                continue;
            }

            $cols = str_getcsv($this->limpiarCsvLine($raw), ';', '"');
            if ($numLinea === 6) {
                continue;
            }

            if ($cols === [] || (count($cols) === 1 && trim($cols[0]) === '')) {
                continue;
            }

            $col0 = trim($cols[0] ?? '');

            if (str_starts_with($col0, 'Cuenta:')) {
                [$cuentaCodigoActual, $cuentaNombreActual, $cuentaActual] = $this->parsearHeaderCuenta($col0);

                continue;
            }

            if (str_starts_with($col0, 'Saldo Inicial')) {
                $saldoEj = $this->parsearMonto($cols[14] ?? $cols[13] ?? '');
                $saldosIniciales[] = [
                    'cuenta' => $cuentaActual,
                    'cuenta_codigo' => $cuentaCodigoActual,
                    'cuenta_nombre' => $cuentaNombreActual,
                    'saldo_ejercicio' => $saldoEj,
                ];

                continue;
            }

            if (str_starts_with($col0, 'Total ')) {
                $totalesCuenta[] = [
                    'cuenta' => $cuentaActual,
                    'cuenta_codigo' => $cuentaCodigoActual,
                    'texto' => $col0,
                    'debe' => $this->parsearMonto($cols[11] ?? ''),
                    'haber' => $this->parsearMonto($cols[12] ?? ''),
                ];

                continue;
            }

            $fecha = trim($col0);
            if ($fecha === '' || ! preg_match('/^\d{2}\/\d{2}\/\d{2}$/', $fecha)) {
                continue;
            }

            $nroAsiento = (int) preg_replace('/\D/', '', trim($cols[1] ?? ''));
            $debe = $this->parsearMonto($cols[11] ?? '');
            $haber = $this->parsearMonto($cols[12] ?? '');

            $lineas[] = [
                'cuenta' => $cuentaActual,
                'cuenta_codigo' => $cuentaCodigoActual,
                'cuenta_nombre' => $cuentaNombreActual,
                'fecha_fmt' => $fecha,
                'fecha' => $this->fechaDdMmYyAymd($fecha),
                'nro_asiento' => $nroAsiento,
                'tipo_comp' => trim($cols[2] ?? ''),
                'comprobante' => trim($cols[3] ?? ''),
                'emisor' => trim($cols[4] ?? ''),
                'cuit' => trim($cols[5] ?? ''),
                'descripcion' => trim($cols[6] ?? ''),
                'nro_oc' => (int) preg_replace('/\D/', '', trim($cols[7] ?? '')),
                'moneda_abrev' => trim($cols[8] ?? ''),
                'cotizacion' => $this->parsearMonto($cols[9] ?? '', true),
                'mon_referencia' => $this->parsearMonto($cols[10] ?? ''),
                'debe' => $debe,
                'haber' => $haber,
                'saldo_mes' => $this->parsearMonto($cols[13] ?? ''),
                'saldo_ejercicio' => $this->parsearMonto($cols[14] ?? ''),
                'empresa_id' => (int) preg_replace('/\D/', '', trim($cols[15] ?? '1')),
            ];
        }

        fclose($handle);

        return [
            'metadata' => $metadata,
            'lineas' => $lineas,
            'totales_cuenta' => $totalesCuenta,
            'saldos_iniciales' => $saldosIniciales,
        ];
    }

    private function limpiarCsvLine(string $line): string
    {
        return preg_replace('/^\xEF\xBB\xBF/', '', $line) ?? $line;
    }

    /**
     * @return array{0: string, 1: string, 2: int}
     */
    private function parsearHeaderCuenta(string $texto): array
    {
        $texto = trim($texto);
        if (! preg_match('/Cuenta:\s*(\d{3,6}-\d{3})\s*(.*)$/u', $texto, $m)) {
            return ['', '', 0];
        }

        $codigoFmt = trim($m[1]);
        $nombre = trim($m[2]);
        $codigo = MayorPlanoCuentaSupport::parsearCodigoCuenta($codigoFmt);

        return [$codigoFmt, $nombre, $codigo];
    }

    private function parsearMonto(string $valor, bool $permitirDecimalesLargos = false): float
    {
        $valor = trim(str_replace(['"', ' '], '', $valor));
        if ($valor === '' || $valor === '-') {
            return 0.0;
        }

        $decimales = $permitirDecimalesLargos ? 4 : 2;
        $negativo = str_starts_with($valor, '-');
        if ($negativo) {
            $valor = ltrim($valor, '-');
        }

        if (str_contains($valor, ',') && str_contains($valor, '.')) {
            $ultimoPunto = strrpos($valor, '.');
            $ultimaComa = strrpos($valor, ',');
            if ($ultimoPunto !== false && $ultimaComa !== false && $ultimoPunto > $ultimaComa) {
                $valor = str_replace(',', '', $valor);
            } else {
                $valor = str_replace('.', '', $valor);
                $valor = str_replace(',', '.', $valor);
            }
        } elseif (str_contains($valor, ',')) {
            $valor = str_replace('.', '', $valor);
            $valor = str_replace(',', '.', $valor);
        } elseif (substr_count($valor, '.') > 1) {
            $valor = str_replace('.', '', $valor);
        }

        $monto = round((float) $valor, $decimales);

        return $negativo ? -$monto : $monto;
    }

    private function fechaDdMmYyAymd(string $fecha): int
    {
        [$d, $m, $y] = explode('/', $fecha);
        $anio = (int) $y;
        if ($anio < 100) {
            $anio += $anio >= 70 ? 1900 : 2000;
        }

        return (int) sprintf('%04d%02d%02d', $anio, (int) $m, (int) $d);
    }
}
