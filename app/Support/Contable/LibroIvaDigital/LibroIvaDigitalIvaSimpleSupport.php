<?php

namespace App\Support\Contable\LibroIvaDigital;

use Illuminate\Support\Facades\DB;

/**
 * Armado y resumen de CSV IVA Simple (débito fiscal por actividad ARCA).
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
