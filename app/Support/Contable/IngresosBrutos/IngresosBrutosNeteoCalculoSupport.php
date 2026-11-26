<?php

declare(strict_types=1);

namespace App\Support\Contable\IngresosBrutos;

/**
 * Neteo de devoluciones de retenciones ARBA: cálculo puro (sin BD ni Anita).
 *
 * Montos en centavos enteros para no arrastrar error de coma flotante.
 * Alícuota en centésimas de punto: 4,00 % → 400.
 */
final class IngresosBrutosNeteoCalculoSupport
{
    public static function centavos(float $importe): int
    {
        return (int) round($importe * 100);
    }

    public static function pesos(int $centavos): float
    {
        return round($centavos / 100, 2);
    }

    public static function alicuotaCentesimas(float $alicuota): int
    {
        return (int) round($alicuota * 100);
    }

    /**
     * Base que da un importe con la alícuota: base = importe / (alícuota / 100).
     * Si el redondeo deja el impuesto un centavo arriba o abajo, mueve la base
     * de a un centavo hasta que el impuesto de ARBA coincide con el importe.
     * Devuelve centavos de base.
     */
    public static function baseCentavos(int $importeCentavos, int $alicuotaCentesimas): int
    {
        if ($alicuotaCentesimas <= 0 || $importeCentavos <= 0) {
            return 0;
        }

        $base = (int) round($importeCentavos * 10000 / $alicuotaCentesimas);
        for ($i = 0; $i < 8; $i++) {
            $impuesto = self::impuestoLineaCentavos($base, $alicuotaCentesimas);
            if ($impuesto === $importeCentavos) {
                return $base;
            }
            $base += $impuesto < $importeCentavos ? 1 : -1;
        }

        return max(0, $base);
    }

    /**
     * Impuesto de la línea como lo toma ARBA: base × alícuota, a 2 decimales.
     * 64.421,47 × 4 % = 2.576,8588 → 2.576,86 (no 2.576,85).
     */
    public static function impuestoLineaCentavos(int $baseCentavos, int $alicuotaCentesimas): int
    {
        if ($alicuotaCentesimas <= 0 || $baseCentavos === 0) {
            return 0;
        }

        return (int) round($baseCentavos * $alicuotaCentesimas / 10000);
    }

    /** VEP: total de la DJ truncado a 10 centavos. */
    public static function vepCentavos(int $djCentavos): int
    {
        return intdiv($djCentavos, 10) * 10;
    }

    /**
     * Total de la DJ ARBA: suma del impuesto de cada línea (a 2 decimales), no del importe del certificado.
     *
     * @param  list<array{base_calculo: float, alicuota: float}>  $lineas
     */
    public static function djCentavos(array $lineas): int
    {
        $total = 0;
        foreach ($lineas as $linea) {
            $total += self::impuestoLineaCentavos(
                self::centavos((float) $linea['base_calculo']),
                self::alicuotaCentesimas((float) $linea['alicuota']),
            );
        }

        return $total;
    }

    public static function digitos(string $valor): string
    {
        return preg_replace('/\D/', '', $valor) ?? '';
    }

    /**
     * Interpreta el detalle del movimiento de devolución en el mayor.
     *
     * - devolucion: DEV RET / «devolución de retenciones IIBB».
     * - anula_op: nº de OP anulada («ANULA OPP 12xxxx»).
     * - op: nº de OP citado (anulada o no).
     * - cert: certificado citado («certificado 52990»).
     * - codigo: código de proveedor al inicio («4518 ANULA OPP …»).
     *
     * @return array{devolucion: bool, anula_op: int, op: int, cert: int, codigo: string}
     */
    public static function analizarDetalle(string $detalle): array
    {
        $d = trim($detalle);

        $codigo = '';
        if (preg_match('/^\s*(\d{3,6})\s+ANULA\b/iu', $d, $m) === 1) {
            $codigo = $m[1];
        }

        $anula = 0;
        if (preg_match('/\bANULA\s+OPP?\s*#?\s*(\d{4,})/iu', $d, $m) === 1) {
            $anula = (int) $m[1];
        }

        $op = 0;
        if (preg_match('/\bOPP?\s*#?\s*(\d{4,})/iu', $d, $m) === 1) {
            $op = (int) $m[1];
        }

        $cert = 0;
        if (preg_match('/\bcert(?:ificado)?\.?\s*(?:n[º°o]?\s*)?(\d{3,})/iu', $d, $m) === 1) {
            $cert = (int) $m[1];
        }

        $devolucion = preg_match('/^DEV\s+RET\b/iu', $d) === 1
            || preg_match('/\bDEVOLUC\S*\s+(?:DE\s+)?(?:RETENCIONES?\s+)?(?:IIBB|INGRESOS\s+BRUTOS)\b/iu', $d) === 1;

        return [
            'devolucion' => $devolucion,
            'anula_op' => $anula,
            'op' => $anula > 0 ? $anula : $op,
            'cert' => $cert,
            'codigo' => $codigo,
        ];
    }

    /**
     * Aplica devoluciones contra certificados positivos del mismo proveedor.
     *
     * Regla: solo se netea contra retenciones del mismo proveedor. Primero el
     * certificado devuelto (si está en el listado), después el más antiguo.
     * Lo que no alcanza queda sin neteo (pendiente).
     *
     * @param  list<array{key: string, proveedor: string, nro_cert: int, importe: float, base_calculo: float, alicuota: float}>  $certs
     * @param  list<array{id: string, proveedor: string, importe: float, cert_ref: int, fecha: string}>  $devoluciones  importe = monto a netear
     * @return array{
     *   aplicaciones: list<array{devolucion_id: string, key: string, nro_cert: int, importe: float}>,
     *   certs: array<string, array<string, mixed>>,
     *   sin_neteo: array<string, float>
     * }
     */
    public static function aplicar(array $certs, array $devoluciones): array
    {
        $estado = [];
        foreach ($certs as $cert) {
            $importeC = self::centavos((float) $cert['importe']);
            $estado[(string) $cert['key']] = [
                'original' => $cert,
                'importe_c' => $importeC,
                'restante_c' => $importeC,
                'neteado_c' => 0,
            ];
        }

        $devs = $devoluciones;
        usort($devs, static fn (array $a, array $b): int => [(string) $a['fecha'], (string) $a['id']]
            <=> [(string) $b['fecha'], (string) $b['id']]);

        $aplicaciones = [];
        $sinNeteo = [];

        foreach ($devs as $dev) {
            $remC = self::centavos((float) $dev['importe']);
            if ($remC <= 0) {
                continue;
            }
            $prov = self::digitos((string) $dev['proveedor']);
            if ($prov === '') {
                $sinNeteo[(string) $dev['id']] = self::pesos($remC);

                continue;
            }

            $ref = (int) ($dev['cert_ref'] ?? 0);
            $candidatas = [];
            foreach ($estado as $key => $e) {
                if ($e['restante_c'] > 0 && self::digitos((string) $e['original']['proveedor']) === $prov) {
                    $candidatas[] = (string) $key;
                }
            }
            usort($candidatas, static function (string $a, string $b) use ($estado, $ref): int {
                $nroA = (int) $estado[$a]['original']['nro_cert'];
                $nroB = (int) $estado[$b]['original']['nro_cert'];

                return [$nroA === $ref ? 0 : 1, $nroA] <=> [$nroB === $ref ? 0 : 1, $nroB];
            });

            foreach ($candidatas as $key) {
                if ($remC <= 0) {
                    break;
                }
                $tomo = min($remC, $estado[$key]['restante_c']);
                $estado[$key]['restante_c'] -= $tomo;
                $estado[$key]['neteado_c'] += $tomo;
                $remC -= $tomo;
                $aplicaciones[] = [
                    'devolucion_id' => (string) $dev['id'],
                    'key' => $key,
                    'nro_cert' => (int) $estado[$key]['original']['nro_cert'],
                    'importe' => self::pesos($tomo),
                ];
            }

            if ($remC > 0) {
                $sinNeteo[(string) $dev['id']] = self::pesos($remC);
            }
        }

        $salida = [];
        foreach ($estado as $key => $e) {
            $o = $e['original'];
            $alic = self::alicuotaCentesimas((float) $o['alicuota']);
            $restante = (int) $e['restante_c'];
            $neteado = (int) $e['neteado_c'];

            if ($neteado === 0) {
                $baseTxt = (float) $o['base_calculo'];
                $estadoCert = 'sin_cambio';
            } elseif ($restante === 0) {
                $baseTxt = 0.0;
                $estadoCert = 'eliminado';
            } else {
                $baseTxt = self::pesos(self::baseCentavos($restante, $alic));
                $estadoCert = 'modificado';
            }

            $salida[(string) $key] = [
                'key' => (string) $key,
                'nro_cert' => (int) $o['nro_cert'],
                'proveedor' => (string) $o['proveedor'],
                'alicuota' => (float) $o['alicuota'],
                'base_original' => round((float) $o['base_calculo'], 2),
                'importe_original' => self::pesos((int) $e['importe_c']),
                'neteado' => self::pesos($neteado),
                'importe_nuevo' => self::pesos($restante),
                'base_txt' => $baseTxt,
                'incluir_txt' => $restante > 0,
                'estado' => $estadoCert,
            ];
        }

        return [
            'aplicaciones' => $aplicaciones,
            'certs' => $salida,
            'sin_neteo' => $sinNeteo,
        ];
    }
}
