<?php

namespace App\Services\Compras;

use App\ApiAnita;
use App\Models\Compras\Comprobante_Proveedor;
use App\Support\Compras\AnitaSync\ComprobanteProveedor\CompraCabeceraAnitaMapper;
use App\Support\Compras\AnitaSync\ComprobanteProveedor\ComprobanteProveedorAnitaContext;
use App\Support\Compras\AnitaSync\ComprobanteProveedor\ComprobanteProveedorAnitaNroInternoSupport;
use App\Support\Compras\AnitaSync\ComprobanteProveedor\ConcmovLineaAnitaMapper;
use App\Support\Compras\AnitaSync\ComprobanteProveedor\PromovCuotaAnitaMapper;
use App\Support\Compras\ComprobanteProveedorAnitaSyncEstado;
use RuntimeException;

/**
 * Sincroniza comprobante ERP → Anita (compra, concmov, promov).
 * Contabilidad: ctamov vía AsientoRepository (como facturación), no subdiario Anita.
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
            'empresas', 'proveedores.condicionivas', 'proveedores.provincias',
            'proveedor_condicioniva_eventual', 'condicionpagos',
            'tipotransaccion_compras', 'monedas', 'ordencompras',
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
            'empresas', 'proveedores.condicionivas', 'proveedores.provincias',
            'proveedor_condicioniva_eventual', 'condicionpagos',
            'tipotransaccion_compras', 'monedas', 'ordencompras',
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

        $comprobante->loadMissing(['proveedores', 'tipotransaccion_compras']);
        $ctx = new ComprobanteProveedorAnitaContext($comprobante, (int) $comprobante->anita_nro_interno);

        $this->apiDelete('promov', $ctx->claveWherePromov());
        $this->apiDelete('concmov', " WHERE concv_nro_interno = '{$ctx->nroInterno}' ");
        $this->apiDelete('compra', $ctx->claveWhereCompra());
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
        $this->apiDelete('concmov', " WHERE concv_nro_interno = '{$ctx->nroInterno}' ");
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
