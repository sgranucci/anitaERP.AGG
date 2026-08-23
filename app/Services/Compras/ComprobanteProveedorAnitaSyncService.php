<?php

namespace App\Services\Compras;

use App\ApiAnita;
use App\Models\Compras\Comprobante_Proveedor;
use App\Support\Compras\AnitaSync\ComprobanteProveedor\AplicpedFacturaAnitaMapper;
use App\Support\Compras\AnitaSync\ComprobanteProveedor\CompraCabeceraAnitaMapper;
use App\Support\Compras\AnitaSync\ComprobanteProveedor\ComprobanteProveedorAnitaContext;
use App\Support\Compras\AnitaSync\ComprobanteProveedor\ComprobanteProveedorAnitaNroInternoSupport;
use App\Support\Compras\AnitaSync\ComprobanteProveedor\ComprobanteProveedorConcmovPertenenciaSupport;
use App\Support\Compras\AnitaSync\ComprobanteProveedor\ConcmovLineaAnitaMapper;
use App\Support\Compras\AnitaSync\ComprobanteProveedor\PromovCuotaAnitaMapper;
use App\Support\Compras\ComprobanteProveedorAnitaSyncEstado;
use App\Support\Compras\ComprobanteProveedorFacturaAnticipadaSupport;
use App\Support\Stock\RecepcionProveedorAnitaEscrituraSupport;
use App\Support\Stock\RecepcionProveedorAnitaWhereSupport;
use RuntimeException;

/**
 * Sincroniza comprobante ERP → Anita (compra, concmov, promov, aplicped).
 * Las aplicaciones de CC (aplmovp + promov.prov_t_pagado) las graba
 * ProveedorCuentacorrienteAplicacionAnitaSyncService.
 * Contabilidad: ctamov vía AsientoRepository (como facturación), no subdiario Anita.
 * aplicped: factura → PEP para que Anita (Capex, mayor, hop FIB) conozca la OC.
 */
class ComprobanteProveedorAnitaSyncService
{
    private const SISTEMA_COMPRAS = 'compras';

    public function __construct(
        private ComprobanteProveedorAnitaNroInternoSupport $nroInternoSupport,
    ) {}

    public function syncCreate(Comprobante_Proveedor $comprobante): void
    {
        $comprobante->loadMissing([
            'comprobante_proveedor_conceptos.concepto_ivacompras',
            'comprobante_proveedor_cuotas',
            'comprobante_proveedor_articulos.articulos',
            'empresas', 'proveedores.condicionivas', 'proveedores.provincias',
            'proveedor_condicioniva_eventual', 'condicionpagos',
            'tipotransaccion_compras', 'monedas',
            'ordencompras.ordencompra_articulos.articulos',
        ]);

        if (! $comprobante->anita_nro_interno) {
            $comprobante->forceFill([
                'anita_nro_interno' => $this->nroInternoSupport->siguiente(),
            ])->save();
            $comprobante->refresh();
        }

        $ctx = new ComprobanteProveedorAnitaContext($comprobante, (int) $comprobante->anita_nro_interno);

        $this->insertCompra($ctx);
        $this->syncConceptos($ctx);
        $this->syncPromov($ctx);
        $this->syncAplicped($ctx);

        $comprobante->forceFill([
            'anita_sync_estado' => ComprobanteProveedorAnitaSyncEstado::SYNC_OK,
            'anita_sync_error' => null,
            'anita_sync_at' => now(),
        ])->save();
    }

    public function syncUpdate(Comprobante_Proveedor $comprobante): void
    {
        $comprobante->loadMissing([
            'comprobante_proveedor_conceptos.concepto_ivacompras',
            'comprobante_proveedor_cuotas',
            'comprobante_proveedor_articulos.articulos',
            'empresas', 'proveedores.condicionivas', 'proveedores.provincias',
            'proveedor_condicioniva_eventual', 'condicionpagos',
            'tipotransaccion_compras', 'monedas',
            'ordencompras.ordencompra_articulos.articulos',
        ]);

        if (! $comprobante->anita_nro_interno) {
            $this->syncCreate($comprobante);

            return;
        }

        $ctx = new ComprobanteProveedorAnitaContext($comprobante, (int) $comprobante->anita_nro_interno);

        $this->updateCompra($ctx);
        $this->deleteConceptos($ctx);
        $this->syncConceptos($ctx);
        $this->deletePromov($ctx);
        $this->syncPromov($ctx);
        $this->deleteAplicped($ctx);
        $this->syncAplicped($ctx);

        $comprobante->forceFill([
            'anita_sync_estado' => ComprobanteProveedorAnitaSyncEstado::SYNC_OK,
            'anita_sync_error' => null,
            'anita_sync_at' => now(),
        ])->save();
    }

    public function syncDelete(Comprobante_Proveedor $comprobante): void
    {
        if (! $comprobante->anita_nro_interno) {
            return;
        }

        $comprobante->loadMissing([
            'proveedores',
            'tipotransaccion_compras',
            'comprobante_proveedor_conceptos.concepto_ivacompras',
            'ordencompras',
        ]);
        $ctx = new ComprobanteProveedorAnitaContext($comprobante, (int) $comprobante->anita_nro_interno);

        $this->deleteAplicped($ctx);
        $this->apiDelete('promov', $ctx->claveWherePromov());
        $this->deleteConceptos($ctx);
        $this->apiDelete('compra', $ctx->claveWhereCompra());
    }

    /**
     * Regraba solo aplicped (factura → PEP). No toca compra / concmov / promov.
     */
    public function resyncAplicped(Comprobante_Proveedor $comprobante): void
    {
        $comprobante->loadMissing([
            'proveedores',
            'tipotransaccion_compras',
            'comprobante_proveedor_articulos.articulos',
            'ordencompras.ordencompra_articulos.articulos',
        ]);

        if (! $comprobante->anita_nro_interno) {
            throw new RuntimeException(
                'Comprobante '.$comprobante->id.' sin anita_nro_interno; no se puede grabar aplicped.'
            );
        }

        $ctx = new ComprobanteProveedorAnitaContext($comprobante, (int) $comprobante->anita_nro_interno);
        $this->deleteAplicped($ctx);
        $this->syncAplicped($ctx);
    }

    private function insertCompra(ComprobanteProveedorAnitaContext $ctx): void
    {
        $api = new ApiAnita;
        $api->apiCallEscritura([
            'acc' => 'insert',
            'tabla' => 'compra',
            'sistema' => self::SISTEMA_COMPRAS,
            'campos' => CompraCabeceraAnitaMapper::camposInsert(),
            'valores' => CompraCabeceraAnitaMapper::valoresInsert($ctx),
        ], 'compra insert comprobante proveedor');
    }

    private function updateCompra(ComprobanteProveedorAnitaContext $ctx): void
    {
        $api = new ApiAnita;
        $api->apiCallEscritura([
            'acc' => 'update',
            'tabla' => 'compra',
            'sistema' => self::SISTEMA_COMPRAS,
            'valores' => CompraCabeceraAnitaMapper::valoresUpdate($ctx),
            'whereArmado' => $ctx->claveWhereCompra(),
        ], 'compra update comprobante proveedor');
    }

    private function syncConceptos(ComprobanteProveedorAnitaContext $ctx): void
    {
        $api = new ApiAnita;
        $orden = 1;
        foreach ($ctx->comprobante->comprobante_proveedor_conceptos as $linea) {
            $api->apiCallEscritura([
                'acc' => 'insert',
                'tabla' => 'concmov',
                'sistema' => self::SISTEMA_COMPRAS,
                'campos' => ConcmovLineaAnitaMapper::camposInsert(),
                'valores' => ConcmovLineaAnitaMapper::valoresInsert($ctx, $linea, $orden),
            ], 'concmov insert comprobante proveedor');
            $orden++;
        }
    }

    private function deleteConceptos(ComprobanteProveedorAnitaContext $ctx): void
    {
        $lineasErp = [];
        foreach ($ctx->comprobante->comprobante_proveedor_conceptos as $linea) {
            $lineasErp[] = [
                'concepto' => (int) ($linea->concepto_ivacompras?->codigo ?? 0),
                'importe' => (float) $linea->monto,
            ];
        }

        $api = new ApiAnita;
        $raw = $api->apiCall([
            'acc' => 'list',
            'sistema' => self::SISTEMA_COMPRAS,
            'tabla' => 'concmov',
            'campos' => 'concv_nro_interno, concv_concepto, concv_importe',
            'whereArmado' => ' WHERE concv_nro_interno = '.(int) $ctx->nroInterno,
        ]);
        $filas = ApiAnita::decodificarListaFilas($raw);
        $lineasConcmov = [];
        foreach ($filas as $fila) {
            $a = (array) $fila;
            $lineasConcmov[] = [
                'concepto' => (int) ($a['concv_concepto'] ?? 0),
                'importe' => (float) ($a['concv_importe'] ?? 0),
            ];
        }

        $part = ComprobanteProveedorConcmovPertenenciaSupport::particionar($lineasErp, $lineasConcmov);
        if (! $part['ok']) {
            throw new RuntimeException(
                'No se borra concmov del interno '.$ctx->nroInterno.': '.$part['error']
            );
        }

        foreach ($part['de_erp'] as $linea) {
            $this->apiDelete(
                'concmov',
                ComprobanteProveedorConcmovPertenenciaSupport::whereBorrarLinea(
                    $ctx->nroInterno,
                    (int) $linea['concepto'],
                    (float) $linea['importe']
                )
            );
        }
    }

    private function syncPromov(ComprobanteProveedorAnitaContext $ctx): void
    {
        $api = new ApiAnita;
        foreach ($ctx->comprobante->comprobante_proveedor_cuotas as $cuota) {
            $api->apiCallEscritura([
                'acc' => 'insert',
                'tabla' => 'promov',
                'sistema' => self::SISTEMA_COMPRAS,
                'campos' => PromovCuotaAnitaMapper::camposInsert(),
                'valores' => PromovCuotaAnitaMapper::valoresInsert($ctx, $cuota),
            ], 'promov insert comprobante proveedor');
        }
    }

    private function deletePromov(ComprobanteProveedorAnitaContext $ctx): void
    {
        $this->apiDelete('promov', $ctx->claveWherePromov());
    }

    private function syncAplicped(ComprobanteProveedorAnitaContext $ctx): void
    {
        $clavePep = AplicpedFacturaAnitaMapper::clavePepDesdeContexto($ctx);
        if ($clavePep === null) {
            return;
        }

        $claveFactura = AplicpedFacturaAnitaMapper::claveFactura($ctx);
        if ($claveFactura['tipo'] === '' || $claveFactura['nro'] <= 0) {
            return;
        }

        $tabla = (string) config('recepcion_proveedor.anita.tablas.aplicacion_oc', 'aplicped');
        $api = new ApiAnita;
        $codigoProveedor = $ctx->proveedorCodigo();
        $comprobante = $ctx->comprobante;
        $comprobante->loadMissing(['ordencompras.ordencompra_articulos']);

        if (ComprobanteProveedorFacturaAnticipadaSupport::aplica($comprobante)) {
            $insert = RecepcionProveedorAnitaEscrituraSupport::aplicpedFacturaAnticipadaInsert(
                $codigoProveedor,
                $claveFactura,
                $clavePep,
                (int) $ctx->nroInterno,
            );
            $api->apiCallEscritura([
                'acc' => 'insert',
                'tabla' => $tabla,
                'sistema' => self::SISTEMA_COMPRAS,
                'campos' => $insert['campos'],
                'valores' => $insert['valores'],
            ], 'aplicped insert factura anticipada');

            return;
        }

        foreach (AplicpedFacturaAnitaMapper::lineas($comprobante) as $linea) {
            // aplp_nro_interno = com_nro_interno de la factura (no penvp de la OC).
            $insert = RecepcionProveedorAnitaEscrituraSupport::aplicpedFacturaLineaInsert(
                $codigoProveedor,
                $claveFactura,
                $clavePep,
                (int) $linea['orden_com'],
                (int) $linea['penvp_orden'],
                (string) $linea['sku'],
                (float) $linea['cantidad'],
                (int) $ctx->nroInterno,
            );
            $api->apiCallEscritura([
                'acc' => 'insert',
                'tabla' => $tabla,
                'sistema' => self::SISTEMA_COMPRAS,
                'campos' => $insert['campos'],
                'valores' => $insert['valores'],
            ], 'aplicped insert comprobante proveedor');
        }
    }

    private function deleteAplicped(ComprobanteProveedorAnitaContext $ctx): void
    {
        $claveFactura = AplicpedFacturaAnitaMapper::claveFactura($ctx);
        if ($claveFactura['tipo'] === '' || $claveFactura['nro'] <= 0) {
            return;
        }

        $tabla = (string) config('recepcion_proveedor.anita.tablas.aplicacion_oc', 'aplicped');
        $this->apiDelete(
            $tabla,
            RecepcionProveedorAnitaWhereSupport::aplicpedCom($ctx->proveedorCodigo(), $claveFactura)
        );
    }

    private function apiDelete(string $tabla, string $whereArmado): void
    {
        $api = new ApiAnita;
        $api->apiCallEscritura([
            'acc' => 'delete',
            'tabla' => $tabla,
            'sistema' => self::SISTEMA_COMPRAS,
            'whereArmado' => $whereArmado,
        ], "{$tabla} delete comprobante proveedor");
    }
}
