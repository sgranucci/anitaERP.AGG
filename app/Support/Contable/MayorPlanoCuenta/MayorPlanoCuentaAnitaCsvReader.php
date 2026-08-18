<?php

namespace App\Support\Contable\MayorPlanoCuenta;

/**
 * Lee export CSV/Excel de Anita l-mayor (mayor analítico por cuenta).
 *
 * Soporta:
 * - delimitador `;` (histórico) o `,` (export actual)
 * - con o sin columna TAS (tipo asiento sistema) tras la fecha
 */
class MayorPlanoCuentaAnitaCsvReader
{
    /**
     * Índices de columnas de detalle (0-based) una vez detectado el layout.
     *
     * @var array{
     *   nro_asiento: int,
     *   tipo_comp: int,
     *   comprobante: int,
     *   emisor: int,
     *   cuit: int,
     *   descripcion: int,
     *   nro_oc: int,
     *   moneda: int,
     *   cotizacion: int,
     *   mon_referencia: int,
     *   debe: int,
     *   haber: int,
     *   saldo_mes: int,
     *   saldo_ejercicio: int,
     *   empresa: int,
     *   tas: int|null
     * }
     */
    private array $cols = [];

    private string $delimiter = ';';

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
        $layoutListo = false;

        while (($raw = fgets($handle)) !== false) {
            $numLinea++;
            $raw = $this->limpiarCsvLine($raw);

            if ($numLinea <= 5) {
                $metadata['linea_'.$numLinea] = trim($raw);

                continue;
            }

            if (! $layoutListo) {
                $this->detectarLayout($raw);
                $layoutListo = true;
                // La fila 6 suele ser el encabezado de columnas.
                if ($this->pareceEncabezadoColumnas($raw)) {
                    continue;
                }
            }

            $cols = str_getcsv(rtrim($raw, "\r\n"), $this->delimiter, '"');
            if ($cols === [] || (count($cols) === 1 && trim((string) $cols[0]) === '')) {
                continue;
            }

            $col0 = trim((string) ($cols[0] ?? ''));

            if (str_starts_with($col0, 'Cuenta:')) {
                [$cuentaCodigoActual, $cuentaNombreActual, $cuentaActual] = $this->parsearHeaderCuenta($col0);

                continue;
            }

            if (str_starts_with($col0, 'Saldo Inicial')) {
                $idxSaldo = $this->cols['saldo_ejercicio'];
                $saldoEj = $this->parsearMonto((string) ($cols[$idxSaldo] ?? $cols[$idxSaldo - 1] ?? ''));
                $saldosIniciales[] = [
                    'cuenta' => $cuentaActual,
                    'cuenta_codigo' => $cuentaCodigoActual,
                    'cuenta_nombre' => $cuentaNombreActual,
                    'saldo_ejercicio' => $saldoEj,
                ];

                continue;
            }

            if (str_starts_with($col0, 'Total cuenta')) {
                $totalesCuenta[] = [
                    'cuenta' => $cuentaActual,
                    'cuenta_codigo' => $cuentaCodigoActual,
                    'texto' => $col0,
                    'debe' => $this->parsearMonto((string) ($cols[$this->cols['debe']] ?? '')),
                    'haber' => $this->parsearMonto((string) ($cols[$this->cols['haber']] ?? '')),
                ];

                continue;
            }

            if (str_starts_with($col0, 'Total general')) {
                $metadata['total_general_debe'] = (string) $this->parsearMonto((string) ($cols[$this->cols['debe']] ?? ''));
                $metadata['total_general_haber'] = (string) $this->parsearMonto((string) ($cols[$this->cols['haber']] ?? ''));

                continue;
            }

            $fecha = $col0;
            if ($fecha === '' || ! preg_match('/^\d{2}\/\d{2}\/\d{2}$/', $fecha)) {
                continue;
            }

            $nroAsiento = (int) preg_replace('/\D/', '', trim((string) ($cols[$this->cols['nro_asiento']] ?? '')));
            $debe = $this->parsearMonto((string) ($cols[$this->cols['debe']] ?? ''));
            $haber = $this->parsearMonto((string) ($cols[$this->cols['haber']] ?? ''));

            $lineas[] = [
                'cuenta' => $cuentaActual,
                'cuenta_codigo' => $cuentaCodigoActual,
                'cuenta_nombre' => $cuentaNombreActual,
                'fecha_fmt' => $fecha,
                'fecha' => $this->fechaDdMmYyAymd($fecha),
                'tas' => $this->cols['tas'] !== null
                    ? trim((string) ($cols[$this->cols['tas']] ?? ''))
                    : '',
                'nro_asiento' => $nroAsiento,
                'tipo_comp' => trim((string) ($cols[$this->cols['tipo_comp']] ?? '')),
                'comprobante' => trim((string) ($cols[$this->cols['comprobante']] ?? '')),
                'emisor' => trim((string) ($cols[$this->cols['emisor']] ?? '')),
                'cuit' => trim((string) ($cols[$this->cols['cuit']] ?? '')),
                'descripcion' => trim((string) ($cols[$this->cols['descripcion']] ?? '')),
                'nro_oc' => (int) preg_replace('/\D/', '', trim((string) ($cols[$this->cols['nro_oc']] ?? ''))),
                'moneda_abrev' => trim((string) ($cols[$this->cols['moneda']] ?? '')),
                'cotizacion' => $this->parsearMonto((string) ($cols[$this->cols['cotizacion']] ?? ''), true),
                'mon_referencia' => $this->parsearMonto((string) ($cols[$this->cols['mon_referencia']] ?? '')),
                'debe' => $debe,
                'haber' => $haber,
                'saldo_mes' => $this->parsearMonto((string) ($cols[$this->cols['saldo_mes']] ?? '')),
                'saldo_ejercicio' => $this->parsearMonto((string) ($cols[$this->cols['saldo_ejercicio']] ?? '')),
                'empresa_id' => (int) preg_replace('/\D/', '', trim((string) ($cols[$this->cols['empresa']] ?? '1'))),
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

    private function detectarLayout(string $rawHeaderOrFirstData): void
    {
        $coma = substr_count($rawHeaderOrFirstData, ',');
        $puntoYComa = substr_count($rawHeaderOrFirstData, ';');
        $this->delimiter = $puntoYComa > $coma ? ';' : ',';

        $cols = str_getcsv(rtrim($rawHeaderOrFirstData, "\r\n"), $this->delimiter, '"');
        $headers = array_map(static fn ($c) => mb_strtoupper(trim((string) $c)), $cols);

        $tieneTas = false;
        foreach ($headers as $h) {
            if ($h === 'TAS' || str_contains($h, 'TAS')) {
                $tieneTas = true;
                break;
            }
        }

        // Si no hay encabezado claro, inferir por cantidad de columnas de una fila dato.
        if (! $this->pareceEncabezadoColumnas($rawHeaderOrFirstData)) {
            // Layout con TAS: Fecha,TAS,N.Asi.,Tip,... = 17 cols típicas
            // Sin TAS: 16 cols.
            $tieneTas = count($cols) >= 17;
        }

        if ($tieneTas) {
            // Fecha | TAS | N.Asi. | Tip | Comprobante | Emisor | CUIT | Desc | OC | Mon | Cot | Mon.Ref | Debe | Haber | Saldo mes | Saldo ej | Empr
            $this->cols = [
                'tas' => 1,
                'nro_asiento' => 2,
                'tipo_comp' => 3,
                'comprobante' => 4,
                'emisor' => 5,
                'cuit' => 6,
                'descripcion' => 7,
                'nro_oc' => 8,
                'moneda' => 9,
                'cotizacion' => 10,
                'mon_referencia' => 11,
                'debe' => 12,
                'haber' => 13,
                'saldo_mes' => 14,
                'saldo_ejercicio' => 15,
                'empresa' => 16,
            ];
        } else {
            // Fecha | N.Asi. | Tip | Comprobante | Emisor | CUIT | Desc | OC | Mon | Cot | Mon.Ref | Debe | Haber | Saldo mes | Saldo ej | Empr
            $this->cols = [
                'tas' => null,
                'nro_asiento' => 1,
                'tipo_comp' => 2,
                'comprobante' => 3,
                'emisor' => 4,
                'cuit' => 5,
                'descripcion' => 6,
                'nro_oc' => 7,
                'moneda' => 8,
                'cotizacion' => 9,
                'mon_referencia' => 10,
                'debe' => 11,
                'haber' => 12,
                'saldo_mes' => 13,
                'saldo_ejercicio' => 14,
                'empresa' => 15,
            ];
        }
    }

    private function pareceEncabezadoColumnas(string $raw): bool
    {
        $u = mb_strtoupper($raw);

        return str_contains($u, 'N.ASI') || str_contains($u, 'NRO') || str_contains($u, 'DEBE');
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
