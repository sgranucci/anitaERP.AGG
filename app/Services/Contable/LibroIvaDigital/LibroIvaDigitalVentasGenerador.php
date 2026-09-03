<?php

namespace App\Services\Contable\LibroIvaDigital;

use App\Models\Ventas\Venta;
use App\Support\Configuracion\PercepcionNoCategorizadoSupport;
use App\Support\Contable\LibroIvaDigital\LibroIvaDigitalFormatoSupport;
use App\Support\Contable\LibroIvaDigital\LibroIvaDigitalIvaSimpleSupport;
use App\Support\Contable\LibroIvaDigital\LibroIvaDigitalMapeosSupport;
use App\Support\Contable\LibroIvaDigital\LibroIvaDigitalVentasAgrupacionSupport;
use App\Support\Contable\LibroIvaDigital\LibroIvaDigitalVentasAlicuotaSupport;
use App\Support\Contable\LibroIvaDigital\LibroIvaDigitalVentasConsumidorFinalSupport;
use App\Support\Contable\LibroIvaDigital\LibroIvaDigitalVentasFslAnitaArmadoSupport;
use App\Support\Contable\LibroIvaDigital\LibroIvaDigitalVentasFslAnitaBridgeReader;
use App\Support\Contable\LibroIvaDigital\LibroIvaDigitalVentasPeriodoSupport;
use App\Support\Contable\CierreRendicionMaquinaConfigSupport;
use App\Support\Ventas\IvaVentas\IvaVentasDesgloseSupport;
use App\Support\Database\SqlDialectSupport;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LibroIvaDigitalVentasGenerador
{
    private const CHUNK_SIZE = 500;

    public function __construct(
        private readonly LibroIvaDigitalVentasFslAnitaBridgeReader $fslAnitaBridgeReader,
    ) {
    }

    /**
     * @param  array{por_fecha_jornada?: bool, completar_fsl_anita?: bool}  $opciones
     * @return array{
     *     ventas_cbte: string,
     *     ventas_alicuotas: string,
     *     resumen: array<string, int|float>
     * }
     */
    public function generar(int $empresaId, int $anio, int $mes, array $opciones = []): array
    {
        $desde = sprintf('%04d-%02d-01', $anio, $mes);
        $hasta = date('Y-m-t', strtotime($desde));
        $porFechaJornada = (bool) ($opciones['por_fecha_jornada'] ?? false);
        $completarFslAnita = (bool) ($opciones['completar_fsl_anita'] ?? true);

        $contenidoCbte = '';
        $contenidoAlicuotas = '';
        $totalImporte = 0.0;
        $totalIva = 0.0;
        $totalExento = 0.0;
        $conteoVentas = 0;
        $conteoRegistros = 0;
        $conteoConAlicuotas = 0;
        $conteoAlicuotas = 0;
        $conteoRmv = 0;
        $conteoFbiFsl = 0;
        $conteoFslAnita = 0;

        $conteoVentasBIndividuales = 0;

        /** @var array<string, true> $clavesErpFsl */
        $clavesErpFsl = [];

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
                &$conteoFbiFsl,
                &$clavesErpFsl,
            ): void {
                $registro = $this->armarRegistroVenta($venta, $porFechaJornada);
                if ($registro === null) {
                    return;
                }

                $totalImporte += abs((float) $venta->total);
                $conteoVentas++;
                $letra = LibroIvaDigitalMapeosSupport::letraDesdeCodigoVenta((string) $venta->codigo);
                $abrev = strtoupper(trim((string) ($venta->tipotransacciones->abreviatura ?? '')));
                $esRmv = LibroIvaDigitalMapeosSupport::esRmv($abrev);
                $esFbiFsl = LibroIvaDigitalMapeosSupport::esFbiOFsl($abrev);
                $esSinCaeInformable = LibroIvaDigitalMapeosSupport::esSinCaeInformable($abrev);
                if ($esRmv) {
                    $conteoRmv++;
                }
                if ($esFbiFsl) {
                    $conteoFbiFsl++;
                }
                if ($abrev === 'FSL') {
                    $pv = (int) ($venta->puntoventas->codigo ?? 0);
                    $clavesErpFsl[LibroIvaDigitalVentasFslAnitaArmadoSupport::claveNatural(
                        $pv,
                        (int) $venta->numerocomprobante,
                    )] = true;
                }
                // RMV / FBI / FSL: no agrupar con venta global diaria CF.
                if ($letra === 'B' && ! $esSinCaeInformable) {
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

        if ($completarFslAnita) {
            $pvFslDefault = CierreRendicionMaquinaConfigSupport::puntoventaFsl($empresaId);
            foreach ($this->fslAnitaBridgeReader->listarPeriodo($empresaId, $desde, $hasta, $porFechaJornada) as $filaAnita) {
                $clave = LibroIvaDigitalVentasFslAnitaArmadoSupport::claveDesdeFilaAnita($filaAnita, $pvFslDefault);
                if (isset($clavesErpFsl[$clave])) {
                    continue;
                }
                $registro = LibroIvaDigitalVentasFslAnitaArmadoSupport::armarRegistroLibro(
                    $filaAnita,
                    $porFechaJornada,
                    $pvFslDefault,
                );
                if ($registro === null) {
                    continue;
                }
                $totalImporte += abs((float) ($registro['cabecera']['importe_total'] ?? 0));
                $conteoVentas++;
                $conteoFbiFsl++;
                $conteoFslAnita++;
                $clavesErpFsl[$clave] = true;
                $registrosIndividuales[] = $registro;
            }
        }

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
            $totalExento += abs((float) ($registro['cabecera']['operaciones_exentas'] ?? 0));
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
            'registros' => $registrosFinales,
            'resumen' => [
                'comprobantes' => $conteoRegistros,
                'ventas_emitidas' => $conteoVentas,
                'ventas_b_individuales' => $conteoVentasBIndividuales,
                'comprobantes_con_alicuotas' => $conteoConAlicuotas,
                'alicuotas' => $conteoAlicuotas,
                'importe_total' => round($totalImporte, 2),
                'total_iva' => round($totalIva, 2),
                'total_exento' => round($totalExento, 2),
                'ventas_rmv' => $conteoRmv,
                'ventas_fbi_fsl' => $conteoFbiFsl,
                'ventas_fsl_anita' => $conteoFslAnita,
                'por_fecha_jornada' => $porFechaJornada,
                'completar_fsl_anita' => $completarFslAnita,
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
            'puntoventas:id,codigo,actividad_arca_id',
            'puntoventas.actividad_arcas:id,codigoarca,nombre',
            'actividad_arca:id,codigoarca,nombre',
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
            ->where('pv_lid.empresa_id', $empresaId);

        LibroIvaDigitalVentasPeriodoSupport::aplicarFiltroFecha($query, $desde, $hasta, $porFechaJornada);
        LibroIvaDigitalVentasPeriodoSupport::aplicarFiltroCaeORmv($query);

        return $query;
    }

    /**
     * @return array{cabecera: array<string, mixed>, alicuotas: list<array<string, mixed>>}|null
     */
    private function armarRegistroVenta(Venta $venta, bool $porFechaJornada): ?array
    {
        // IZV interno gastronómico: no informa Libro IVA. RMV / FBI / FSL sí (sin CAE).
        $abrev = strtoupper(trim((string) ($venta->tipotransacciones->abreviatura ?? '')));
        if ($abrev === 'IZV') {
            return null;
        }

        $esRmv = LibroIvaDigitalMapeosSupport::esRmv($abrev);
        $esSinCaeInformable = LibroIvaDigitalMapeosSupport::esSinCaeInformable($abrev);
        $letra = LibroIvaDigitalMapeosSupport::letraDesdeCodigoVenta((string) $venta->codigo);
        $codigoBase = (string) ($venta->tipotransacciones->codigo ?? '001');
        $tipoComprobante = LibroIvaDigitalMapeosSupport::tipoComprobanteVentas($codigoBase, $letra, $abrev);
        $puntoVenta = (int) ($venta->puntoventas->codigo ?? 0);
        $numero = (int) $venta->numerocomprobante;

        // RMV viene letra Z; FBI/FSL letra B. Desglose de Factura B para todos los sin CAE.
        $totales = $this->desglosarImpuestos($venta, $esSinCaeInformable ? 'B' : $letra);
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

        $signo = (int) IvaVentasDesgloseSupport::signoVenta($venta);
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
            'percepcion_no_categorizados' => $signo * $totales['percepcion_no_categorizados'],
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

        $registro = LibroIvaDigitalVentasAlicuotaSupport::asegurarRegistro([
            'cabecera' => $cabecera,
            'alicuotas' => $alicuotas,
        ]);
        $registro['iva_simple'] = $this->metaIvaSimpleVenta($venta, $signoRaw < 0);

        return $registro;
    }

    /**
     * @return array{actividad_codigo: string, actividad_nombre: string, tipo_sujeto: int, restitucion: bool}
     */
    private function metaIvaSimpleVenta(Venta $venta, bool $restitucion): array
    {
        $actividad = $venta->actividad_arca;
        if ($actividad === null || trim((string) ($actividad->codigoarca ?? '')) === '') {
            $actividad = $venta->puntoventas?->actividad_arcas;
        }
        $codigo = LibroIvaDigitalIvaSimpleSupport::normalizarCodigoActividad(
            (string) ($actividad->codigoarca ?? '0'),
        );

        return [
            'actividad_codigo' => $codigo,
            'actividad_nombre' => (string) ($actividad->nombre ?? ''),
            'tipo_sujeto' => LibroIvaDigitalMapeosSupport::tipoSujetoCompradorIvaSimple(
                (string) ($venta->condicionivas->codigoexterno ?? ''),
            ),
            'restitucion' => $restitucion,
        ];
    }

    /**
     * @return array{
     *     importe_total: float,
     *     no_integra_neto: float,
     *     no_gravado: float,
     *     operaciones_exentas: float,
     *     percepcion_no_categorizados: float,
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
        $percNoCateg = 0.0;
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
            if (PercepcionNoCategorizadoSupport::esConcepto($concepto)) {
                $percNoCateg += $importe;
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
                $percNoCateg,
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
            'percepcion_no_categorizados' => $percNoCateg,
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
        float $percNoCateg = 0.0,
    ): array {
        $baseGravable = round(
            $importeTotal - $exento - $noGravado - $impInterno - $percNac - $percIibb - $percNoCateg,
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
