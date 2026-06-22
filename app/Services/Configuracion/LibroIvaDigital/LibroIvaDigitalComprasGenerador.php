<?php

namespace App\Services\Configuracion\LibroIvaDigital;

use App\Models\Compras\Comprobante_Proveedor;
use App\Support\Compras\ComprobanteProveedorEstados;
use App\Support\Configuracion\LibroIvaDigital\LibroIvaDigitalConceptoIvacompraSupport;
use App\Support\Configuracion\LibroIvaDigital\LibroIvaDigitalFormatoSupport;
use App\Support\Configuracion\LibroIvaDigital\LibroIvaDigitalMapeosSupport;
use Illuminate\Database\Eloquent\Builder;

class LibroIvaDigitalComprasGenerador
{
    /**
     * @return array{
     *     compras_cbte: string,
     *     compras_alicuotas: string,
     *     resumen: array<string, int|float>
     * }
     */
    public function generar(int $empresaId, int $anio, int $mes): array
    {
        $desde = sprintf('%04d-%02d-01', $anio, $mes);
        $hasta = date('Y-m-t', strtotime($desde));

        $lineasCbte = [];
        $lineasAlicuotas = [];
        $conteo = 0;
        $totalImporte = 0.0;
        $totalIva = 0.0;

        $this->queryCompras($empresaId, $desde, $hasta)
            ->orderBy('comprobante_proveedor.fechaiva')
            ->orderBy('comprobante_proveedor.sucursal')
            ->orderBy('comprobante_proveedor.numerocomprobante')
            ->chunkById(100, function ($comprobantes) use (&$lineasCbte, &$lineasAlicuotas, &$conteo, &$totalImporte, &$totalIva): void {
                $comprobantes->load([
                    'proveedores.tipoempresas',
                    'proveedores.condicionivas',
                    'tipotransaccion_compras',
                    'monedas',
                    'comprobante_proveedor_conceptos.concepto_ivacompras.impuestos',
                ]);

                foreach ($comprobantes as $cp) {
                    $registro = $this->armarRegistroCompra($cp);
                    if ($registro === null) {
                        continue;
                    }

                    $lineasCbte[] = LibroIvaDigitalFormatoSupport::registroComprasCbte($registro['cabecera']);
                    foreach ($registro['alicuotas'] as $alicuota) {
                        $lineasAlicuotas[] = LibroIvaDigitalFormatoSupport::registroComprasAlicuota($alicuota);
                        $totalIva += (float) ($alicuota['impuesto_liquidado'] ?? 0);
                    }

                    $conteo++;
                    $totalImporte += abs((float) $cp->total);
                }
            }, 'comprobante_proveedor.id', 'id');

        return [
            'compras_cbte' => implode("\r\n", $lineasCbte),
            'compras_alicuotas' => implode("\r\n", $lineasAlicuotas),
            'resumen' => [
                'comprobantes' => $conteo,
                'alicuotas' => count($lineasAlicuotas),
                'importe_total' => round($totalImporte, 2),
                'total_iva' => round($totalIva, 2),
            ],
        ];
    }

    private function queryCompras(int $empresaId, string $desde, string $hasta): Builder
    {
        return Comprobante_Proveedor::query()
            ->whereNull('comprobante_proveedor.deleted_at')
            ->where('comprobante_proveedor.empresa_id', $empresaId)
            ->whereBetween('comprobante_proveedor.fechaiva', [$desde, $hasta])
            ->where('comprobante_proveedor.estado', '<>', ComprobanteProveedorEstados::ANULADO);
    }

    /**
     * @return array{cabecera: array<string, mixed>, alicuotas: list<array<string, mixed>>}|null
     */
    private function armarRegistroCompra(Comprobante_Proveedor $cp): ?array
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
        $cuit = preg_replace('/\D+/', '', (string) ($cp->proveedores->nroinscripcion ?? '')) ?? '';

        $cabecera = [
            'fecha' => date('Ymd', strtotime((string) ($cp->fechaiva ?: $cp->fechacomprobante))),
            'tipo_comprobante' => $tipoComprobante,
            'punto_venta' => $puntoVenta,
            'numero_comprobante' => $numero,
            'despacho_importacion' => '',
            'codigo_documento' => '80',
            'numero_identificacion' => $cuit !== '' ? $cuit : '0',
            'nombre_vendedor' => (string) ($cp->proveedores->nombre ?? ''),
            'importe_total' => abs((float) $cp->total),
            'no_integra_neto' => $totales['no_integra'],
            'operaciones_exentas' => $totales['exento'],
            'percepciones_iva' => $totales['perc_iva'],
            'percepciones_nacionales' => $totales['perc_nacional'],
            'percepciones_iibb' => $totales['perc_iibb'],
            'percepciones_municipales' => $totales['perc_municipal'],
            'impuestos_internos' => $totales['imp_interno'],
            'codigo_moneda' => LibroIvaDigitalMapeosSupport::codigoMonedaAfip(
                $cp->monedas->codigo ?? null,
                $cp->monedas->nombre ?? null,
            ),
            'tipo_cambio' => (float) ($cp->cotizacion ?: 1),
            'cantidad_alicuotas' => $totales['cantidad_alicuotas'],
            'codigo_operacion' => ' ',
            'credito_fiscal_computable' => $totales['credito_computable'],
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
            ];
        }

        return ['cabecera' => $cabecera, 'alicuotas' => $alicuotas];
    }
}
