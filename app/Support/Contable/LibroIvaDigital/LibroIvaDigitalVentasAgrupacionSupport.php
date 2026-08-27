<?php

namespace App\Support\Contable\LibroIvaDigital;

/**
 * Agrupación de Facturas B en venta global diaria (RG 4597 campos 4-5 y 6-8).
 * Misma lógica que IVA Ventas {@see \App\Services\Ventas\IvaVentasReporteService::agruparFacturasBPorDia}.
 */
final class LibroIvaDigitalVentasAgrupacionSupport
{
    /**
     * @param  list<array{cabecera: array<string, mixed>, alicuotas: list<array<string, mixed>>}>  $registros
     * @return array{cabecera: array<string, mixed>, alicuotas: list<array<string, mixed>>}
     */
    public static function consolidarGrupoFacturaB(array $registros): array
    {
        if ($registros === []) {
            throw new \InvalidArgumentException('Grupo Factura B vacío.');
        }

        if (count($registros) === 1) {
            return self::aplicarCompradorVentaGlobalDiaria($registros[0]);
        }

        usort($registros, static fn (array $a, array $b): int => ((int) ($a['cabecera']['numero_comprobante'] ?? 0))
            <=> ((int) ($b['cabecera']['numero_comprobante'] ?? 0)));

        $base = $registros[0]['cabecera'];
        $numeroDesde = (int) ($base['numero_comprobante'] ?? 0);
        $numeroHasta = $numeroDesde;

        $cabecera = $base;
        $cabecera['importe_total'] = 0.0;
        $cabecera['no_integra_neto'] = 0.0;
        $cabecera['operaciones_exentas'] = 0.0;
        $cabecera['percepciones_nacionales'] = 0.0;
        $cabecera['percepciones_iibb'] = 0.0;
        $cabecera['impuestos_internos'] = 0.0;

        /** @var array<string, array{neto: float, iva: float, alicuota_iva: string}> $alicuotasPorCodigo */
        $alicuotasPorCodigo = [];

        foreach ($registros as $registro) {
            $c = $registro['cabecera'];
            $numero = (int) ($c['numero_comprobante'] ?? 0);
            $numeroDesde = min($numeroDesde, $numero);
            $numeroHasta = max($numeroHasta, $numero);

            $cabecera['importe_total'] += (float) ($c['importe_total'] ?? 0);
            $cabecera['no_integra_neto'] += (float) ($c['no_integra_neto'] ?? 0);
            $cabecera['operaciones_exentas'] += (float) ($c['operaciones_exentas'] ?? 0);
            $cabecera['percepciones_nacionales'] += (float) ($c['percepciones_nacionales'] ?? 0);
            $cabecera['percepciones_iibb'] += (float) ($c['percepciones_iibb'] ?? 0);
            $cabecera['impuestos_internos'] += (float) ($c['impuestos_internos'] ?? 0);

            foreach ($registro['alicuotas'] as $alicuota) {
                $codigo = (string) ($alicuota['alicuota_iva'] ?? '');
                if ($codigo === '') {
                    continue;
                }
                if (! isset($alicuotasPorCodigo[$codigo])) {
                    $alicuotasPorCodigo[$codigo] = [
                        'neto' => 0.0,
                        'iva' => 0.0,
                        'alicuota_iva' => $codigo,
                    ];
                }
                $alicuotasPorCodigo[$codigo]['neto'] += (float) ($alicuota['neto_gravado'] ?? 0);
                $alicuotasPorCodigo[$codigo]['iva'] += (float) ($alicuota['impuesto_liquidado'] ?? 0);
            }
        }

        $cabecera['numero_comprobante'] = $numeroDesde;
        $cabecera['numero_hasta'] = $numeroHasta;
        $cabecera['codigo_documento'] = '99';
        $cabecera['numero_identificacion'] = '0';
        $cabecera['nombre_comprador'] = '-VENTA GLOBAL DIARIA-';

        foreach (['importe_total', 'no_integra_neto', 'operaciones_exentas', 'percepciones_nacionales', 'percepciones_iibb', 'impuestos_internos'] as $campo) {
            $cabecera[$campo] = round((float) ($cabecera[$campo] ?? 0), 2);
        }

        $alicuotas = [];
        foreach ($alicuotasPorCodigo as $row) {
            $alicuotas[] = [
                'tipo_comprobante' => $cabecera['tipo_comprobante'],
                'punto_venta' => $cabecera['punto_venta'],
                'numero_comprobante' => $numeroDesde,
                'neto_gravado' => round($row['neto'], 2),
                'alicuota_iva' => $row['alicuota_iva'],
                'impuesto_liquidado' => round($row['iva'], 2),
            ];
        }

        usort($alicuotas, static fn (array $a, array $b): int => strcmp(
            (string) ($a['alicuota_iva'] ?? ''),
            (string) ($b['alicuota_iva'] ?? ''),
        ));

        $consolidado = LibroIvaDigitalVentasAlicuotaSupport::asegurarRegistro([
            'cabecera' => $cabecera,
            'alicuotas' => $alicuotas,
        ]);
        if (isset($registros[0]['iva_simple']) && is_array($registros[0]['iva_simple'])) {
            $consolidado['iva_simple'] = $registros[0]['iva_simple'];
        }

        return $consolidado;
    }

    /**
     * @param  array{cabecera: array<string, mixed>, alicuotas: list<array<string, mixed>>}  $registro
     * @return array{cabecera: array<string, mixed>, alicuotas: list<array<string, mixed>>}
     */
    private static function aplicarCompradorVentaGlobalDiaria(array $registro): array
    {
        $meta = $registro['iva_simple'] ?? null;
        $registro['cabecera']['codigo_documento'] = '99';
        $registro['cabecera']['numero_identificacion'] = '0';
        $registro['cabecera']['nombre_comprador'] = '-VENTA GLOBAL DIARIA-';

        $salida = LibroIvaDigitalVentasAlicuotaSupport::asegurarRegistro($registro);
        if (is_array($meta)) {
            $salida['iva_simple'] = $meta;
        }

        return $salida;
    }

    /**
     * @param  list<array{cabecera: array<string, mixed>, alicuotas: list<array<string, mixed>>}>  $registros
     */
    public static function compararRegistrosCabecera(array $a, array $b): int
    {
        $ca = $a['cabecera'];
        $cb = $b['cabecera'];

        foreach (['fecha', 'punto_venta', 'tipo_comprobante'] as $campo) {
            $va = (string) ($ca[$campo] ?? '');
            $vb = (string) ($cb[$campo] ?? '');
            if ($va !== $vb) {
                return strcmp($va, $vb);
            }
        }

        return ((int) ($ca['numero_comprobante'] ?? 0)) <=> ((int) ($cb['numero_comprobante'] ?? 0));
    }

    public static function claveGrupoFacturaB(array $cabecera): string
    {
        return implode('|', [
            (string) ($cabecera['fecha'] ?? ''),
            (string) ($cabecera['punto_venta'] ?? ''),
            (string) ($cabecera['tipo_comprobante'] ?? ''),
        ]);
    }
}
