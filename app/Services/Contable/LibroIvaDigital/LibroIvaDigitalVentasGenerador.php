<?php

namespace App\Services\Contable\LibroIvaDigital;

use App\Models\Ventas\Venta;
use App\Support\Contable\LibroIvaDigital\LibroIvaDigitalFormatoSupport;
use App\Support\Contable\LibroIvaDigital\LibroIvaDigitalMapeosSupport;
use App\Support\Contable\LibroIvaDigital\LibroIvaDigitalVentasAgrupacionSupport;
use App\Support\Contable\LibroIvaDigital\LibroIvaDigitalVentasAlicuotaSupport;
use App\Support\Contable\LibroIvaDigital\LibroIvaDigitalVentasConsumidorFinalSupport;
use App\Support\Contable\LibroIvaDigital\LibroIvaDigitalVentasPeriodoSupport;
use App\Support\Database\SqlDialectSupport;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LibroIvaDigitalVentasGenerador
{
    private const CHUNK_SIZE = 500;

    /**
     * @return array{
     *     ventas_cbte: string,
     *     ventas_alicuotas: string,
     *     resumen: array<string, int|float>
     * }
     */
    /**
     * @param  array{por_fecha_jornada?: bool}  $opciones
     */
    public function generar(int $empresaId, int $anio, int $mes, array $opciones = []): array
    {
        $desde = sprintf('%04d-%02d-01', $anio, $mes);
        $hasta = date('Y-m-t', strtotime($desde));
        $porFechaJornada = (bool) ($opciones['por_fecha_jornada'] ?? false);

        $contenidoCbte = '';
        $contenidoAlicuotas = '';
        $totalImporte = 0.0;
        $totalIva = 0.0;
        $conteoVentas = 0;
        $conteoRegistros = 0;
        $conteoConAlicuotas = 0;
        $conteoAlicuotas = 0;
        $conteoRmv = 0;

        $conteoVentasBIndividuales = 0;

        /** @var array<string, list<array{cabecera: array<string, mixed>, alicuotas: list<array<string, mixed>>}>> $gruposFacturaB */
        $gruposFacturaB = [];
        /** @var list<array{cabecera: array<string, mixed>, alicuotas: list<array<string, mixed>>}> $registrosIndividuales */
        $registrosIndividuales = [];

        $this->queryVentas($empresaId, $desde, $hasta, $porFechaJornada)
            ->with($this->relacionesVentas())
            ->orderByRaw(SqlDialectSupport::fecha(
                LibroIvaDigitalVentasPeriodoSupport::expresionFechaSql($porFechaJornada),
            ))
            ->orderBy('venta.puntoventa_id')
            ->orderBy('venta.tipotransaccion_id')
            ->orderBy('venta.numerocomprobante')
            ->lazy(self::CHUNK_SIZE)
            ->each(function (Venta $venta) use (
                $porFechaJornada,
                &$gruposFacturaB,
                &$registrosIndividuales,
                &$totalImporte,
                &$conteoVentas,
                &$conteoVentasBIndividuales,
                &$conteoRmv,
            ): void {
                $registro = $this->armarRegistroVenta($venta, $porFechaJornada);
                if ($registro === null) {
                    return;
                }

                $totalImporte += abs((float) $venta->total);
                $conteoVentas++;
                $letra = LibroIvaDigitalMapeosSupport::letraDesdeCodigoVenta((string) $venta->codigo);
                $esRmv = LibroIvaDigitalMapeosSupport::esRmv(
                    (string) ($venta->tipotransacciones->abreviatura ?? ''),
                );
                if ($esRmv) {
                    $conteoRmv++;
                }
                if ($letra === 'B' && ! $esRmv) {
                    $importeRegistro = abs((float) ($registro['cabecera']['importe_total'] ?? 0));
                    if (LibroIvaDigitalVentasConsumidorFinalSupport::permiteAgrupacionGlobalDiaria($venta, $importeRegistro)) {
                        $clave = LibroIvaDigitalVentasAgrupacionSupport::claveGrupoFacturaB($registro['cabecera']);
                        $gruposFacturaB[$clave][] = $registro;

                        return;
                    }

                    $conteoVentasBIndividuales++;
                }

                $registrosIndividuales[] = $registro;
            });

        $registrosFinales = $registrosIndividuales;
        foreach ($gruposFacturaB as $grupo) {
            $registrosFinales[] = LibroIvaDigitalVentasAgrupacionSupport::consolidarGrupoFacturaB($grupo);
        }

        usort(
            $registrosFinales,
            static fn (array $a, array $b): int => LibroIvaDigitalVentasAgrupacionSupport::compararRegistrosCabecera($a, $b),
        );

        foreach ($registrosFinales as $registro) {
            $contenidoCbte .= LibroIvaDigitalFormatoSupport::registroVentasCbte($registro['cabecera'])."\r\n";
            if ($registro['alicuotas'] !== []) {
                $conteoConAlicuotas++;
            }
            foreach ($registro['alicuotas'] as $alicuota) {
                $contenidoAlicuotas .= LibroIvaDigitalFormatoSupport::registroVentasAlicuota($alicuota)."\r\n";
                $totalIva += abs((float) ($alicuota['impuesto_liquidado'] ?? 0));
                $conteoAlicuotas++;
            }
            $conteoRegistros++;
        }

        return [
            'ventas_cbte' => rtrim($contenidoCbte, "\r\n"),
            'ventas_alicuotas' => rtrim($contenidoAlicuotas, "\r\n"),
            'resumen' => [
                'comprobantes' => $conteoRegistros,
                'ventas_emitidas' => $conteoVentas,
                'ventas_b_individuales' => $conteoVentasBIndividuales,
                'comprobantes_con_alicuotas' => $conteoConAlicuotas,
                'alicuotas' => $conteoAlicuotas,
                'importe_total' => round($totalImporte, 2),
                'total_iva' => round($totalIva, 2),
                'ventas_rmv' => $conteoRmv,
                'por_fecha_jornada' => $porFechaJornada,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function relacionesVentas(): array
    {
        return [
            'venta_impuestos' => static function (HasMany $query): void {
                $query->select('id', 'venta_id', 'concepto', 'importe', 'tasa');
            },
            'tipotransacciones:id,codigo,signo,abreviatura',
            'puntoventas:id,codigo',
            'condicionivas:id,nombre,codigoexterno',
            'clientes:id,nombre,numerodocumento,condicioniva_id,tipodocumento_id',
            'clientes.tipodocumentos:id,codigoexterno',
            'clientes.condicionivas:id,nombre,codigoexterno',
            'monedas:id,codigo,nombre',
        ];
    }

    private function queryVentas(int $empresaId, string $desde, string $hasta, bool $porFechaJornada): Builder
    {
        $query = Venta::query()
            ->select('venta.*')
            ->join('puntoventa as pv_lid', 'pv_lid.id', '=', 'venta.puntoventa_id')
            ->where('pv_lid.empresa_id', $empresaId)
            ->whereNull('venta.deleted_at');

        LibroIvaDigitalVentasPeriodoSupport::aplicarFiltroFecha($query, $desde, $hasta, $porFechaJornada);
        LibroIvaDigitalVentasPeriodoSupport::aplicarFiltroCaeORmv($query);

        return $query;
    }

    /**
     * @return array{cabecera: array<string, mixed>, alicuotas: list<array<string, mixed>>}|null
     */
    private function armarRegistroVenta(Venta $venta, bool $porFechaJornada): ?array
    {
        // IZV / FBI / FSL internos. RMV sí informa Anita (p-rg3685.c: tipo 6, sucursal ≥ 1000).
        $abrev = strtoupper(trim((string) ($venta->tipotransacciones->abreviatura ?? '')));
        if (in_array($abrev, ['IZV', 'FBI', 'FSL'], true)) {
            return null;
        }

        $esRmv = LibroIvaDigitalMapeosSupport::esRmv($abrev);
        $letra = LibroIvaDigitalMapeosSupport::letraDesdeCodigoVenta((string) $venta->codigo);
        $codigoBase = (string) ($venta->tipotransacciones->codigo ?? '001');
        $tipoComprobante = LibroIvaDigitalMapeosSupport::tipoComprobanteVentas($codigoBase, $letra, $abrev);
        $puntoVenta = (int) ($venta->puntoventas->codigo ?? 0);
        $numero = (int) $venta->numerocomprobante;

        $totales = $this->desglosarImpuestos($venta, $esRmv ? 'B' : $letra);
        $comprador = $esRmv
            ? LibroIvaDigitalMapeosSupport::compradorRmv((string) ($venta->nombre ?? ''))
            : LibroIvaDigitalVentasConsumidorFinalSupport::resolverComprador(
                $venta,
                (float) $totales['importe_total'],
            );

        $codigoOperacion = $letra === 'C'
            ? ' '
            : LibroIvaDigitalVentasAlicuotaSupport::codigoOperacionDesdeAlicuotas(
                $totales['alicuotas'],
                $totales,
            );

        $signoRaw = (int) ($venta->tipotransacciones?->getRawOriginal('signo') ?? 1);
        $signo = $signoRaw < 0 ? -1 : 1;
        $codigoMoneda = LibroIvaDigitalMapeosSupport::codigoMonedaAfip(
            $venta->monedas->codigo ?? null,
            $venta->monedas->nombre ?? null,
        );

        $cabecera = [
            'fecha' => LibroIvaDigitalVentasPeriodoSupport::fechaDocumento($venta, $porFechaJornada),
            'tipo_comprobante' => $tipoComprobante,
            'punto_venta' => $puntoVenta,
            'numero_comprobante' => $numero,
            'numero_hasta' => $numero,
            'codigo_documento' => $comprador['codigo_documento'],
            'numero_identificacion' => $comprador['numero_identificacion'],
            'nombre_comprador' => $comprador['nombre'],
            'importe_total' => $signo * $totales['importe_total'],
            'no_integra_neto' => $signo * $totales['no_integra_neto'],
            'percepcion_no_categorizados' => 0,
            'operaciones_exentas' => $signo * $totales['operaciones_exentas'],
            'percepciones_nacionales' => $signo * $totales['percepciones_nacionales'],
            'percepciones_iibb' => $signo * $totales['percepciones_iibb'],
            'percepciones_municipales' => 0,
            'impuestos_internos' => $signo * $totales['impuestos_internos'],
            'codigo_moneda' => $codigoMoneda,
            'tipo_cambio' => LibroIvaDigitalMapeosSupport::tipoCambioArca(
                $codigoMoneda,
                (float) ($venta->cotizacion ?: 1),
            ),
            'cantidad_alicuotas' => $totales['cantidad_alicuotas'],
            'codigo_operacion' => $codigoOperacion,
            'otros_tributos' => 0,
            'fecha_vencimiento' => '00000000',
        ];

        $alicuotas = [];
        foreach ($totales['alicuotas'] as $row) {
            $alicuotas[] = [
                'tipo_comprobante' => $tipoComprobante,
                'punto_venta' => $puntoVenta,
                'numero_comprobante' => $numero,
                'neto_gravado' => $signo * $row['neto'],
                'alicuota_iva' => $row['codigo_lid'],
                'impuesto_liquidado' => $signo * $row['iva'],
            ];
        }

        return LibroIvaDigitalVentasAlicuotaSupport::asegurarRegistro([
            'cabecera' => $cabecera,
            'alicuotas' => $alicuotas,
        ]);
    }

    /**
     * @return array{
     *     importe_total: float,
     *     no_integra_neto: float,
     *     no_gravado: float,
     *     operaciones_exentas: float,
     *     percepciones_nacionales: float,
     *     percepciones_iibb: float,
     *     impuestos_internos: float,
     *     cantidad_alicuotas: int,
     *     alicuotas: list<array{neto: float, iva: float, codigo_lid: string, tasa: float}>
     * }
     */
    private function desglosarImpuestos(Venta $venta, string $letra): array
    {
        $importeTotal = 0.0;
        $noIntegra = 0.0;
        $noGravado = 0.0;
        $exento = 0.0;
        $percNac = 0.0;
        $percIibb = 0.0;
        $impInterno = 0.0;
        $alicuotas = [];

        foreach ($venta->venta_impuestos as $imp) {
            $concepto = (string) $imp->concepto;
            $importe = abs((float) $imp->importe);

            if (stripos($concepto, 'Total') === 0) {
                $importeTotal = $importe;
                continue;
            }
            if (stripos($concepto, 'Subtotal') === 0) {
                continue;
            }
            if (stripos($concepto, 'No Gravado') !== false) {
                $noGravado += $importe;
                continue;
            }
            if (stripos($concepto, 'Exento') !== false) {
                $exento += $importe;
                continue;
            }
            if (stripos($concepto, 'Impuesto Interno') !== false) {
                $impInterno += $importe;
                continue;
            }
            if (stripos($concepto, 'Percepcion IVA') !== false || stripos($concepto, 'Perc. IVA') !== false) {
                $percNac += $importe;
                continue;
            }
            if (stripos($concepto, 'Perc.') !== false || stripos($concepto, 'Percepcion IIBB') !== false) {
                $percIibb += $importe;
                continue;
            }
            if (stripos($concepto, 'Gravado al') !== false) {
                $tasa = round((float) $imp->tasa, 3);
                $alicuotas[$tasa]['neto'] = ($alicuotas[$tasa]['neto'] ?? 0) + $importe;
                $alicuotas[$tasa]['tasa'] = $tasa;
                continue;
            }
            if (stripos($concepto, 'Iva ') !== false || stripos($concepto, 'IVA') === 0) {
                $tasa = round((float) $imp->tasa, 3);
                $alicuotas[$tasa]['iva'] = ($alicuotas[$tasa]['iva'] ?? 0) + $importe;
                $alicuotas[$tasa]['tasa'] = $tasa;
            }
        }

        $importeDocumento = abs((float) $venta->total);
        if ($importeDocumento > 0) {
            $importeTotal = $importeDocumento;
        }

        $filasAlicuota = [];
        foreach ($alicuotas as $tasa => $row) {
            $neto = (float) ($row['neto'] ?? 0);
            $iva = (float) ($row['iva'] ?? 0);
            if ($neto <= 0 && $iva <= 0) {
                continue;
            }
            if ($iva <= 0 && $neto > 0 && $tasa > 0) {
                $iva = round($neto * ((float) $tasa / 100), 2);
            }
            $filasAlicuota[] = [
                'neto' => $neto,
                'iva' => $iva,
                'tasa' => (float) $tasa,
                'codigo_lid' => LibroIvaDigitalMapeosSupport::codigoAlicuotaLid((float) $tasa),
            ];
        }

        if ($letra === 'B' && $filasAlicuota === []) {
            $filasAlicuota = $this->inferirAlicuotasFacturaB(
                $importeTotal,
                $exento,
                $noGravado,
                $impInterno,
                $percNac,
                $percIibb,
            );
        }

        $filasAlicuota = LibroIvaDigitalVentasAlicuotaSupport::asegurarFilasDesglose($letra, $filasAlicuota);
        $cantidadAlicuotas = $letra === 'C' ? 0 : count($filasAlicuota);
        $filasArchivo = $letra === 'C' ? [] : $filasAlicuota;

        return [
            'importe_total' => $importeTotal,
            'no_integra_neto' => $noIntegra,
            'no_gravado' => $noGravado,
            'operaciones_exentas' => $exento + $noGravado,
            'percepciones_nacionales' => $percNac,
            'percepciones_iibb' => $percIibb,
            'impuestos_internos' => $impInterno,
            'cantidad_alicuotas' => $cantidadAlicuotas,
            'alicuotas' => $filasArchivo,
        ];
    }

    /**
     * @return list<array{neto: float, iva: float, tasa: float, codigo_lid: string}>
     */
    private function inferirAlicuotasFacturaB(
        float $importeTotal,
        float $exento,
        float $noGravado,
        float $impInterno,
        float $percNac,
        float $percIibb,
    ): array {
        $baseGravable = round(
            $importeTotal - $exento - $noGravado - $impInterno - $percNac - $percIibb,
            2,
        );
        if ($baseGravable <= 0.01) {
            return [];
        }

        $tasa = 21.0;
        $neto = round($baseGravable / (1 + ($tasa / 100)), 2);
        $iva = round($baseGravable - $neto, 2);

        return [[
            'neto' => $neto,
            'iva' => $iva,
            'tasa' => $tasa,
            'codigo_lid' => LibroIvaDigitalMapeosSupport::codigoAlicuotaLid($tasa),
        ]];
    }
}
