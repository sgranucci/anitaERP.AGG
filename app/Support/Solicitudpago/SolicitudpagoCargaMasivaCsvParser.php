<?php

namespace App\Support\Solicitudpago;

/**
 * Parseo del CSV de carga masiva Anita (p-cargasolpm.c).
 * Separadores: coma o punto y coma. Pares cuenta/importe desde col 11.
 *
 * Si hay fila de encabezado con "Cuenta Haber" / "Cuenta Debe", se usa eso
 * para el D/H de cada par (p. ej. SUSS: 1 Haber + N Debe).
 */
final class SolicitudpagoCargaMasivaCsvParser
{
    public const MAX_FILAS = 1000;

    /**
     * @return list<array{
     *   nro_linea: int,
     *   empresa_codigo: int,
     *   proveedor_codigo: string,
     *   concepto_codigo: int,
     *   sector_codigo: int,
     *   forma_pago_codigo: int,
     *   beneficiario: string,
     *   moneda_codigo: string,
     *   detalle: string,
     *   fecha_vencimiento: ?string,
     *   monto: float,
     *   cuentas: list<array{cuenta_codigo: string, monto: float, debe_haber: ?string}>
     * }>
     */
    public function parsear(string $contenido): array
    {
        $lineas = preg_split("/\r\n|\n|\r/", $contenido) ?: [];
        $out = [];
        $nro = 0;
        /** @var list<?string>|null $hintsDh */
        $hintsDh = null;

        foreach ($lineas as $raw) {
            $nro++;
            if ($raw === '' || trim($raw) === '') {
                continue;
            }

            if ($this->esEncabezado($raw)) {
                $hintsDh = $this->extraerHintsDhDesdeEncabezado($raw);
                continue;
            }

            $empresaStr = $this->extraeCampo(1, $raw);
            $empresa = (int) $empresaStr;
            if ($empresa < 1 || $empresa > 999999) {
                continue;
            }

            if (count($out) >= self::MAX_FILAS) {
                break;
            }

            $proveedor = trim($this->extraeCampo(2, $raw));
            $proveedor = str_pad(substr(preg_replace('/\D/', '', $proveedor) ?: $proveedor, -6), 6, '0', STR_PAD_LEFT);

            $fechaVto = $this->parseFechaVto($this->extraeCampo(9, $raw));
            $monto = $this->parseMonto($this->extraeCampo(10, $raw));
            $cuentas = $this->parseCuentas($raw, $hintsDh);

            // Anita: si el 1er importe de cuenta viene en 0, asume una sola imputación
            // (Haber + Debe) con el monto de cabecera.
            if ($cuentas !== [] && abs(($cuentas[0]['monto'] ?? 0.0)) < 0.0000001) {
                $limite = min(2, count($cuentas));
                $cuentas = array_slice($cuentas, 0, $limite);
                foreach ($cuentas as $i => $cta) {
                    $cuentas[$i]['monto'] = $monto;
                }
            }

            $out[] = [
                'nro_linea' => $nro,
                'empresa_codigo' => $empresa,
                'proveedor_codigo' => $proveedor,
                'concepto_codigo' => (int) $this->extraeCampo(3, $raw),
                'sector_codigo' => (int) $this->extraeCampo(4, $raw),
                'forma_pago_codigo' => (int) $this->extraeCampo(5, $raw),
                'beneficiario' => $this->recortar($this->extraeCampo(6, $raw), 80),
                'moneda_codigo' => substr(trim($this->extraeCampo(7, $raw)), 0, 1) ?: '1',
                'detalle' => $this->recortar($this->extraeCampo(8, $raw), 180),
                'fecha_vencimiento' => $fechaVto,
                'monto' => $monto,
                'cuentas' => $cuentas,
            ];
        }

        return $out;
    }

    /**
     * Equivalente a extrae_campo() del C: separa por , o ;.
     */
    public function extraeCampo(int $nroCampo, string $buffer): string
    {
        $valor = '';
        $qCampo = 1;
        $len = strlen($buffer);

        for ($i = 0; $i < $len; $i++) {
            $ch = $buffer[$i];
            if ($nroCampo === $qCampo) {
                if ($ch !== ',' && $ch !== ';') {
                    $valor .= $ch;
                } else {
                    $qCampo++;
                }
            } elseif ($ch === ',' || $ch === ';') {
                $qCampo++;
            }
        }

        return rtrim($valor, "\r\n");
    }

    private function esEncabezado(string $buffer): bool
    {
        $c1 = mb_strtolower(trim($this->extraeCampo(1, $buffer)));
        if ($c1 === '' || is_numeric($c1)) {
            return false;
        }

        return str_starts_with($c1, 'empresa');
    }

    /**
     * @return list<?string> D/H por cada par cuenta/importe (null si no se reconoce)
     */
    private function extraerHintsDhDesdeEncabezado(string $buffer): array
    {
        $hints = [];
        $offCampo = 0;

        do {
            $labelCuenta = trim($this->extraeCampo(11 + $offCampo, $buffer));
            $labelImporte = trim($this->extraeCampo(12 + $offCampo, $buffer));
            if ($labelCuenta === '' && $labelImporte === '') {
                break;
            }

            $hints[] = $this->inferirDhDesdeTexto($labelCuenta.' '.$labelImporte);
            $offCampo += 2;
        } while ($offCampo < 200);

        return $hints;
    }

    private function inferirDhDesdeTexto(string $texto): ?string
    {
        $t = mb_strtolower($texto);
        $tieneHaber = str_contains($t, 'haber');
        $tieneDebe = str_contains($t, 'debe');
        if ($tieneHaber && ! $tieneDebe) {
            return 'H';
        }
        if ($tieneDebe && ! $tieneHaber) {
            return 'D';
        }

        return null;
    }

    /**
     * @param  list<?string>|null  $hintsDh
     * @return list<array{cuenta_codigo: string, monto: float, debe_haber: ?string}>
     */
    private function parseCuentas(string $buffer, ?array $hintsDh): array
    {
        $cuentas = [];
        $offCampo = 0;

        do {
            $str1 = $this->extraeCampo(11 + $offCampo, $buffer);
            if ($str1 === '' || strpos($str1, '-') === false) {
                break;
            }

            // Anita atol: quita guiones/espacios y convierte a long
            $codigoDigits = preg_replace('/\D/', '', $str1) ?? '';
            $codigoDigits = $codigoDigits !== '' ? (string) (int) $codigoDigits : '0';

            $monto = $this->parseMonto($this->extraeCampo(12 + $offCampo, $buffer));
            $idx = count($cuentas);
            $dh = null;
            if (is_array($hintsDh) && array_key_exists($idx, $hintsDh)) {
                $dh = $hintsDh[$idx];
            }

            $cuentas[] = [
                'cuenta_codigo' => $codigoDigits,
                'monto' => $monto,
                'debe_haber' => $dh,
            ];

            $offCampo += 2;
        } while (true);

        return $cuentas;
    }

    private function parseFechaVto(string $raw): ?string
    {
        $raw = trim($raw);
        if ($raw === '') {
            return null;
        }

        $parts = preg_split('#[/\-.]#', $raw) ?: [];
        if (count($parts) < 3) {
            return null;
        }

        $dia = (int) $parts[0];
        $mes = (int) $parts[1];
        $anio = (int) $parts[2];
        if ($anio <= 99) {
            $anio += 2000;
        }
        if ($dia < 1 || $mes < 1 || $mes > 12 || $anio < 1900) {
            return null;
        }

        return sprintf('%04d-%02d-%02d', $anio, $mes, $dia);
    }

    private function parseMonto(string $raw): float
    {
        $limpio = '';
        $len = strlen($raw);
        for ($i = 0; $i < $len; $i++) {
            $ch = $raw[$i];
            if ($ch !== ',' && $ch !== ' ') {
                $limpio .= $ch;
            }
        }

        return (float) $limpio;
    }

    private function recortar(string $v, int $max): string
    {
        $v = trim($v);
        if (mb_strlen($v) <= $max) {
            return $v;
        }

        return mb_substr($v, 0, $max);
    }
}
