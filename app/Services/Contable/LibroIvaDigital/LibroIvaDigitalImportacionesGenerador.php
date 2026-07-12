<?php

namespace App\Services\Contable\LibroIvaDigital;

use App\Support\Contable\LibroIvaDigital\LibroIvaDigitalFormatoSupport;
use App\Support\Contable\LibroIvaDigital\LibroIvaDigitalMapeosSupport;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class LibroIvaDigitalImportacionesGenerador
{
    /**
     * @return array{
     *     compras_cbte_importacion: list<string>,
     *     importacion_bienes_alicuotas: string,
     *     importacion_servicios: string,
     *     resumen: array<string, int>
     * }
     */
    public function generar(int $empresaId, int $anio, int $mes): array
    {
        if (! Schema::hasTable('libro_iva_importacion_bien')) {
            return [
                'compras_cbte_importacion' => [],
                'importacion_bienes_alicuotas' => '',
                'importacion_servicios' => '',
                'resumen' => ['bienes' => 0, 'servicios' => 0],
            ];
        }

        $desde = sprintf('%04d-%02d-01', $anio, $mes);
        $hasta = date('Y-m-t', strtotime($desde));

        $cabecerasImportacion = [];
        $lineasAlicuotas = [];

        $bienes = DB::table('libro_iva_importacion_bien')
            ->where('empresa_id', $empresaId)
            ->whereBetween('fecha_oficializacion', [$desde, $hasta])
            ->orderBy('fecha_oficializacion')
            ->orderBy('despacho')
            ->get();

        foreach ($bienes as $row) {
            $cabecerasImportacion[] = LibroIvaDigitalFormatoSupport::registroComprasCbte([
                'fecha' => date('Ymd', strtotime((string) $row->fecha_oficializacion)),
                'tipo_comprobante' => '066',
                'punto_venta' => 0,
                'numero_comprobante' => 0,
                'despacho_importacion' => $row->despacho,
                'codigo_documento' => '80',
                'numero_identificacion' => '0',
                'nombre_vendedor' => 'IMPORTACION',
                'importe_total' => (float) $row->importe_total,
                'no_integra_neto' => 0,
                'operaciones_exentas' => 0,
                'percepciones_iva' => 0,
                'percepciones_nacionales' => 0,
                'percepciones_iibb' => 0,
                'percepciones_municipales' => 0,
                'impuestos_internos' => 0,
                'codigo_moneda' => LibroIvaDigitalMapeosSupport::codigoMonedaAfip((string) $row->codigo_moneda),
                'tipo_cambio' => (float) ($row->tipo_cambio ?: 1),
                'cantidad_alicuotas' => 1,
                'codigo_operacion' => $row->codigo_operacion ?: ' ',
                'credito_fiscal_computable' => (float) $row->impuesto_liquidado,
                'otros_tributos' => 0,
                'cuit_emisor_corredor' => '0',
                'denominacion_emisor_corredor' => '',
                'iva_comision' => 0,
            ]);

            $lineasAlicuotas[] = LibroIvaDigitalFormatoSupport::registroImportacionBienAlicuota([
                'despacho' => $row->despacho,
                'neto_gravado' => (float) $row->neto_gravado,
                'alicuota_iva' => $row->alicuota_lid,
                'impuesto_liquidado' => (float) $row->impuesto_liquidado,
            ]);
        }

        $lineasServicios = [];
        if (Schema::hasTable('libro_iva_importacion_servicio')) {
            $servicios = DB::table('libro_iva_importacion_servicio')
                ->where('empresa_id', $empresaId)
                ->whereBetween('fecha_operacion', [$desde, $hasta])
                ->orderBy('fecha_operacion')
                ->get();

            foreach ($servicios as $row) {
                $lineasServicios[] = LibroIvaDigitalFormatoSupport::registroImportacionServicio([
                    'tipo_comprobante' => (int) $row->tipo_comprobante,
                    'descripcion' => (int) $row->tipo_comprobante === 3 ? ($row->descripcion ?? '') : '',
                    'identificacion_comprobante' => $row->identificacion_comprobante,
                    'fecha_operacion' => date('Ymd', strtotime((string) $row->fecha_operacion)),
                    'monto_moneda_original' => (float) $row->monto_moneda_original,
                    'codigo_moneda' => LibroIvaDigitalMapeosSupport::codigoMonedaAfip((string) $row->codigo_moneda),
                    'tipo_cambio' => (float) ($row->tipo_cambio ?: 1),
                    'cuit_prestador' => preg_replace('/\D+/', '', (string) $row->cuit_prestador),
                    'nif_prestador' => $row->nif_prestador ?? '',
                    'nombre_prestador' => $row->nombre_prestador,
                    'alicuota_iva' => $row->alicuota_lid,
                    'fecha_ingreso_impuesto' => date('Ymd', strtotime((string) $row->fecha_ingreso_impuesto)),
                    'monto_impuesto_ingresado' => (float) $row->monto_impuesto_ingresado,
                    'impuesto_computable' => (float) $row->impuesto_computable,
                    'identificacion_pago' => $row->identificacion_pago ?? '',
                    'cuit_entidad_pago' => preg_replace('/\D+/', '', (string) ($row->cuit_entidad_pago ?? '0')),
                ]);
            }
        }

        return [
            'compras_cbte_importacion' => $cabecerasImportacion,
            'importacion_bienes_alicuotas' => implode("\r\n", $lineasAlicuotas),
            'importacion_servicios' => implode("\r\n", $lineasServicios),
            'resumen' => [
                'bienes' => count($lineasAlicuotas),
                'servicios' => count($lineasServicios),
            ],
        ];
    }
}
