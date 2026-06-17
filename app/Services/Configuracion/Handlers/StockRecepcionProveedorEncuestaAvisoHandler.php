<?php

namespace App\Services\Configuracion\Handlers;

use App\Contracts\Configuracion\ModuloAvisoDespachoHandlerInterface;
use App\Mail\Configuracion\ModuloAvisoMail;
use App\Models\Configuracion\ModuloAvisoTipo;
use App\Repositories\Stock\Recepcion_ProveedorRepositoryInterface;
use App\Services\Stock\RecepcionProveedorPdfService;
use App\Support\Stock\RecepcionProveedorEncuestaSupport;
use App\Support\Stock\RecepcionProveedorRequisicionEmailSupport;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class StockRecepcionProveedorEncuestaAvisoHandler implements ModuloAvisoDespachoHandlerInterface
{
    public function __construct(
        private readonly Recepcion_ProveedorRepositoryInterface $repository,
        private readonly RecepcionProveedorPdfService $pdfService,
    ) {
    }

    public function despachar(ModuloAvisoTipo $tipo, int $entityId, array $opciones = []): void
    {
        if (! config('recepcion_proveedor.encuesta_habilitada', true)) {
            return;
        }

        $rec = $this->repository->find($entityId);
        $rec->loadMissing([
            'proveedores',
            'empresas',
            'ordencompras.requisiciones.usuarios',
            'ordencompras.creousuarios',
        ]);

        $email = RecepcionProveedorRequisicionEmailSupport::emailSolicitanteOc($rec);
        if ($email === null || $email === '') {
            Log::info('RecepcionProveedorEncuesta: sin email destinatario', ['recepcion_id' => $entityId]);

            return;
        }

        $link = RecepcionProveedorEncuestaSupport::linkEncuestaProveedor($rec);
        if ($link === null) {
            Log::info('RecepcionProveedorEncuesta: no se pudo armar link', ['recepcion_id' => $entityId]);

            return;
        }

        $placeholders = array_merge($this->placeholders($entityId), ['link_encuesta' => $link]);
        $asunto = $this->aplicarPlaceholders((string) $tipo->mail_asunto, $placeholders);
        $texto = $this->aplicarPlaceholders((string) ($tipo->mail_texto ?? ''), $placeholders);

        $pdfAdjunto = $tipo->adjuntar_pdf ? $this->generarPdf($entityId) : null;

        try {
            Mail::to($email)->send(new ModuloAvisoMail(
                $asunto,
                $texto,
                $tipo->nombre,
                null,
                $pdfAdjunto
            ));
        } catch (\Throwable $e) {
            Log::warning('RecepcionProveedorEncuesta: error envío', [
                'recepcion_id' => $entityId,
                'email' => $email,
                'error' => $e->getMessage(),
            ]);
        }
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
            'fecha' => $rec->fecha ? $rec->fecha->format('d/m/Y') : '—',
            'com_anita' => RecepcionProveedorEncuestaSupport::etiquetaComAnita($rec),
            'link_encuesta' => RecepcionProveedorEncuestaSupport::linkEncuestaProveedor($rec) ?? '—',
        ];
    }

    public function linkConsulta(int $entityId): ?string
    {
        return null;
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

    /** @param array<string, string> $placeholders */
    private function aplicarPlaceholders(string $plantilla, array $placeholders): string
    {
        $texto = $plantilla;
        foreach ($placeholders as $clave => $valor) {
            $texto = str_replace('{'.$clave.'}', $valor, $texto);
        }

        return $texto;
    }
}
