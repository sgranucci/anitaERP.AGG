<?php

namespace App\Services\Contable\LibroIvaDigital;

use App\Models\Compras\Comprobante_Proveedor;
use App\Models\Configuracion\Empresa;
use App\Support\Compras\ComprobanteProveedorEstados;
use App\Support\Contable\LibroIvaDigital\LibroIvaDigitalComprasAlicuotaSupport;
use App\Support\Contable\LibroIvaDigital\LibroIvaDigitalComprasAnitaArmadoSupport;
use App\Support\Contable\LibroIvaDigital\LibroIvaDigitalComprasAnitaBridgeReader;
use App\Support\Contable\LibroIvaDigital\LibroIvaDigitalComprasCuitSupport;
use App\Support\Contable\LibroIvaDigital\LibroIvaDigitalComprasImportesSupport;
use App\Support\Contable\LibroIvaDigital\LibroIvaDigitalConceptoIvacompraSupport;
use App\Support\Contable\LibroIvaDigital\LibroIvaDigitalFormatoSupport;
use App\Support\Contable\LibroIvaDigital\LibroIvaDigitalIvaSimpleSupport;
use App\Support\Contable\LibroIvaDigital\LibroIvaDigitalMapeosSupport;
use Illuminate\Database\Eloquent\Builder;

class LibroIvaDigitalComprasGenerador
{
    public function __construct(
        private readonly LibroIvaDigitalComprasAnitaBridgeReader $comprasAnitaBridgeReader,
    ) {
    }

    /**
     * @param  array{
     *     prorrateo_cf_global?: bool,
     *     completar_compras_anita?: bool
     * }  $opciones
     * @return array{
     *     compras_cbte: string,
     *     compras_alicuotas: string,
     *     resumen: array<string, int|float|bool>
     * }
     */
    public function generar(int $empresaId, int $anio, int $mes, array $opciones = []): array
    {
        $desde = sprintf('%04d-%02d-01', $anio, $mes);
        $hasta = date('Y-m-t', strtotime($desde));
        $prorrateoGlobal = (bool) ($opciones['prorrateo_cf_global'] ?? false);
        $completarAnita = (bool) ($opciones['completar_compras_anita'] ?? true);
        $cuitInformante = LibroIvaDigitalComprasCuitSupport::soloDigitos(
            (string) (Empresa::query()->whereKey($empresaId)->value('nroinscripcion') ?? ''),
        );

        $lineasCbte = [];
        $lineasAlicuotas = [];
        $registros = [];
        $conteo = 0;
        $conteoAnita = 0;
        $omitidosSinCuit = 0;
        $omitidosCuitInformante = 0;
        /** @var list<array<string, mixed>> $omitidosDetalle */
        $omitidosDetalle = [];
        $totalImporte = 0.0;
        $totalIva = 0.0;
        /** @var array<string, true> $clavesUsadas */
        $clavesUsadas = [];
        /** @var array<int, true> $nrosInternosUsados */
        $nrosInternosUsados = [];

        // Anita es el libro de compras (Portal). El ERP solo aporta lo que no está en Anita:
        // si se prioriza ERP, un comprobante con conceptos incompletos tapa el concmov de Anita
        // y faltan gravado y NC (el cruce de ~11 M / ~7 M de IVA).
        if ($completarAnita) {
            foreach ($this->comprasAnitaBridgeReader->listarPeriodo($empresaId, $desde, $hasta) as $fila) {
                $compra = $fila['compra'];
                $tipoAbrev = (string) ($compra['com_tipo'] ?? '');
                $letraAnita = (string) ($compra['com_letra'] ?? '');
                $tipo = LibroIvaDigitalComprasAnitaArmadoSupport::tipoPorAbreviatura($tipoAbrev);
                $clave = LibroIvaDigitalComprasAnitaArmadoSupport::claveNatural(
                    (string) ($compra['com_proveedor'] ?? ''),
                    $tipoAbrev,
                    $letraAnita,
                    (int) ($compra['com_sucursal'] ?? 0),
                    (int) ($compra['com_nro'] ?? 0),
                );
                $nroInterno = (int) ($compra['com_nro_interno'] ?? 0);

                if ($tipo !== null
                    && ! LibroIvaDigitalComprasAnitaArmadoSupport::esTipoInformableIvaCompras($tipo, $letraAnita)) {
                    if ($clave !== '') {
                        $clavesUsadas[$clave] = true;
                    }
                    if ($nroInterno > 0) {
                        $nrosInternosUsados[$nroInterno] = true;
                    }
                    continue;
                }

                $registro = LibroIvaDigitalComprasAnitaArmadoSupport::armarRegistro(
                    $compra,
                    $fila['conceptos'],
                    $prorrateoGlobal,
                );
                if ($registro === null) {
                    continue;
                }

                if ($clave !== '') {
                    $clavesUsadas[$clave] = true;
                }
                if ($nroInterno > 0) {
                    $nrosInternosUsados[$nroInterno] = true;
                }
                $tipoCbte = (string) ($registro['cabecera']['tipo_comprobante'] ?? '');
                $registro['iva_simple'] = [
                    'restitucion' => LibroIvaDigitalComprasAnitaArmadoSupport::esNotaCreditoTipo($tipo)
                        || LibroIvaDigitalComprasAnitaArmadoSupport::esNotaCreditoAbreviatura($tipoAbrev)
                        || LibroIvaDigitalMapeosSupport::esTipoNotaCredito($tipoCbte),
                ];
                $this->adjuntarConceptoIvaSimple($registro, $fila['conceptos'], (string) ($compra['com_letra'] ?? 'A'));
                if (! LibroIvaDigitalComprasCuitSupport::tieneCuitVendedor($registro)) {
                    $omitidosSinCuit++;
                    $omitidosDetalle[] = $this->filaOmitido(
                        $registro,
                        'sin_cuit',
                        'Sin CUIT de vendedor (ARCA exige código 80)',
                        'anita',
                        (string) ($compra['com_proveedor'] ?? ''),
                        (string) ($compra['com_tipo'] ?? ''),
                        (string) ($compra['com_letra'] ?? ''),
                    );
                    continue;
                }
                if (LibroIvaDigitalComprasCuitSupport::esCuitInformante($registro, $cuitInformante)) {
                    $omitidosCuitInformante++;
                    $omitidosDetalle[] = $this->filaOmitido(
                        $registro,
                        'cuit_informante',
                        'CUIT del vendedor igual al CUIT del informante',
                        'anita',
                        (string) ($compra['com_proveedor'] ?? ''),
                        (string) ($compra['com_tipo'] ?? ''),
                        (string) ($compra['com_letra'] ?? ''),
                    );
                    continue;
                }
                $this->acumularRegistro(
                    $registro,
                    abs((float) ($compra['com_monto'] ?? 0)),
                    $lineasCbte,
                    $lineasAlicuotas,
                    $registros,
                    $conteo,
                    $totalImporte,
                    $totalIva,
                );
                $conteoAnita++;
            }
        }

        $this->queryCompras($empresaId, $desde, $hasta)
            ->with([
                'proveedores.tipoempresas',
                'proveedores.condicionivas',
                'tipotransaccion_compras',
                'monedas',
                'comprobante_proveedor_conceptos.concepto_ivacompras.impuestos',
            ])
            ->orderBy('comprobante_proveedor.fechaiva')
            ->orderBy('comprobante_proveedor.sucursal')
            ->orderBy('comprobante_proveedor.numerocomprobante')
            ->lazy(100)
            ->each(function (Comprobante_Proveedor $cp) use (
                &$lineasCbte,
                &$lineasAlicuotas,
                &$conteo,
                &$totalImporte,
                &$totalIva,
                &$clavesUsadas,
                &$nrosInternosUsados,
                $prorrateoGlobal,
                &$registros,
                &$omitidosSinCuit,
                &$omitidosCuitInformante,
                &$omitidosDetalle,
                $cuitInformante,
            ): void {
                $nroInterno = (int) ($cp->anita_nro_interno ?? 0);
                if ($nroInterno > 0 && isset($nrosInternosUsados[$nroInterno])) {
                    return;
                }
                $clave = $this->claveErp($cp);
                if ($clave !== '' && isset($clavesUsadas[$clave])) {
                    return;
                }

                $registro = $this->armarRegistroCompra($cp, $prorrateoGlobal);
                if ($registro === null) {
                    return;
                }

                if ($clave !== '') {
                    $clavesUsadas[$clave] = true;
                }
                if ($nroInterno > 0) {
                    $nrosInternosUsados[$nroInterno] = true;
                }

                $tipoCbte = (string) ($registro['cabecera']['tipo_comprobante'] ?? '');
                $registro['iva_simple']['restitucion'] = (bool) ($registro['iva_simple']['restitucion'] ?? false)
                    || LibroIvaDigitalMapeosSupport::esTipoNotaCredito($tipoCbte);

                if (! LibroIvaDigitalComprasCuitSupport::tieneCuitVendedor($registro)) {
                    $omitidosSinCuit++;
                    $omitidosDetalle[] = $this->filaOmitido(
                        $registro,
                        'sin_cuit',
                        'Sin CUIT de vendedor (ARCA exige código 80)',
                        'erp',
                        (string) ($cp->proveedores->codigo ?? ''),
                        (string) ($cp->tipotransaccion_compras->abreviatura ?? ''),
                        (string) ($cp->letra ?? ''),
                    );

                    return;
                }
                if (LibroIvaDigitalComprasCuitSupport::esCuitInformante($registro, $cuitInformante)) {
                    $omitidosCuitInformante++;
                    $omitidosDetalle[] = $this->filaOmitido(
                        $registro,
                        'cuit_informante',
                        'CUIT del vendedor igual al CUIT del informante',
                        'erp',
                        (string) ($cp->proveedores->codigo ?? ''),
                        (string) ($cp->tipotransaccion_compras->abreviatura ?? ''),
                        (string) ($cp->letra ?? ''),
                    );

                    return;
                }

                $this->acumularRegistro(
                    $registro,
                    abs((float) $cp->total),
                    $lineasCbte,
                    $lineasAlicuotas,
                    $registros,
                    $conteo,
                    $totalImporte,
                    $totalIva,
                );
            });

        $totalesPortal = LibroIvaDigitalIvaSimpleSupport::creditoDesdeRegistrosLibro($registros, $prorrateoGlobal);

        return [
            'compras_cbte' => implode("\r\n", $lineasCbte),
            'compras_alicuotas' => implode("\r\n", $lineasAlicuotas),
            'registros' => $registros,
            'omitidos' => $omitidosDetalle,
            'resumen' => [
                'comprobantes' => $conteo,
                'comprobantes_anita' => $conteoAnita,
                'omitidos_sin_cuit' => $omitidosSinCuit,
                'omitidos_cuit_informante' => $omitidosCuitInformante,
                'omitidos_total' => count($omitidosDetalle),
                'alicuotas' => count($lineasAlicuotas),
                'importe_total' => round($totalImporte, 2),
                'total_iva' => round($totalIva, 2),
                'neto_portal' => $totalesPortal['total_neto_portal'],
                'iva_portal' => $totalesPortal['total_iva_portal'],
                'neto_facturas' => $totalesPortal['total_neto_facturas'],
                'neto_nc' => $totalesPortal['total_neto_nc'],
                'prorrateo_cf_global' => $prorrateoGlobal,
                'completar_compras_anita' => $completarAnita,
            ],
        ];
    }

    /**
     * @param  array{cabecera: array<string, mixed>, alicuotas?: list<array<string, mixed>>}  $registro
     * @return array{
     *     motivo: string,
     *     motivo_texto: string,
     *     origen: string,
     *     fecha: string,
     *     tipo_comprobante: string,
     *     punto_venta: int,
     *     numero_comprobante: int,
     *     codigo_documento: string,
     *     numero_identificacion: string,
     *     nombre_vendedor: string,
     *     importe_total: float,
     *     proveedor_codigo: string,
     *     tipo_abrev: string,
     *     letra: string
     * }
     */
    private function filaOmitido(
        array $registro,
        string $motivo,
        string $motivoTexto,
        string $origen,
        string $proveedorCodigo = '',
        string $tipoAbrev = '',
        string $letra = '',
    ): array {
        $cab = $registro['cabecera'] ?? [];

        return [
            'motivo' => $motivo,
            'motivo_texto' => $motivoTexto,
            'origen' => $origen,
            'fecha' => (string) ($cab['fecha'] ?? ''),
            'tipo_comprobante' => str_pad((string) ($cab['tipo_comprobante'] ?? ''), 3, '0', STR_PAD_LEFT),
            'punto_venta' => (int) ($cab['punto_venta'] ?? 0),
            'numero_comprobante' => (int) ($cab['numero_comprobante'] ?? 0),
            'codigo_documento' => (string) ($cab['codigo_documento'] ?? ''),
            'numero_identificacion' => (string) ($cab['numero_identificacion'] ?? ''),
            'nombre_vendedor' => (string) ($cab['nombre_vendedor'] ?? ''),
            'importe_total' => round((float) ($cab['importe_total'] ?? 0), 2),
            'proveedor_codigo' => $proveedorCodigo,
            'tipo_abrev' => $tipoAbrev,
            'letra' => strtoupper(substr(trim($letra), 0, 1)),
        ];
    }

    private function queryCompras(int $empresaId, string $desde, string $hasta): Builder
    {
        return LibroIvaDigitalComprasAnitaArmadoSupport::restringirQueryTiposInformables(
            Comprobante_Proveedor::query()
                ->where('comprobante_proveedor.empresa_id', $empresaId)
                ->whereBetween('comprobante_proveedor.fechaiva', [$desde, $hasta])
                ->where('comprobante_proveedor.estado', '<>', ComprobanteProveedorEstados::ANULADO)
        );
    }

    private function claveErp(Comprobante_Proveedor $cp): string
    {
        $proveedor = str_pad(trim((string) ($cp->proveedores->codigo ?? '')), 6, '0', STR_PAD_LEFT);
        $tipo = strtoupper(substr(trim((string) ($cp->tipotransaccion_compras->abreviatura ?? '')), 0, 3));
        $letra = strtoupper(substr(trim((string) ($cp->letra ?: 'A')), 0, 1));

        if ($proveedor === '000000' || $tipo === '') {
            return '';
        }

        return LibroIvaDigitalComprasAnitaArmadoSupport::claveNatural(
            $proveedor,
            $tipo,
            $letra,
            (int) $cp->sucursal,
            (int) $cp->numerocomprobante,
        );
    }

    /**
     * @return array{cabecera: array<string, mixed>, alicuotas: list<array<string, mixed>>}|null
     */
    private function armarRegistroCompra(Comprobante_Proveedor $cp, bool $prorrateoGlobal): ?array
    {
        $letra = strtoupper((string) ($cp->letra ?: 'A'));
        if (! LibroIvaDigitalComprasAnitaArmadoSupport::esTipoInformableIvaCompras(
            $cp->tipotransaccion_compras,
            $letra,
        )) {
            return null;
        }

        $codigoAfip = (string) ($cp->tipotransaccion_compras->codigoafip ?? '001');
        $tipoComprobante = LibroIvaDigitalMapeosSupport::tipoComprobanteVentas($codigoAfip, $letra);
        $puntoVenta = (int) $cp->sucursal;
        $numero = (int) $cp->numerocomprobante;

        $totales = LibroIvaDigitalConceptoIvacompraSupport::desglosarComprobante(
            $cp->comprobante_proveedor_conceptos,
            $letra,
        );
        $cuit = preg_replace('/\D+/', '', (string) ($cp->proveedores->nroinscripcion ?? $cp->proveedor_documento_eventual ?? '')) ?? '';
        $codigoMoneda = LibroIvaDigitalMapeosSupport::codigoMonedaAfip(
            $cp->monedas->codigo ?? null,
            $cp->monedas->nombre ?? null,
        );
        $fechaDoc = (string) ($cp->fechaiva ?: $cp->fechacomprobante);
        $coeficiente = LibroIvaDigitalComprasAnitaArmadoSupport::coeficienteDesdeErp(
            (int) ($cp->moneda_id ?? 1),
            $cp->cotizacion,
            $fechaDoc,
            $codigoMoneda,
        );
        $totales = LibroIvaDigitalComprasImportesSupport::aplicarCoeficiente($totales, $coeficiente);

        $credito = $prorrateoGlobal ? 0.0 : (float) $totales['credito_computable'];

        $cabecera = [
            'fecha' => date('Ymd', strtotime($fechaDoc)),
            'tipo_comprobante' => $tipoComprobante,
            'punto_venta' => $puntoVenta,
            'numero_comprobante' => $numero,
            'despacho_importacion' => '',
            'codigo_documento' => '80',
            'numero_identificacion' => $cuit !== '' ? $cuit : '0',
            'nombre_vendedor' => (string) ($cp->proveedores->nombre ?? $cp->proveedor_nombre_eventual ?? ''),
            'importe_total' => abs((float) $cp->total) * $coeficiente,
            'no_integra_neto' => $totales['no_integra'],
            'operaciones_exentas' => $totales['exento'],
            'percepciones_iva' => $totales['perc_iva'],
            'percepciones_nacionales' => $totales['perc_nacional'],
            'percepciones_iibb' => $totales['perc_iibb'],
            'percepciones_municipales' => $totales['perc_municipal'],
            'impuestos_internos' => $totales['imp_interno'],
            'codigo_moneda' => $codigoMoneda,
            'tipo_cambio' => LibroIvaDigitalMapeosSupport::tipoCambioArca(
                $codigoMoneda,
                (float) ($cp->cotizacion ?: 1),
            ),
            'cantidad_alicuotas' => $totales['cantidad_alicuotas'],
            'codigo_operacion' => ' ',
            'credito_fiscal_computable' => $credito,
            'otros_tributos' => $totales['otros'] ?? 0,
            'cuit_emisor_corredor' => '0',
            'denominacion_emisor_corredor' => '',
            'iva_comision' => 0,
        ];
        $cabecera = LibroIvaDigitalMapeosSupport::cabeceraImportesEnPesos($cabecera);

        $alicuotas = [];
        foreach ($totales['alicuotas'] as $row) {
            $alicuotas[] = [
                'tipo_comprobante' => $tipoComprobante,
                'punto_venta' => $puntoVenta,
                'numero_comprobante' => $numero,
                'codigo_documento' => '80',
                'numero_identificacion' => $cuit !== '' ? $cuit : '0',
                'neto_gravado' => $row['neto'],
                'alicuota_iva' => $row['codigo_lid'],
                'impuesto_liquidado' => $row['iva'],
                'concepto_iva_simple' => (int) ($row['concepto_iva_simple'] ?? 1),
                'tasa' => (float) ($row['tasa'] ?? 0),
            ];
        }

        $registro = LibroIvaDigitalComprasAlicuotaSupport::asegurarRegistro([
            'cabecera' => $cabecera,
            'alicuotas' => $alicuotas,
        ]);
        $registro['iva_simple'] = [
            'restitucion' => LibroIvaDigitalComprasAnitaArmadoSupport::esNotaCreditoTipo($cp->tipotransaccion_compras),
        ];

        return $registro;
    }

    /**
     * @param  array{cabecera: array<string, mixed>, alicuotas: list<array<string, mixed>>}  $registro
     * @param  list<array{concepto: int, importe: float}>  $conceptos
     */
    private function adjuntarConceptoIvaSimple(array &$registro, array $conceptos, string $letra): void
    {
        $codigoMoneda = LibroIvaDigitalMapeosSupport::codigoMonedaAfip(
            (string) ($registro['cabecera']['codigo_moneda'] ?? 'PES'),
            null,
        );
        $coeficiente = LibroIvaDigitalComprasAnitaArmadoSupport::coeficienteMoneda(
            $codigoMoneda,
            (float) ($registro['cabecera']['tipo_cambio'] ?? 1),
        );
        $alicuotas = LibroIvaDigitalComprasAnitaArmadoSupport::alicuotasIvaSimple($conceptos, $letra, $coeficiente);
        $porLid = [];
        foreach ($alicuotas as $row) {
            $codigo = LibroIvaDigitalMapeosSupport::codigoAlicuotaLid((float) ($row['tasa'] ?? 0));
            $porLid[$codigo] = (int) ($row['concepto_iva_simple'] ?? 1);
        }
        foreach ($registro['alicuotas'] as $i => $alicuota) {
            $codigo = (string) ($alicuota['alicuota_iva'] ?? '');
            if (isset($porLid[$codigo])) {
                $registro['alicuotas'][$i]['concepto_iva_simple'] = $porLid[$codigo];
            }
        }
    }

    /**
     * @param  array{cabecera: array<string, mixed>, alicuotas: list<array<string, mixed>>}  $registro
     * @param  list<string>  $lineasCbte
     * @param  list<string>  $lineasAlicuotas
     * @param  list<array<string, mixed>>  $registros
     */
    private function acumularRegistro(
        array $registro,
        float $importeAbs,
        array &$lineasCbte,
        array &$lineasAlicuotas,
        array &$registros,
        int &$conteo,
        float &$totalImporte,
        float &$totalIva,
    ): void {
        $registros[] = $registro;
        $lineasCbte[] = LibroIvaDigitalFormatoSupport::registroComprasCbte($registro['cabecera']);
        foreach ($registro['alicuotas'] as $alicuota) {
            $lineasAlicuotas[] = LibroIvaDigitalFormatoSupport::registroComprasAlicuota($alicuota);
            $totalIva += (float) ($alicuota['impuesto_liquidado'] ?? 0);
        }
        $conteo++;
        $totalImporte += $importeAbs;
    }
}
