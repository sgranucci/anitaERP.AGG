<?php

namespace App\Support\Contable\MayorConcepto;

/**
 * Parser del export CSV Anita l_mayor (mayor analítico plano).
 *
 * Delimitador ; (semicolon). Totales por cuenta en filas "Total cuenta …":
 * col. 11 = Debe del mes, col. 12 = Haber del mes (col. 10 = saldo inicial).
 */
class MayorPlanoAnitaCsvReader
{
    /**
     * @return array{
     *   metadata: array<string, mixed>,
     *   totales_cuenta: array<int, array<string, mixed>>,
     *   lineas: list<array<string, mixed>>
     * }
     */
    public function leer(string $ruta, bool $soloDisponibilidad = true): array
    {
        if (! is_readable($ruta)) {
            throw new \InvalidArgumentException('No se puede leer el CSV mayor plano Anita: '.$ruta);
        }

        $handle = fopen($ruta, 'r');
        if ($handle === false) {
            throw new \RuntimeException('No se pudo abrir el CSV mayor plano Anita: '.$ruta);
        }

        $metadata = [
            'ruta' => $ruta,
            'titulo' => '',
            'periodo' => '',
            'empresas' => '',
            'fecha_desde' => '',
            'fecha_hasta' => '',
        ];

        $totalesCuenta = [];
        $lineas = [];
        $cuentaActual = 0;
        $cuentaCodigo = '';
        $cuentaNombre = '';
        $filaNum = 0;

        try {
            while (($raw = fgets($handle)) !== false) {
                $filaNum++;
                $campos = str_getcsv(rtrim($raw, "\r\n"), ';', '"', '\\');
                if ($campos === [null] || $campos === []) {
                    continue;
                }

                $primero = trim((string) ($campos[0] ?? ''));

                if ($filaNum === 1) {
                    continue;
                }
                if ($filaNum === 2) {
                    $metadata['titulo'] = $primero;

                    continue;
                }
                if ($filaNum === 3) {
                    $metadata['periodo'] = $primero;
                    $this->extraerRangoFechas($primero, $metadata);

                    continue;
                }
                if ($filaNum === 4) {
                    $metadata['empresas'] = $primero;

                    continue;
                }
                if ($filaNum <= 6) {
                    continue;
                }

                if (str_starts_with($primero, 'Cuenta:')) {
                    [$cuentaActual, $cuentaCodigo, $cuentaNombre] = $this->parsearCuenta($primero);

                    continue;
                }

                if (str_starts_with($primero, 'Total cuenta')) {
                    $cuenta = $this->cuentaDesdeTotal($primero) ?: $cuentaActual;
                    if ($cuenta <= 0) {
                        continue;
                    }
                    if ($soloDisponibilidad && $cuenta > MayorConceptoMemoriaMotor::LIMITE_DISPONIBILIDAD) {
                        continue;
                    }

                    $debe = $this->parsearImporte($campos[11] ?? '');
                    $haber = $this->parsearImporte($campos[12] ?? '');
                    $codigoFmt = $this->codigoDesdeTotal($primero) ?: $cuentaCodigo;

                    $totalesCuenta[$cuenta] = [
                        'cuenta' => $cuenta,
                        'cuenta_codigo' => $codigoFmt !== '' ? $codigoFmt : $this->formatearCodigoCuenta($cuenta),
                        'cuenta_nombre' => $cuentaNombre,
                        'debe' => $debe,
                        'haber' => $haber,
                        'saldo_inicial' => $this->parsearImporte($campos[10] ?? ''),
                        'fila_csv' => $filaNum,
                    ];

                    continue;
                }

                if (! $this->pareceLineaDetalle($primero)) {
                    continue;
                }

                $cuentaLinea = $cuentaActual > 0 && $this->pareceCodigoCuenta($primero)
                    ? $this->normalizarCuentaCodigo($primero)
                    : $cuentaActual;
                if ($cuentaLinea <= 0) {
                    continue;
                }
                if ($soloDisponibilidad && $cuentaLinea > MayorConceptoMemoriaMotor::LIMITE_DISPONIBILIDAD) {
                    continue;
                }

                $lineas[] = [
                    'cuenta' => $cuentaLinea,
                    'cuenta_codigo' => $this->formatearCodigoCuenta($cuentaLinea),
                    'fecha_fmt' => trim((string) ($campos[0] ?? '')),
                    'nro_asiento' => (int) preg_replace('/\D/', '', (string) ($campos[1] ?? '')),
                    'tipo_comp' => trim((string) ($campos[2] ?? '')),
                    'comprobante' => trim((string) ($campos[3] ?? '')),
                    'descripcion' => trim((string) ($campos[6] ?? '')),
                    'debe' => $this->parsearImporte($campos[11] ?? ''),
                    'haber' => $this->parsearImporte($campos[12] ?? ''),
                    'fila_csv' => $filaNum,
                ];
            }
        } finally {
            fclose($handle);
        }

        ksort($totalesCuenta);

        return [
            'metadata' => $metadata,
            'totales_cuenta' => $totalesCuenta,
            'lineas' => $lineas,
        ];
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    private function extraerRangoFechas(string $texto, array &$metadata): void
    {
        if (preg_match('/Desde\s+(\d{2}\/\d{2}\/\d{2})\s+hasta\s+(\d{2}\/\d{2}\/\d{2})/i', $texto, $m)) {
            $metadata['fecha_desde'] = $m[1];
            $metadata['fecha_hasta'] = $m[2];
        }
    }

    /**
     * @return array{0: int, 1: string, 2: string}
     */
    private function parsearCuenta(string $texto): array
    {
        if (preg_match('/Cuenta:\s*([\d\-]+)\s*(.*)$/i', $texto, $m)) {
            $codigo = $this->normalizarCuentaCodigo($m[1]);

            return [$codigo, $this->formatearCodigoCuenta($codigo), trim($m[2] ?? '')];
        }

        return [0, '', ''];
    }

    private function pareceLineaDetalle(string $valor): bool
    {
        $valor = trim($valor);

        return (bool) preg_match('/^\d{2}\/\d{2}\/\d{2}$/', $valor)
            || $this->pareceCodigoCuenta($valor);
    }

    private function pareceCodigoCuenta(string $valor): bool
    {
        return (bool) preg_match('/^\d{3,6}-\d{3}$/', trim($valor));
    }

    private function normalizarCuentaCodigo(string $codigo): int
    {
        $limpio = str_replace(['-', ' '], '', trim($codigo));

        return (int) $limpio;
    }

    public function formatearCodigoCuenta(int $codigo): string
    {
        $s = str_pad((string) $codigo, 9, '0', STR_PAD_LEFT);

        return substr($s, 0, 6).'-'.substr($s, 6, 3);
    }

    private function parsearImporte(mixed $valor): float
    {
        $s = trim((string) $valor);
        if ($s === '') {
            return 0.0;
        }

        $s = str_replace(',', '', $s);

        return round((float) $s, 2);
    }

    private function cuentaDesdeTotal(string $texto): int
    {
        if (preg_match('/Total cuenta\s+([\d\-]+)/i', $texto, $m)) {
            return $this->normalizarCuentaCodigo($m[1]);
        }

        return 0;
    }

    private function codigoDesdeTotal(string $texto): string
    {
        if (preg_match('/Total cuenta\s+([\d\-]+)/i', $texto, $m)) {
            return trim($m[1]);
        }

        return '';
    }
}
