<?php

namespace App\Services\Configuracion\Handlers;

use App\Contracts\Configuracion\ModuloAvisoHandlerInterface;
use App\Repositories\Stock\Recepcion_ProveedorRepositoryInterface;
use App\Services\Stock\RecepcionProveedorPdfService;

class StockRecepcionProveedorAvisoHandler implements ModuloAvisoHandlerInterface
{
    public function __construct(
        private readonly Recepcion_ProveedorRepositoryInterface $repository,
        private readonly RecepcionProveedorPdfService $pdfService,
    ) {
    }

    public function contextoFiltro(int $entityId): array
    {
        $rec = $this->repository->find($entityId);

        return [
            'empresa_id' => $rec->empresa_id ? (int) $rec->empresa_id : null,
            'centrocosto_id' => (int) (optional($rec->ordencompras)->centrocosto_id ?? 0) ?: null,
        ];
    }

    public function placeholders(int $entityId): array
    {
        $rec = $this->repository->find($entityId);
        $oc = $rec->ordencompras;
        $prov = $rec->proveedores;

        return [
            'numero_recepcion' => (string) ($rec->numerorecepcion ?? $entityId),
            'numero_oc' => (string) (optional($oc)->numeroordencompra ?? '—'),
            'proveedor' => (string) (optional($prov)->nombre ?? '—'),
            'comentario_precio' => (string) ($rec->comentario_precio ?? '—'),
            'resumen_diferencias' => (string) ($rec->resumen_diferencias ?? '—'),
            'fecha' => $rec->fecha ? $rec->fecha->format('d/m/Y') : '—',
            'estado' => (string) ($rec->estado ?? '—'),
        ];
    }

    public function linkConsulta(int $entityId): ?string
    {
        return url('stock/recepcion-proveedor/'.$entityId.'/editar');
    }

    public function generarPdf(int $entityId): ?array
    {
        try {
            $doc = $this->pdfService->generarComPdf($entityId);

            return [
                'bytes' => $doc['bytes'],
                'filename' => $doc['filename'],
                'mime' => 'application/pdf',
            ];
        } catch (\Throwable) {
            return null;
        }
    }
}
