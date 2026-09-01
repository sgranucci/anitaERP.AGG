<?php

namespace App\Support\Contable\LibroIvaDigital;

/**
 * Alícuotas del archivo de ventas (RG 4597 Campo 19).
 *
 * En VENTAS, si no hay varias alícuotas se informa '1' (nunca 0), también cuando
 * la operación es toda exenta / no gravada. La alícuota 0% es código LID 0003 y
 * el código de operación del CBTE debe ser E/N (Campo 20). Factura C no informa alícuotas.
 */
final class LibroIvaDigitalVentasAlicuotaSupport
{
    /** Tipos C (no liquidan IVA) — cantidad de alícuotas = 0. */
    public const TIPOS_SIN_ALICUOTA = ['011', '012', '013', '014', '015', '016'];

    public static function codigoAlicuotaCero(): string
    {
        return LibroIvaDigitalMapeosSupport::codigoAlicuotaLid(0.0);
    }

    /**
     * @return array{neto: float, iva: float, tasa: float, codigo_lid: string}
     */
    public static function filaDesgloseCero(): array
    {
        return [
            'neto' => 0.0,
            'iva' => 0.0,
            'tasa' => 0.0,
            'codigo_lid' => self::codigoAlicuotaCero(),
        ];
    }

    /**
     * @param  list<array{neto: float, iva: float, tasa: float, codigo_lid: string}>  $filas
     * @return list<array{neto: float, iva: float, tasa: float, codigo_lid: string}>
     */
    public static function asegurarFilasDesglose(string $letra, array $filas): array
    {
        if (strtoupper($letra) === 'C' || $filas !== []) {
            return $filas;
        }

        return [self::filaDesgloseCero()];
    }

    /**
     * @param  array{cabecera: array<string, mixed>, alicuotas: list<array<string, mixed>>}  $registro
     * @return array{cabecera: array<string, mixed>, alicuotas: list<array<string, mixed>>}
     */
    public static function asegurarRegistro(array $registro): array
    {
        $cabecera = $registro['cabecera'];
        $tipo = str_pad((string) ($cabecera['tipo_comprobante'] ?? ''), 3, '0', STR_PAD_LEFT);
        if (in_array($tipo, self::TIPOS_SIN_ALICUOTA, true)) {
            $cabecera['cantidad_alicuotas'] = 0;
            $cabecera = LibroIvaDigitalIdentificacionSupport::aplicarACabecera($cabecera);
            $registro['cabecera'] = $cabecera;
            $registro['alicuotas'] = [];

            return LibroIvaDigitalComprasImportesSupport::cerrarRegistro(
                LibroIvaDigitalComprasImportesSupport::equilibrarTipoC($registro),
            );
        }

        $alicuotas = self::compactarAlicuotas($registro['alicuotas'] ?? []);
        if ($alicuotas === []) {
            $alicuotas = [[
                'tipo_comprobante' => $cabecera['tipo_comprobante'],
                'punto_venta' => $cabecera['punto_venta'],
                'numero_comprobante' => $cabecera['numero_comprobante'],
                'neto_gravado' => 0.0,
                'alicuota_iva' => self::codigoAlicuotaCero(),
                'impuesto_liquidado' => 0.0,
            ]];
        }

        $cabecera['cantidad_alicuotas'] = count($alicuotas);
        $cabecera['codigo_operacion'] = self::codigoOperacionDesdeAlicuotas($alicuotas, $cabecera);
        $cabecera = LibroIvaDigitalIdentificacionSupport::aplicarACabecera($cabecera);
        $registro['cabecera'] = $cabecera;
        $registro['alicuotas'] = $alicuotas;

        return LibroIvaDigitalComprasImportesSupport::cerrarRegistro($registro);
    }

    /**
     * @param  list<array<string, mixed>>  $alicuotas
     * @param  array<string, mixed>  $importes
     */
    public static function codigoOperacionDesdeAlicuotas(array $alicuotas, array $importes): string
    {
        if (self::tieneAlicuotaGravada($alicuotas)) {
            return ' ';
        }

        $exentas = (float) ($importes['operaciones_exentas'] ?? 0);
        $noGravado = (float) ($importes['no_gravado'] ?? $importes['no_integra_neto'] ?? 0);
        if (abs($noGravado) > 0.00001 && abs($exentas) < 0.00001) {
            return 'N';
        }

        return 'E';
    }

    /**
     * @param  list<array<string, mixed>>  $alicuotas
     */
    public static function tieneAlicuotaGravada(array $alicuotas): bool
    {
        $cero = self::codigoAlicuotaCero();
        foreach ($alicuotas as $row) {
            $tasa = (float) ($row['tasa'] ?? 0);
            $codigo = (string) ($row['codigo_lid'] ?? $row['alicuota_iva'] ?? '');
            if ($tasa > 0 || ($codigo !== '' && $codigo !== $cero)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Si ya hay alícuota gravada, no hace falta la línea 0% (el exento va en campo 12 del CBTE).
     *
     * @param  list<array<string, mixed>>  $alicuotas
     * @return list<array<string, mixed>>
     */
    public static function compactarAlicuotas(array $alicuotas): array
    {
        if (! self::tieneAlicuotaGravada($alicuotas)) {
            return $alicuotas;
        }

        $cero = self::codigoAlicuotaCero();
        $filtradas = [];
        foreach ($alicuotas as $row) {
            $codigo = (string) ($row['alicuota_iva'] ?? $row['codigo_lid'] ?? '');
            $neto = (float) ($row['neto_gravado'] ?? $row['neto'] ?? 0);
            $iva = (float) ($row['impuesto_liquidado'] ?? $row['iva'] ?? 0);
            if ($codigo === $cero && abs($neto) < 0.00001 && abs($iva) < 0.00001) {
                continue;
            }
            $filtradas[] = $row;
        }

        return $filtradas !== [] ? $filtradas : $alicuotas;
    }
}
