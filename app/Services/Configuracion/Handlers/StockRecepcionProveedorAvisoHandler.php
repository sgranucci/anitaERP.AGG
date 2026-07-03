<?php

namespace App\Services\Configuracion\Handlers;

use App\Contracts\Configuracion\ModuloAvisoHandlerInterface;
use App\Repositories\Stock\Recepcion_ProveedorRepositoryInterface;
use App\Services\Stock\RecepcionProveedorPdfService;
use App\Support\Stock\RecepcionProveedorEncuestaSupport;
use App\Support\Stock\RecepcionProveedorEnlacePublicoSupport;

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

        $rec->loadMissing('recepcion_proveedor_partes_unicas');

        return [
            'numero_recepcion' => (string) ($rec->numerorecepcion ?? $entityId),
            'numero_oc' => (string) (optional($oc)->numeroordencompra ?? '—'),
            'proveedor' => (string) (optional($prov)->nombre ?? '—'),
            'comentario_precio' => (string) ($rec->comentario_precio ?? '—'),
            'resumen_diferencias' => (string) ($rec->resumen_diferencias ?? '—'),
            'fecha' => $rec->fecha ? $rec->fecha->format('d/m/Y') : '—',
            'estado' => (string) ($rec->estado ?? '—'),
            'com_anita' => RecepcionProveedorEncuestaSupport::etiquetaComAnita($rec),
            'cantidad_partes_unicas' => (string) $rec->recepcion_proveedor_partes_unicas->count(),
            'resumen_rechazos' => (string) ($rec->resumen_rechazos ?? '—'),
        ];
    }

    public function linkConsulta(int $entityId): ?string
    {
        return RecepcionProveedorEnlacePublicoSupport::urlConsultaMail($entityId);
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
