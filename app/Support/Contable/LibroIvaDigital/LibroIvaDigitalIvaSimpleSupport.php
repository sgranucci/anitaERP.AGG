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
                'iva_credito' => 0.0,
                'iva_computable' => 0.0,
                'iva_restitucion' => 0.0,
            ];
        }

        if ($restitucion) {
            $acumulado[$concepto]['renglones_restitucion']++;
            $acumulado[$concepto]['iva_restitucion'] += (float) ($fila['iva'] ?? 0);
        } else {
            $acumulado[$concepto]['renglones_credito']++;
            $acumulado[$concepto]['iva_computable'] += (float) ($fila['iva_computable'] ?? $fila['iva'] ?? 0);
        }

        $acumulado[$concepto]['neto_gravado'] += (float) ($fila['neto'] ?? 0);
        $acumulado[$concepto]['iva_credito'] += (float) ($fila['iva'] ?? 0);
        $acumulado[$concepto]['neto_gravado'] = round($acumulado[$concepto]['neto_gravado'], 2);
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
                    'iva_debito' => 0.0,
                    'exento' => 0.0,
                    'iva_restitucion' => 0.0,
                ];
            }

            $esRestitucion = (bool) ($fila['restitucion'] ?? false);
            if ($esRestitucion) {
                $acumulado[$codigo]['renglones_restitucion']++;
                $acumulado[$codigo]['iva_restitucion'] += (float) ($fila['iva'] ?? 0);
            } else {
                $acumulado[$codigo]['renglones_debito']++;
            }

            $acumulado[$codigo]['neto_gravado'] += (float) ($fila['neto'] ?? 0);
            $acumulado[$codigo]['iva_debito'] += (float) ($fila['iva'] ?? 0);
            $acumulado[$codigo]['exento'] += (float) ($fila['exento'] ?? 0);
        }

        ksort($acumulado);

        return array_values(array_map(static function (array $row): array {
            $row['neto_gravado'] = round($row['neto_gravado'], 2);
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
