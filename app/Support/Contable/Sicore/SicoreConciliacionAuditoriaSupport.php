<?php

declare(strict_types=1);

namespace App\Support\Contable\Sicore;

final class SicoreConciliacionAuditoriaSupport
{
    /**
     * Pasivo (2) y resultado/patrimonio (3) tienen naturaleza inversa al activo.
     * Fallback por prefijo numérico del plan Anita (2xx, 4xx).
     */
    public static function esCuentaInversa(?string $tipocuenta, int $codigoNumerico = 0): bool
    {
        if (in_array((string) $tipocuenta, ['2', '3'], true)) {
            return true;
        }

        if ($codigoNumerico >= 200000000 && $codigoNumerico < 500000000) {
            return true;
        }

        return false;
    }

    /**
     * @param  list<array{codigo?: string, tipocuenta?: string|null}>  $cuentas
     */
    public static function cuentasSonInversas(array $cuentas): bool
    {
        if ($cuentas === []) {
            return false;
        }

        foreach ($cuentas as $cuenta) {
            $codigo = (int) preg_replace('/\D/', '', (string) ($cuenta['codigo'] ?? ''));
            if (! self::esCuentaInversa($cuenta['tipocuenta'] ?? null, $codigo)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Neto del mayor en convención contable (haber +, debe −).
     *
     * @param  list<array<string, mixed>>  $movimientos
     */
    public static function totalMayorNeto(array $movimientos): float
    {
        return round(array_sum(array_map(
            static fn (array $m) => (float) ($m['neto_haber'] ?? 0),
            $movimientos,
        )), 2);
    }

    /**
     * Total mayor comparable con SICORE: neto firmado (haber +, debe −), misma convención que el archivo a presentar.
     *
     * @param  list<array<string, mixed>>  $movimientos
     */
    public static function totalMayorComparable(array $movimientos): float
    {
        return self::totalMayorNeto($movimientos);
    }

    /**
     * @deprecated Solo referencia de saldo pasivo invertido; no usar para la diferencia vs SICORE.
     *
     * @param  list<array<string, mixed>>  $movimientos
     */
    public static function totalMayorSaldoInvertido(array $movimientos, bool $cuentaInversa): ?float
    {
        if (! $cuentaInversa) {
            return null;
        }

        return round(-self::totalMayorNeto($movimientos), 2);
    }

    /**
     * Importe firmado de una línea del mayor (haber +, debe −).
     */
    public static function importeLineaMayorFirmado(array $mov): float
    {
        $haber = (float) ($mov['haber'] ?? 0);
        $debe = (float) ($mov['debe'] ?? 0);

        if ($haber > 0.001) {
            return round($haber, 2);
        }
        if ($debe > 0.001) {
            return round(-$debe, 2);
        }

        return 0.0;
    }

    /**
     * @param  list<array<string, mixed>>  $registrosSicore
     * @param  list<array<string, mixed>>  $movimientosMayor  Mayor del período (totales / solo_mayor)
     * @param  list<array<string, mixed>>|null  $movimientosMayorMatch  Pool ampliado para matching
     *                                                                (p. ej. ±1 día: AOP vs Debe)
     * @return array<string, mixed>
     */
    public static function auditarOperaciones(
        array $registrosSicore,
        array $movimientosMayor,
        bool $cuentaInversa,
        float $tolerancia,
        ?array $movimientosMayorMatch = null,
    ): array {
        $poolMatchFuente = $movimientosMayorMatch ?? $movimientosMayor;

        /** @var list<array<string, mixed>> $poolMatch */
        $poolMatch = [];
        foreach ($poolMatchFuente as $idx => $mov) {
            $poolMatch[] = array_merge($mov, [
                '_idx' => $idx,
                '_usado' => false,
                '_en_periodo' => false,
                'importe_conciliacion' => self::importeLineaMayorFirmado($mov),
            ]);
        }

        $clavesPeriodo = [];
        foreach ($movimientosMayor as $mov) {
            $clavesPeriodo[self::claveMovimientoMayor($mov)] = true;
        }
        foreach ($poolMatch as &$movPool) {
            $movPool['_en_periodo'] = isset($clavesPeriodo[self::claveMovimientoMayor($movPool)]);
        }
        unset($movPool);

        $filas = [];
        $coinciden = 0;
        $soloSicore = 0;
        $soloMayor = 0;

        foreach ($registrosSicore as $reg) {
            $importeSicore = round((float) ($reg['importe'] ?? 0), 2);
            $match = self::buscarMatchMayor($reg, $poolMatch, $importeSicore, $tolerancia);

            $importeMayor = $match !== null ? (float) ($match['importe_conciliacion'] ?? 0) : null;
            $diferencia = $match !== null
                ? round($importeSicore - $importeMayor, 2)
                : $importeSicore;
            $cuadra = $match !== null && abs($diferencia) <= $tolerancia;

            if ($cuadra) {
                $coinciden++;
            } else {
                $soloSicore++;
            }

            $filas[] = [
                'tipo' => $cuadra ? 'coincide' : 'solo_sicore',
                'fecha' => (string) ($reg['fecha_retencion'] ?? ''),
                'referencia_sicore' => self::referenciaSicore($reg),
                'proveedor' => trim((string) ($reg['razon_social'] ?? '')),
                'codigo_proveedor' => (string) ($reg['codigo_proveedor'] ?? ''),
                'nro_cert' => (int) ($reg['nro_cert'] ?? 0),
                'nro_comp' => (int) ($reg['nro_comp'] ?? 0),
                'importe_sicore' => $importeSicore,
                'importe_mayor' => $importeMayor,
                'mayor_fecha' => $match['fecha'] ?? null,
                'mayor_asiento' => $match['asiento_id'] ?? null,
                'mayor_detalle' => $match['detalle'] ?? null,
                'mayor_origen' => $match['origen'] ?? null,
                'diferencia' => $diferencia,
                'cuadra' => $cuadra,
            ];
        }

        foreach ($poolMatch as $mov) {
            if (! empty($mov['_usado']) || empty($mov['_en_periodo'])) {
                continue;
            }

            $importeMayor = (float) ($mov['importe_conciliacion'] ?? 0);
            if (abs($importeMayor) < 0.001) {
                continue;
            }

            $soloMayor++;
            $filas[] = [
                'tipo' => 'solo_mayor',
                'fecha' => (string) ($mov['fecha'] ?? ''),
                'referencia_sicore' => '',
                'proveedor' => '',
                'codigo_proveedor' => '',
                'nro_cert' => 0,
                'nro_comp' => 0,
                'importe_sicore' => null,
                'importe_mayor' => $importeMayor,
                'mayor_fecha' => $mov['fecha'] ?? null,
                'mayor_asiento' => $mov['asiento_id'] ?? null,
                'mayor_detalle' => $mov['detalle'] ?? null,
                'mayor_origen' => $mov['origen'] ?? null,
                'diferencia' => round(-$importeMayor, 2),
                'cuadra' => false,
            ];
        }

        usort($filas, static function (array $a, array $b): int {
            $tipoOrder = ['solo_sicore' => 0, 'solo_mayor' => 1, 'coincide' => 2];
            $ta = $tipoOrder[$a['tipo'] ?? ''] ?? 9;
            $tb = $tipoOrder[$b['tipo'] ?? ''] ?? 9;
            if ($ta !== $tb) {
                return $ta <=> $tb;
            }

            return [(string) ($a['fecha'] ?? ''), (string) ($a['referencia_sicore'] ?? '')]
                <=> [(string) ($b['fecha'] ?? ''), (string) ($b['referencia_sicore'] ?? '')];
        });

        $desglose = self::desgloseDesdeFilas($filas);

        return [
            'filas' => $filas,
            'resumen' => array_merge([
                'total_operaciones' => count($filas),
                'coinciden' => $coinciden,
                'solo_sicore' => $soloSicore,
                'solo_mayor' => $soloMayor,
                'cuenta_inversa' => $cuentaInversa,
            ], $desglose),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $filas
     * @return array<string, float>
     */
    public static function desgloseDesdeFilas(array $filas): array
    {
        $sumCoincidentesSicore = 0.0;
        $sumCoincidentesMayor = 0.0;
        $sumSoloMayor = 0.0;
        $sumSoloSicore = 0.0;

        foreach ($filas as $fila) {
            if (($fila['tipo'] ?? '') === 'coincide') {
                $sumCoincidentesSicore += (float) ($fila['importe_sicore'] ?? 0);
                $sumCoincidentesMayor += (float) ($fila['importe_mayor'] ?? 0);
            } elseif (($fila['tipo'] ?? '') === 'solo_mayor') {
                $sumSoloMayor += (float) ($fila['importe_mayor'] ?? 0);
            } elseif (($fila['tipo'] ?? '') === 'solo_sicore') {
                $sumSoloSicore += (float) ($fila['importe_sicore'] ?? 0);
            }
        }

        return [
            'sum_coincidentes_sicore' => round($sumCoincidentesSicore, 2),
            'sum_coincidentes_mayor' => round($sumCoincidentesMayor, 2),
            'sum_solo_mayor' => round($sumSoloMayor, 2),
            'sum_solo_sicore' => round($sumSoloSicore, 2),
        ];
    }

    /**
     * @param  array<string, mixed>  $resumenAuditoria
     * @return array<string, mixed>
     */
    public static function explicacionDiferencia(float $totalSicore, float $totalMayorNeto, array $resumenAuditoria): array
    {
        $dif = round($totalSicore - $totalMayorNeto, 2);
        $sumSoloMayor = (float) ($resumenAuditoria['sum_solo_mayor'] ?? 0);
        $sumSoloSicore = (float) ($resumenAuditoria['sum_solo_sicore'] ?? 0);
        $sumCoinS = (float) ($resumenAuditoria['sum_coincidentes_sicore'] ?? 0);

        $coincideConSoloMayor = abs($sumCoinS - $totalSicore) < 0.01
            && (int) ($resumenAuditoria['solo_mayor'] ?? 0) > 0
            && abs(round($dif + $sumSoloMayor, 2)) <= SicoreFormatoV8Support::tolerancia();

        return [
            'diferencia' => $dif,
            'coincidentes_cuadran_totales' => abs($sumCoinS - (float) ($resumenAuditoria['sum_coincidentes_mayor'] ?? 0)) < 0.01,
            'diferencia_explicada_por_solo_mayor' => $coincideConSoloMayor,
            'ajuste_solo_mayor' => round(-$sumSoloMayor, 2),
            'ajuste_solo_sicore' => round($sumSoloSicore, 2),
        ];
    }

    /**
     * Texto de auditoría: un solo "Cert. N" / "OP N" (saca duplicados del texto de referencia).
     *
     * @param  array<string, mixed>  $reg
     */
    private static function referenciaSicore(array $reg): string
    {
        $nroCert = (int) ($reg['nro_cert'] ?? 0);
        $nroComp = (int) ($reg['nro_comp'] ?? 0);
        $referencia = trim((string) ($reg['referencia'] ?? ''));

        if ($nroCert > 0) {
            $referencia = self::quitarMencionNumero($referencia, 'cert', $nroCert);
        }
        if ($nroComp > 0) {
            $referencia = self::quitarMencionNumero($referencia, 'op', $nroComp);
        }
        $referencia = trim(preg_replace('/(?:\s*—\s*)+/u', ' — ', $referencia) ?? $referencia);
        $referencia = trim($referencia, " \t\n\r\0\x0B-");
        $referencia = preg_replace('/^\s*—\s*|\s*—\s*$/u', '', $referencia) ?? $referencia;
        $referencia = trim($referencia);

        $partes = [];
        if ($nroCert > 0) {
            $partes[] = 'Cert. '.$nroCert;
        }
        if ($nroComp > 0) {
            $partes[] = 'OP '.$nroComp;
        }
        if ($referencia !== '') {
            $partes[] = $referencia;
        }

        return $partes !== [] ? implode(' — ', $partes) : 'SICORE';
    }

    private static function quitarMencionNumero(string $texto, string $etiqueta, int $numero): string
    {
        if ($texto === '' || $numero <= 0) {
            return $texto;
        }

        $n = preg_quote((string) $numero, '/');
        $patron = match ($etiqueta) {
            'cert' => '/(?:\s*—\s*)?\bcert\.?\s*'.$n.'\b/iu',
            'op' => '/(?:\s*—\s*)?\bOP\s*'.$n.'\b/iu',
            default => null,
        };
        if ($patron === null) {
            return $texto;
        }

        return trim((string) preg_replace($patron, '', $texto));
    }

    /**
     * True si el detalle del mayor menciona la OP/comprobante SICORE
     * (ej. "Pago: PASTORIZA AGOST #123066").
     */
    public static function detalleContieneNroComp(string $detalle, int $nroComp): bool
    {
        if ($nroComp <= 0 || $detalle === '') {
            return false;
        }

        $n = preg_quote((string) $nroComp, '/');

        return preg_match('/(?:#|\bOP\s*)'.$n.'\b/u', $detalle) === 1;
    }

    /**
     * @param  array<string, mixed>  $mov
     */
    private static function claveMovimientoMayor(array $mov): string
    {
        return implode('|', [
            (string) ($mov['fecha'] ?? ''),
            (string) (int) ($mov['asiento_id'] ?? 0),
            (string) ($mov['cuenta_codigo'] ?? ''),
            (string) round((float) ($mov['debe'] ?? 0), 2),
            (string) round((float) ($mov['haber'] ?? 0), 2),
            trim((string) ($mov['detalle'] ?? '')),
            (string) ($mov['origen'] ?? ''),
        ]);
    }

    /**
     * @param  array<string, mixed>  $reg
     * @param  list<array<string, mixed>>  $poolMayor
     * @return array<string, mixed>|null
     */
    private static function buscarMatchMayor(
        array $reg,
        array &$poolMayor,
        float $importeSicore,
        float $tolerancia,
    ): ?array {
        $fecha = (string) ($reg['fecha_retencion'] ?? '');
        $cert = (string) (int) ($reg['nro_cert'] ?? 0);
        $prov = ltrim(trim((string) ($reg['codigo_proveedor'] ?? '')), '0');
        $nroComp = (int) ($reg['nro_comp'] ?? 0);

        // 1) Mismo día + mismas claves (cert / proveedor / OP en detalle / importe solo).
        // 2) Si no hay match (típico AOP en retmov con fecha distinta al Debe del mayor),
        //    reintentar por importe + nº OP en el detalle, sin exigir misma fecha.
        $pasadas = [
            [
                'exigir_fecha' => true,
                'estrategias' => [
                    static fn (array $mov) => $cert !== '0' && str_contains((string) ($mov['detalle'] ?? ''), $cert),
                    static fn (array $mov) => $prov !== '' && str_contains((string) ($mov['detalle'] ?? ''), $prov),
                    static fn (array $mov) => self::detalleContieneNroComp((string) ($mov['detalle'] ?? ''), $nroComp),
                    static fn (): bool => true,
                ],
            ],
            [
                'exigir_fecha' => false,
                'estrategias' => [
                    static fn (array $mov) => self::detalleContieneNroComp((string) ($mov['detalle'] ?? ''), $nroComp),
                ],
            ],
        ];

        foreach ($pasadas as $pasada) {
            foreach ($pasada['estrategias'] as $filtroExtra) {
                foreach ($poolMayor as &$mov) {
                    if (! empty($mov['_usado'])) {
                        continue;
                    }
                    if ($pasada['exigir_fecha'] && (string) ($mov['fecha'] ?? '') !== $fecha) {
                        continue;
                    }
                    if (abs($importeSicore - (float) ($mov['importe_conciliacion'] ?? 0)) > $tolerancia) {
                        continue;
                    }
                    if (! $filtroExtra($mov)) {
                        continue;
                    }

                    $mov['_usado'] = true;

                    return $mov;
                }
                unset($mov);
            }
        }

        return null;
    }
}
