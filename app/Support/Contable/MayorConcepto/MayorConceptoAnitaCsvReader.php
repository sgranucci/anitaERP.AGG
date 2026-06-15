<?php

namespace App\Support\Contable\MayorConcepto;

/**
 * Parser del export CSV de Anita (l-mayorconc / l_mayorconc).
 */
class MayorConceptoAnitaCsvReader
{
    /**
     * @return array{
     *   metadata: array<string, mixed>,
     *   lineas: list<array<string, mixed>>,
     *   totales_cuenta: list<array<string, mixed>>,
     *   totales_concepto: list<array<string, mixed>>
     * }
     */
    public function leer(string $ruta): array
    {
        if (! is_readable($ruta)) {
            throw new \InvalidArgumentException('No se puede leer el CSV Anita: '.$ruta);
        }

        $handle = fopen($ruta, 'r');
        if ($handle === false) {
            throw new \RuntimeException('No se pudo abrir el CSV Anita: '.$ruta);
        }

        $metadata = [
            'ruta' => $ruta,
            'titulo' => '',
            'periodo' => '',
            'empresas' => '',
            'fecha_desde' => '',
            'fecha_hasta' => '',
        ];

        $lineas = [];
        $totalesCuenta = [];
        $totalesConcepto = [];
        $conceptoActual = 0;
        $conceptoNombre = '';
        $cuentaActual = 0;
        $cuentaCodigo = '';
        $filaNum = 0;

        try {
            while (($raw = fgets($handle)) !== false) {
                $filaNum++;
                $campos = str_getcsv(rtrim($raw, "\r\n"), ',', '"', '\\');
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
                if ($filaNum === 5) {
                    continue;
                }

                if (str_starts_with($primero, 'Concepto:')) {
                    [$conceptoActual, $conceptoNombre] = $this->parsearConcepto($primero);

                    continue;
                }

                if (str_starts_with($primero, 'Cuenta:')) {
                    [$cuentaActual, $cuentaCodigo] = $this->parsearCuenta($primero);

                    continue;
                }

                if (str_starts_with($primero, 'Total cuenta')) {
                    $totalesCuenta[] = $this->filaTotal(
                        'cuenta',
                        $conceptoActual,
                        $conceptoNombre,
                        $this->cuentaDesdeTotal($primero) ?: $cuentaActual,
                        $this->codigoDesdeTotal($primero) ?: $cuentaCodigo,
                        $campos,
                    );

                    continue;
                }

                if (str_starts_with($primero, 'Total concepto')) {
                    $totalesConcepto[] = $this->filaTotal(
                        'concepto',
                        $this->conceptoDesdeTotal($primero) ?: $conceptoActual,
                        $this->nombreConceptoDesdeTotal($primero) ?: $conceptoNombre,
                        0,
                        '',
                        $campos,
                    );

                    continue;
                }

                if (! $this->pareceLineaDetalle($primero)) {
                    continue;
                }

                $cuenta = $this->normalizarCuentaCodigo($primero);
                if ($cuenta <= 0) {
                    continue;
                }

                $debe = $this->parsearImporte($campos[14] ?? '');
                $haber = $this->parsearImporte($campos[15] ?? '');

                $lineas[] = [
                    'concepto_id' => $conceptoActual,
                    'concepto_nombre' => $conceptoNombre,
                    'cuenta' => $cuenta,
                    'cuenta_codigo' => $this->formatearCodigoCuenta($cuenta),
                    'cuenta_nombre' => trim((string) ($campos[1] ?? '')),
                    'fecha_fmt' => trim((string) ($campos[2] ?? '')),
                    'nro_asiento' => (int) preg_replace('/\D/', '', (string) ($campos[3] ?? '')),
                    'tipo_comp' => trim((string) ($campos[4] ?? '')),
                    'comprobante' => trim((string) ($campos[5] ?? '')),
                    'cheque' => trim((string) ($campos[6] ?? '')),
                    'nro_oc' => (int) preg_replace('/\D/', '', (string) ($campos[7] ?? '')),
                    'emisor' => trim((string) ($campos[8] ?? '')),
                    'cuit' => trim((string) ($campos[9] ?? '')),
                    'descripcion' => trim((string) ($campos[10] ?? '')),
                    'moneda_abrev' => trim((string) ($campos[11] ?? '')),
                    'cotizacion' => $this->parsearImporte($campos[12] ?? ''),
                    'mon_referencia' => $this->parsearImporte($campos[13] ?? ''),
                    'debe' => $debe,
                    'haber' => $haber,
                    'origen' => 'anita_csv',
                    'fila_csv' => $filaNum,
                ];
            }
        } finally {
            fclose($handle);
        }

        return [
            'metadata' => $metadata,
            'lineas' => $lineas,
            'totales_cuenta' => $totalesCuenta,
            'totales_concepto' => $totalesConcepto,
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
     * @return array{0: int, 1: string}
     */
    private function parsearConcepto(string $texto): array
    {
        if (preg_match('/Concepto:\s*(\d+)\s+(.+)/', $texto, $m)) {
            return [(int) $m[1], trim($m[2])];
        }

        return [0, ''];
    }

    /**
     * @return array{0: int, 1: string}
     */
    private function parsearCuenta(string $texto): array
    {
        if (preg_match('/Cuenta:\s*([\d\-]+)/', $texto, $m)) {
            $codigo = $this->normalizarCuentaCodigo($m[1]);

            return [$codigo, $this->formatearCodigoCuenta($codigo)];
        }

        return [0, ''];
    }

    private function pareceLineaDetalle(string $cuenta): bool
    {
        return (bool) preg_match('/^\d{3,6}-\d{3}$/', $cuenta);
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

    /**
     * @param  list<string|null>  $campos
     * @return array<string, mixed>
     */
    private function filaTotal(
        string $tipo,
        int $conceptoId,
        string $conceptoNombre,
        int $cuenta,
        string $cuentaCodigo,
        array $campos,
    ): array {
        $debe = $this->parsearImporte($campos[14] ?? '');
        $haber = $this->parsearImporte($campos[15] ?? '');

        if ($debe <= 0 && $haber <= 0) {
            $haber = $this->parsearImporte($campos[16] ?? '');
        }

        return [
            'tipo' => $tipo,
            'concepto_id' => $conceptoId,
            'concepto_nombre' => $conceptoNombre,
            'cuenta' => $cuenta,
            'cuenta_codigo' => $cuentaCodigo !== '' ? $cuentaCodigo : ($cuenta > 0 ? $this->formatearCodigoCuenta($cuenta) : ''),
            'debe' => $debe,
            'haber' => $haber,
        ];
    }

    private function cuentaDesdeTotal(string $texto): int
    {
        if (preg_match('/Total cuenta\s+([\d\-]+)/', $texto, $m)) {
            return $this->normalizarCuentaCodigo($m[1]);
        }

        return 0;
    }

    private function codigoDesdeTotal(string $texto): string
    {
        if (preg_match('/Total cuenta\s+([\d\-]+)/', $texto, $m)) {
            return trim($m[1]);
        }

        return '';
    }

    private function conceptoDesdeTotal(string $texto): int
    {
        if (preg_match('/Total concepto\s+(\d+)/', $texto, $m)) {
            return (int) $m[1];
        }

        return 0;
    }

    private function nombreConceptoDesdeTotal(string $texto): string
    {
        if (preg_match('/Total concepto\s+\d+\s+(.+)/', $texto, $m)) {
            return trim($m[1]);
        }

        return '';
    }
}
