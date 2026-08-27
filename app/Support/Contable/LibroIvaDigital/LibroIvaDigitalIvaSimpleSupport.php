<?php

namespace App\Support\Contable\LibroIvaDigital;

use Illuminate\Support\Facades\DB;

/**
 * Armado y resumen de CSV IVA Simple (débito por actividad / crédito por concepto).
 */
final class LibroIvaDigitalIvaSimpleSupport
{
    public static function normalizarCodigoActividad(string $codigo): string
    {
        return str_pad(preg_replace('/\D+/', '', $codigo) ?: '0', 6, '0', STR_PAD_LEFT);
    }

    /**
     * @param  array<string, mixed>  $fila
     */
    public static function lineaDebitoFiscal(array $fila, bool $restitucion = false): string
    {
        $campos = [
            self::normalizarCodigoActividad((string) ($fila['actividad_codigo'] ?? '0')),
            (string) ($fila['tipo_operacion'] ?? '1'),
            (string) ($fila['tipo_sujeto'] ?? ''),
            (string) ($fila['alicuota_codigo'] ?? ''),
            LibroIvaDigitalMapeosSupport::importeCsvIvaSimple((float) ($fila['neto'] ?? 0)),
            LibroIvaDigitalMapeosSupport::importeCsvIvaSimple((float) ($fila['iva'] ?? 0)),
        ];

        if (! $restitucion) {
            $campos[] = LibroIvaDigitalMapeosSupport::importeCsvIvaSimple(
                (float) ($fila['iva_computable'] ?? $fila['iva'] ?? 0),
            );
            $campos[] = LibroIvaDigitalMapeosSupport::importeCsvIvaSimple((float) ($fila['exento'] ?? 0));
        }

        return implode(';', $campos).';';
    }

    /**
     * @param  array<string, mixed>  $fila
     */
    public static function lineaCreditoFiscal(array $fila, bool $restitucion = false): string
    {
        $campos = [
            (string) ($fila['concepto'] ?? '1'),
            (string) ($fila['alicuota_codigo'] ?? '5'),
            LibroIvaDigitalMapeosSupport::importeCsvIvaSimple((float) ($fila['neto'] ?? 0)),
            LibroIvaDigitalMapeosSupport::importeCsvIvaSimple((float) ($fila['iva'] ?? 0)),
        ];

        if (! $restitucion) {
            $campos[] = LibroIvaDigitalMapeosSupport::importeCsvIvaSimple(
                (float) ($fila['iva_computable'] ?? $fila['iva'] ?? 0),
            );
        }

        return implode(';', $campos).';';
    }

    public static function etiquetaConceptoCredito(int $concepto): string
    {
        return match ($concepto) {
            1 => 'Bienes',
            2 => 'Locaciones',
            3 => 'Servicios',
            4 => 'Bienes de uso',
            default => 'Concepto '.$concepto,
        };
    }

    /**
     * Agrupa neto + IVA de la misma alícuota/concepto (G e I van en el mismo renglón ARCA).
     *
     * @param  array<string, array<string, mixed>>  $acumulado
     * @param  array<string, mixed>  $fila
     */
    public static function acumularCredito(
        array &$acumulado,
        array $fila,
        bool $prorrateoGlobal = false,
        bool $restitucion = false,
    ): void {
        $neto = (float) ($fila['neto'] ?? 0);
        $iva = (float) ($fila['iva'] ?? 0);
        if ($neto <= 0 && $iva <= 0) {
            return;
        }

        $concepto = (int) ($fila['concepto_iva_simple'] ?? $fila['concepto'] ?? 1);
        $tasa = (float) ($fila['tasa'] ?? 0);
        $alicuota = isset($fila['alicuota_codigo'])
            ? (int) $fila['alicuota_codigo']
            : LibroIvaDigitalMapeosSupport::codigoAlicuotaIvaSimple($tasa);
        $key = $concepto.'|'.$alicuota;

        if (! isset($acumulado[$key])) {
            $acumulado[$key] = [
                'concepto' => $concepto,
                'alicuota_codigo' => $alicuota,
                'tasa' => $tasa,
                'neto' => 0.0,
                'iva' => 0.0,
                'iva_computable' => 0.0,
                'restitucion' => $restitucion,
            ];
        }

        $acumulado[$key]['neto'] += $neto;
        $acumulado[$key]['iva'] += $iva;
        if (! $prorrateoGlobal) {
            $acumulado[$key]['iva_computable'] += $iva;
        }
        if ($tasa > 0 && (float) ($acumulado[$key]['tasa'] ?? 0) <= 0) {
            $acumulado[$key]['tasa'] = $tasa;
        }
    }

    /**
     * @param  array<string, array<string, mixed>>  $acumulado
     * @return array{lineas: list<string>, detalle: list<array<string, mixed>>}
     */
    public static function lineasDesdeAcumuladoCredito(array $acumulado, bool $restitucion = false): array
    {
        ksort($acumulado);
        $lineas = [];
        $detalle = [];
        foreach ($acumulado as $fila) {
            $fila['neto'] = round((float) $fila['neto'], 2);
            $fila['iva'] = round((float) $fila['iva'], 2);
            $fila['iva_computable'] = round((float) $fila['iva_computable'], 2);
            if ($fila['neto'] <= 0 && $fila['iva'] <= 0) {
                continue;
            }
            $detalle[] = $fila;
            $lineas[] = self::lineaCreditoFiscal($fila, $restitucion);
        }

        return ['lineas' => $lineas, 'detalle' => $detalle];
    }

    /**
     * ARCA: ventas exentas/no gravadas del Libro van a tipo operación 3,
     * totalizando el campo operaciones_exentas del VENTAS_CBTE.
     *
     * @param  list<array{cabecera: array<string, mixed>, alicuotas?: list<array<string, mixed>>, iva_simple?: array<string, mixed>}>  $registros
     * @return array{
     *     detalle: list<array<string, mixed>>,
     *     detalle_restitucion: list<array<string, mixed>>
     * }
     */
    public static function debitoDesdeRegistrosLibro(array $registros): array
    {
        $acum = [];
        $acumRest = [];
        $acumExento = [];
        $acumExentoRest = [];

        foreach ($registros as $registro) {
            $meta = is_array($registro['iva_simple'] ?? null) ? $registro['iva_simple'] : [];
            $actividad = self::normalizarCodigoActividad((string) ($meta['actividad_codigo'] ?? '0'));
            $actividadNombre = (string) ($meta['actividad_nombre'] ?? '');
            $sujeto = (int) ($meta['tipo_sujeto'] ?? 3);
            $restitucion = (bool) ($meta['restitucion'] ?? false);
            $cabecera = is_array($registro['cabecera'] ?? null) ? $registro['cabecera'] : [];

            foreach ($registro['alicuotas'] ?? [] as $alicuota) {
                $neto = abs((float) ($alicuota['neto_gravado'] ?? $alicuota['neto'] ?? 0));
                $iva = abs((float) ($alicuota['impuesto_liquidado'] ?? $alicuota['iva'] ?? 0));
                if ($neto <= 0.0001 && $iva <= 0.0001) {
                    continue;
                }
                $codigoLid = (string) ($alicuota['alicuota_iva'] ?? $alicuota['codigo_lid'] ?? '');
                $alicuotaCodigo = isset($alicuota['alicuota_codigo'])
                    ? (int) $alicuota['alicuota_codigo']
                    : LibroIvaDigitalMapeosSupport::codigoAlicuotaIvaSimpleDesdeLid($codigoLid);
                $key = $actividad.'|'.$sujeto.'|'.$alicuotaCodigo;
                $destino = &$acum;
                if ($restitucion) {
                    $destino = &$acumRest;
                }
                if (! isset($destino[$key])) {
                    $destino[$key] = [
                        'actividad_codigo' => $actividad,
                        'actividad_nombre' => $actividadNombre,
                        'tipo_operacion' => '1',
                        'tipo_sujeto' => $sujeto,
                        'alicuota_codigo' => $alicuotaCodigo,
                        'neto' => 0.0,
                        'iva' => 0.0,
                        'iva_computable' => 0.0,
                        'exento' => 0.0,
                        'restitucion' => $restitucion,
                    ];
                }
                $destino[$key]['neto'] += $neto;
                $destino[$key]['iva'] += $iva;
                if (! $restitucion) {
                    $destino[$key]['iva_computable'] += $iva;
                }
                unset($destino);
            }

            $exento = abs((float) ($cabecera['operaciones_exentas'] ?? 0));
            if ($exento <= 0.0001) {
                continue;
            }
            $destinoEx = &$acumExento;
            if ($restitucion) {
                $destinoEx = &$acumExentoRest;
            }
            if (! isset($destinoEx[$actividad])) {
                $destinoEx[$actividad] = [
                    'actividad_codigo' => $actividad,
                    'actividad_nombre' => $actividadNombre,
                    'tipo_operacion' => $restitucion ? '2' : '3',
                    'tipo_sujeto' => null,
                    'alicuota_codigo' => null,
                    'neto' => 0.0,
                    'iva' => 0.0,
                    'iva_computable' => 0.0,
                    'exento' => 0.0,
                    'restitucion' => $restitucion,
                ];
            }
            $destinoEx[$actividad]['exento'] += $exento;
            unset($destinoEx);
        }

        return [
            'detalle' => self::redondearDetalleDebito(array_merge(array_values($acum), array_values($acumExento))),
            'detalle_restitucion' => self::redondearDetalleDebito(array_merge(array_values($acumRest), array_values($acumExentoRest))),
        ];
    }

    /**
     * Crédito: mismas alícuotas G+I que COMPRAS_ALICUOTAS.
     * Exento / no gravado / monotributo quedan en el Libro (el CSV de CF no tiene esos campos).
     *
     * @param  list<array{cabecera: array<string, mixed>, alicuotas?: list<array<string, mixed>>, iva_simple?: array<string, mixed>}>  $registros
     * @return array{
     *     detalle: list<array<string, mixed>>,
     *     detalle_restitucion: list<array<string, mixed>>,
     *     total_exento: float,
     *     total_no_integra: float,
     *     total_monotributo: float
     * }
     */
    public static function creditoDesdeRegistrosLibro(array $registros, bool $prorrateoGlobal = false): array
    {
        $acum = [];
        $acumRest = [];
        $totalExento = 0.0;
        $totalNoIntegra = 0.0;
        $totalMonotributo = 0.0;

        foreach ($registros as $registro) {
            $meta = is_array($registro['iva_simple'] ?? null) ? $registro['iva_simple'] : [];
            $cabecera = is_array($registro['cabecera'] ?? null) ? $registro['cabecera'] : [];
            $tipo = str_pad((string) ($cabecera['tipo_comprobante'] ?? ''), 3, '0', STR_PAD_LEFT);
            $restitucion = (bool) ($meta['restitucion'] ?? false)
                || LibroIvaDigitalMapeosSupport::esTipoNotaCredito($tipo);
            $esTipoC = in_array($tipo, LibroIvaDigitalVentasAlicuotaSupport::TIPOS_SIN_ALICUOTA, true);

            $exento = abs((float) ($cabecera['operaciones_exentas'] ?? 0));
            $noIntegra = abs((float) ($cabecera['no_integra_neto'] ?? 0));
            if ($esTipoC) {
                $montoC = abs((float) ($cabecera['importe_total'] ?? 0));
                $totalMonotributo += $restitucion ? -$montoC : $montoC;
            } elseif ($restitucion) {
                $totalExento -= $exento;
                $totalNoIntegra -= $noIntegra;
            } else {
                $totalExento += $exento;
                $totalNoIntegra += $noIntegra;
            }

            foreach ($registro['alicuotas'] ?? [] as $alicuota) {
                $neto = abs((float) ($alicuota['neto_gravado'] ?? $alicuota['neto'] ?? 0));
                $iva = abs((float) ($alicuota['impuesto_liquidado'] ?? $alicuota['iva'] ?? 0));
                if ($neto <= 0.0001 && $iva <= 0.0001) {
                    continue;
                }
                $fila = [
                    'concepto' => (int) ($alicuota['concepto_iva_simple'] ?? $alicuota['concepto'] ?? 1),
                    'alicuota_codigo' => isset($alicuota['alicuota_codigo'])
                        ? (int) $alicuota['alicuota_codigo']
                        : LibroIvaDigitalMapeosSupport::codigoAlicuotaIvaSimpleDesdeLid(
                            (string) ($alicuota['alicuota_iva'] ?? $alicuota['codigo_lid'] ?? ''),
                        ),
                    'neto' => $neto,
                    'iva' => $iva,
                ];
                if ($restitucion) {
                    self::acumularCredito($acumRest, $fila, $prorrateoGlobal, true);
                } else {
                    self::acumularCredito($acum, $fila, $prorrateoGlobal, false);
                }
            }
        }

        $credito = self::lineasDesdeAcumuladoCredito($acum, false);
        $restitucion = self::lineasDesdeAcumuladoCredito($acumRest, true);
        $netoFacturas = round(array_sum(array_column($credito['detalle'], 'neto')), 2);
        $ivaFacturas = round(array_sum(array_column($credito['detalle'], 'iva')), 2);
        $netoNc = round(array_sum(array_column($restitucion['detalle'], 'neto')), 2);
        $ivaNc = round(array_sum(array_column($restitucion['detalle'], 'iva')), 2);

        return [
            'detalle' => $credito['detalle'],
            'detalle_restitucion' => $restitucion['detalle'],
            'total_exento' => round($totalExento, 2),
            'total_no_integra' => round($totalNoIntegra, 2),
            'total_monotributo' => round($totalMonotributo, 2),
            'total_neto_facturas' => $netoFacturas,
            'total_neto_nc' => $netoNc,
            'total_neto_portal' => round($netoFacturas - $netoNc, 2),
            'total_iva_facturas' => $ivaFacturas,
            'total_iva_nc' => $ivaNc,
            'total_iva_portal' => round($ivaFacturas - $ivaNc, 2),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $detalle
     * @return list<array<string, mixed>>
     */
    private static function redondearDetalleDebito(array $detalle): array
    {
        return array_values(array_map(static function (array $fila): array {
            $fila['neto'] = round((float) ($fila['neto'] ?? 0), 2);
            $fila['iva'] = round((float) ($fila['iva'] ?? 0), 2);
            $fila['iva_computable'] = round((float) ($fila['iva_computable'] ?? $fila['iva'] ?? 0), 2);
            $fila['exento'] = round((float) ($fila['exento'] ?? 0), 2);

            return $fila;
        }, $detalle));
    }

    /**
     * @param  list<array<string, mixed>>  $detalleCredito
     * @param  list<array<string, mixed>>  $detalleRestitucion
     * @return list<array<string, mixed>>
     */
    public static function resumenPorConcepto(array $detalleCredito, array $detalleRestitucion): array
    {
        /** @var array<int, array<string, mixed>> $acumulado */
        $acumulado = [];

        foreach ($detalleCredito as $fila) {
            self::sumarResumenConcepto($acumulado, $fila, false);
        }
        foreach ($detalleRestitucion as $fila) {
            self::sumarResumenConcepto($acumulado, $fila, true);
        }

        ksort($acumulado);

        return array_values($acumulado);
    }

    /**
     * @param  array<int, array<string, mixed>>  $acumulado
     * @param  array<string, mixed>  $fila
     */
    private static function sumarResumenConcepto(array &$acumulado, array $fila, bool $restitucion): void
    {
        $concepto = (int) ($fila['concepto'] ?? 1);
        if (! isset($acumulado[$concepto])) {
            $acumulado[$concepto] = [
                'concepto' => $concepto,
                'concepto_nombre' => self::etiquetaConceptoCredito($concepto),
                'renglones_credito' => 0,
                'renglones_restitucion' => 0,
                'neto_gravado' => 0.0,
                'neto_restitucion' => 0.0,
                'iva_credito' => 0.0,
                'iva_computable' => 0.0,
                'iva_restitucion' => 0.0,
            ];
        }

        if ($restitucion) {
            $acumulado[$concepto]['renglones_restitucion']++;
            $acumulado[$concepto]['neto_restitucion'] += (float) ($fila['neto'] ?? 0);
            $acumulado[$concepto]['iva_restitucion'] += (float) ($fila['iva'] ?? 0);
        } else {
            $acumulado[$concepto]['renglones_credito']++;
            $acumulado[$concepto]['neto_gravado'] += (float) ($fila['neto'] ?? 0);
            $acumulado[$concepto]['iva_credito'] += (float) ($fila['iva'] ?? 0);
            $acumulado[$concepto]['iva_computable'] += (float) ($fila['iva_computable'] ?? $fila['iva'] ?? 0);
        }
        $acumulado[$concepto]['neto_gravado'] = round($acumulado[$concepto]['neto_gravado'], 2);
        $acumulado[$concepto]['neto_restitucion'] = round($acumulado[$concepto]['neto_restitucion'], 2);
        $acumulado[$concepto]['iva_credito'] = round($acumulado[$concepto]['iva_credito'], 2);
        $acumulado[$concepto]['iva_computable'] = round($acumulado[$concepto]['iva_computable'], 2);
        $acumulado[$concepto]['iva_restitucion'] = round($acumulado[$concepto]['iva_restitucion'], 2);
    }

    public static function etiquetaTipoOperacion(string $tipo): string
    {
        return match ($tipo) {
            '1' => 'Gravado',
            '3' => 'Exento / no gravado',
            default => 'Tipo '.$tipo,
        };
    }

    public static function etiquetaTipoSujeto(?int $tipo): string
    {
        return match ($tipo) {
            1 => 'Responsable inscripto',
            2 => 'Monotributo',
            3 => 'Consumidor final / exento',
            default => $tipo === null ? '—' : 'Tipo '.$tipo,
        };
    }

    /**
     * @param  list<array<string, mixed>>  $detalleDebito
     * @param  list<array<string, mixed>>  $detalleRestitucionDebito
     * @return list<array<string, mixed>>
     */
    public static function resumenPorActividad(array $detalleDebito, array $detalleRestitucionDebito): array
    {
        $nombres = self::mapaNombresActividad(
            collect($detalleDebito)
                ->merge($detalleRestitucionDebito)
                ->pluck('actividad_codigo')
                ->map(fn ($codigo) => self::normalizarCodigoActividad((string) $codigo))
                ->unique()
                ->all(),
        );

        /** @var array<string, array<string, mixed>> $acumulado */
        $acumulado = [];

        foreach ([...$detalleDebito, ...$detalleRestitucionDebito] as $fila) {
            $codigo = self::normalizarCodigoActividad((string) ($fila['actividad_codigo'] ?? '0'));
            if (! isset($acumulado[$codigo])) {
                $acumulado[$codigo] = [
                    'actividad_codigo' => $codigo,
                    'actividad_nombre' => $nombres[$codigo] ?? ($codigo === '000000' ? 'Sin actividad ARCA' : ''),
                    'renglones_debito' => 0,
                    'renglones_restitucion' => 0,
                    'neto_gravado' => 0.0,
                    'neto_restitucion' => 0.0,
                    'iva_debito' => 0.0,
                    'exento' => 0.0,
                    'iva_restitucion' => 0.0,
                ];
            }

            $esRestitucion = (bool) ($fila['restitucion'] ?? false);
            if ($esRestitucion) {
                $acumulado[$codigo]['renglones_restitucion']++;
                $acumulado[$codigo]['neto_restitucion'] += (float) ($fila['neto'] ?? 0);
                $acumulado[$codigo]['iva_restitucion'] += (float) ($fila['iva'] ?? 0);
            } else {
                $acumulado[$codigo]['renglones_debito']++;
                $acumulado[$codigo]['neto_gravado'] += (float) ($fila['neto'] ?? 0);
                $acumulado[$codigo]['iva_debito'] += (float) ($fila['iva'] ?? 0);
                $acumulado[$codigo]['exento'] += (float) ($fila['exento'] ?? 0);
            }
        }

        ksort($acumulado);

        return array_values(array_map(static function (array $row): array {
            $row['neto_gravado'] = round($row['neto_gravado'], 2);
            $row['neto_restitucion'] = round($row['neto_restitucion'], 2);
            $row['iva_debito'] = round($row['iva_debito'], 2);
            $row['exento'] = round($row['exento'], 2);
            $row['iva_restitucion'] = round($row['iva_restitucion'], 2);

            return $row;
        }, $acumulado));
    }

    /**
     * @param  list<string>  $codigos
     * @return array<string, string>
     */
    private static function mapaNombresActividad(array $codigos): array
    {
        if ($codigos === []) {
            return [];
        }

        $mapa = [];
        $filas = DB::table('actividad_arca')
            ->select(['codigoarca', 'nombre'])
            ->get();

        foreach ($filas as $fila) {
            $codigo = self::normalizarCodigoActividad((string) $fila->codigoarca);
            if (in_array($codigo, $codigos, true)) {
                $mapa[$codigo] = (string) $fila->nombre;
            }
        }

        return $mapa;
    }
}
