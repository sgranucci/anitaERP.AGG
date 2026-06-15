<?php

namespace App\Services\Compras;

use App\ApiAnita;
use App\Models\Compras\Comprobante_Proveedor;
use App\Support\Compras\ComprobanteProveedorAnitaSyncEstado;
use RuntimeException;

/**
 * Sincroniza comprobante ERP → Anita (compra, concmov, promov).
 * Contabilidad: ctamov vía AsientoRepository (como facturación), no subdiario Anita.
 */
class ComprobanteProveedorAnitaSyncService
{
    private const SISTEMA_COMPRAS = 'compras';

    public function syncCreate(Comprobante_Proveedor $comprobante): void
    {
        $comprobante->loadMissing([
            'comprobante_proveedor_conceptos.concepto_ivacompras',
            'empresas', 'proveedores', 'tipotransaccion_compras',
        ]);

        $this->insertCompra($comprobante);
        $this->syncConceptos($comprobante);
        $this->insertPromov($comprobante);

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
            'empresas', 'proveedores', 'tipotransaccion_compras',
        ]);

        if (! $comprobante->anita_nro_interno) {
            $this->syncCreate($comprobante);

            return;
        }

        $this->updateCompra($comprobante);
        $this->deleteConceptos($comprobante);
        $this->syncConceptos($comprobante);
        $this->updatePromov($comprobante);

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

        $prov = str_pad((string) $comprobante->proveedores->codigo, 6, '0', STR_PAD_LEFT);
        $tipo = $comprobante->tipotransaccion_compras->abreviatura;

        $where = " WHERE com_proveedor = '{$prov}'
            AND com_tipo = '{$tipo}'
            AND com_letra = '{$comprobante->letra}'
            AND com_sucursal = '{$comprobante->sucursal}'
            AND com_nro = '{$comprobante->numerocomprobante}'
            AND com_nro_interno = '{$comprobante->anita_nro_interno}' ";

        $this->apiDelete('promov', $where.' AND prov_nro_interno = com_nro_interno');
        $this->apiDelete('concmov', " WHERE concv_nro_interno = '{$comprobante->anita_nro_interno}' ");
        $this->apiDelete('compra', $where);
    }

    private function insertCompra(Comprobante_Proveedor $cp): void
    {
        // Mapper detallado en fase de implementación UI (campos com_* ↔ ERP).
        throw new RuntimeException('ComprobanteProveedorAnitaSyncService::insertCompra pendiente de mapper comercial.');
    }

    private function updateCompra(Comprobante_Proveedor $cp): void
    {
        throw new RuntimeException('ComprobanteProveedorAnitaSyncService::updateCompra pendiente de mapper comercial.');
    }

    private function insertPromov(Comprobante_Proveedor $cp): void
    {
        throw new RuntimeException('ComprobanteProveedorAnitaSyncService::insertPromov pendiente de mapper comercial.');
    }

    private function updatePromov(Comprobante_Proveedor $cp): void
    {
        throw new RuntimeException('ComprobanteProveedorAnitaSyncService::updatePromov pendiente de mapper comercial.');
    }

    private function syncConceptos(Comprobante_Proveedor $cp): void
    {
        foreach ($cp->comprobante_proveedor_conceptos as $linea) {
            // concmov insert vía bridge (concv_*).
            unset($linea);
        }
    }

    private function deleteConceptos(Comprobante_Proveedor $cp): void
    {
        if (! $cp->anita_nro_interno) {
            return;
        }

        $this->apiDelete('concmov', " WHERE concv_nro_interno = '{$cp->anita_nro_interno}' ");
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
