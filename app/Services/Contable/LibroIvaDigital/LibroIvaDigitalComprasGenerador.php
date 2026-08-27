<?php

namespace App\Services\Contable\LibroIvaDigital;

use App\Models\Compras\Comprobante_Proveedor;
use App\Support\Compras\ComprobanteProveedorEstados;
use App\Support\Contable\LibroIvaDigital\LibroIvaDigitalComprasAlicuotaSupport;
use App\Support\Contable\LibroIvaDigital\LibroIvaDigitalComprasAnitaArmadoSupport;
use App\Support\Contable\LibroIvaDigital\LibroIvaDigitalComprasAnitaBridgeReader;
use App\Support\Contable\LibroIvaDigital\LibroIvaDigitalConceptoIvacompraSupport;
use App\Support\Contable\LibroIvaDigital\LibroIvaDigitalFormatoSupport;
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

        $lineasCbte = [];
        $lineasAlicuotas = [];
        $registros = [];
        $conteo = 0;
        $conteoAnita = 0;
        $totalImporte = 0.0;
        $totalIva = 0.0;
        /** @var array<string, true> $clavesErp */
        $clavesErp = [];
        /** @var array<int, true> $nrosInternosErp */
        $nrosInternosErp = [];

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
                &$clavesErp,
                &$nrosInternosErp,
                $prorrateoGlobal,
                &$registros,
            ): void {
                $clave = $this->claveErp($cp);
                if ($clave !== '') {
                    $clavesErp[$clave] = true;
                }
                $nroInterno = (int) ($cp->anita_nro_interno ?? 0);
                if ($nroInterno > 0) {
                    $nrosInternosErp[$nroInterno] = true;
                }

                $registro = $this->armarRegistroCompra($cp, $prorrateoGlobal);
                if ($registro === null) {
                    return;
                }

                $registros[] = $registro;
                $lineasCbte[] = LibroIvaDigitalFormatoSupport::registroComprasCbte($registro['cabecera']);
                foreach ($registro['alicuotas'] as $alicuota) {
                    $lineasAlicuotas[] = LibroIvaDigitalFormatoSupport::registroComprasAlicuota($alicuota);
                    $totalIva += (float) ($alicuota['impuesto_liquidado'] ?? 0);
                }

                $conteo++;
                $totalImporte += abs((float) $cp->total);
            });

        if ($completarAnita) {
            $filasAnita = $this->comprasAnitaBridgeReader->listarPeriodo($empresaId, $desde, $hasta);
            foreach ($filasAnita as $fila) {
                $compra = $fila['compra'];
                $nroInterno = (int) ($compra['com_nro_interno'] ?? 0);
                if ($nroInterno > 0 && isset($nrosInternosErp[$nroInterno])) {
                    continue;
                }

                $clave = LibroIvaDigitalComprasAnitaArmadoSupport::claveNatural(
                    (string) ($compra['com_proveedor'] ?? ''),
                    (string) ($compra['com_tipo'] ?? ''),
                    (string) ($compra['com_letra'] ?? ''),
                    (int) ($compra['com_sucursal'] ?? 0),
                    (int) ($compra['com_nro'] ?? 0),
                );
                if (isset($clavesErp[$clave])) {
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

                $clavesErp[$clave] = true;
                if ($nroInterno > 0) {
                    $nrosInternosErp[$nroInterno] = true;
                }

                $tipo = LibroIvaDigitalComprasAnitaArmadoSupport::tipoPorAbreviatura(
                    strtoupper(substr(trim((string) ($compra['com_tipo'] ?? '')), 0, 3)),
                );
                $registro['iva_simple'] = [
                    'restitucion' => LibroIvaDigitalComprasAnitaArmadoSupport::esNotaCreditoTipo($tipo),
                ];
                $this->adjuntarConceptoIvaSimple($registro, $fila['conceptos'], (string) ($compra['com_letra'] ?? 'A'));
                $registros[] = $registro;
                $lineasCbte[] = LibroIvaDigitalFormatoSupport::registroComprasCbte($registro['cabecera']);
                foreach ($registro['alicuotas'] as $alicuota) {
                    $lineasAlicuotas[] = LibroIvaDigitalFormatoSupport::registroComprasAlicuota($alicuota);
                    $totalIva += (float) ($alicuota['impuesto_liquidado'] ?? 0);
                }

                $conteo++;
                $conteoAnita++;
                $totalImporte += abs((float) ($compra['com_monto'] ?? 0));
            }
        }

        return [
            'compras_cbte' => implode("\r\n", $lineasCbte),
            'compras_alicuotas' => implode("\r\n", $lineasAlicuotas),
            'registros' => $registros,
            'resumen' => [
                'comprobantes' => $conteo,
                'comprobantes_anita' => $conteoAnita,
                'alicuotas' => count($lineasAlicuotas),
                'importe_total' => round($totalImporte, 2),
                'total_iva' => round($totalIva, 2),
                'prorrateo_cf_global' => $prorrateoGlobal,
                'completar_compras_anita' => $completarAnita,
            ],
        ];
    }

    private function queryCompras(int $empresaId, string $desde, string $hasta): Builder
    {
        return Comprobante_Proveedor::query()
            ->where('comprobante_proveedor.empresa_id', $empresaId)
            ->whereBetween('comprobante_proveedor.fechaiva', [$desde, $hasta])
            ->where('comprobante_proveedor.estado', '<>', ComprobanteProveedorEstados::ANULADO);
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

        $credito = $prorrateoGlobal ? 0.0 : (float) $totales['credito_computable'];

        $cabecera = [
            'fecha' => date('Ymd', strtotime((string) ($cp->fechaiva ?: $cp->fechacomprobante))),
            'tipo_comprobante' => $tipoComprobante,
            'punto_venta' => $puntoVenta,
            'numero_comprobante' => $numero,
            'despacho_importacion' => '',
            'codigo_documento' => '80',
            'numero_identificacion' => $cuit !== '' ? $cuit : '0',
            'nombre_vendedor' => (string) ($cp->proveedores->nombre ?? $cp->proveedor_nombre_eventual ?? ''),
            'importe_total' => abs((float) $cp->total),
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
            'otros_tributos' => 0,
            'cuit_emisor_corredor' => '0',
            'denominacion_emisor_corredor' => '',
            'iva_comision' => 0,
        ];

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
        $alicuotas = LibroIvaDigitalComprasAnitaArmadoSupport::alicuotasIvaSimple($conceptos, $letra);
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
}
