<?php

namespace App\Services\Configuracion\Handlers;

use App\Contracts\Configuracion\ModuloAvisoDespachoHandlerInterface;
use App\Mail\Configuracion\ModuloAvisoMail;
use App\Models\Configuracion\ModuloAvisoTipo;
use App\Models\Stock\Recepcion_Proveedor;
use App\Repositories\Stock\Recepcion_ProveedorRepositoryInterface;
use App\Services\Configuracion\ModuloAvisoService;
use App\Services\Stock\RecepcionProveedorPdfService;
use App\Support\Stock\RecepcionProveedorEncuestaSupport;
use App\Support\Stock\RecepcionProveedorEnlacePublicoSupport;
use App\Support\Stock\RecepcionProveedorRequisicionEmailSupport;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class StockRecepcionProveedorIngresadaAvisoHandler implements ModuloAvisoDespachoHandlerInterface
{
    public function __construct(
        private readonly Recepcion_ProveedorRepositoryInterface $repository,
        private readonly RecepcionProveedorPdfService $pdfService,
        private readonly ModuloAvisoService $moduloAvisoService,
    ) {
    }

    public function despachar(ModuloAvisoTipo $tipo, int $entityId, array $opciones = []): void
    {
        $rec = $this->repository->find($entityId);
        $rec->loadMissing([
            'proveedores',
            'creousuarios',
            'recepcion_proveedor_articulos.articulos',
            'ordencompras',
        ]);

        $filtro = $this->contextoFiltro($entityId);
        $emails = $this->moduloAvisoService->resolverEmailsDestinatarios($tipo, $filtro);

        if (! $rec->fl_laboratorio) {
            $emailReq = RecepcionProveedorRequisicionEmailSupport::emailSolicitanteOc($rec);
            if ($emailReq !== null && $emailReq !== '') {
                $emails[] = strtolower($emailReq);
            }
        }

        $emails = array_values(array_unique(array_filter($emails)));

        if ($emails === []) {
            Log::info('RecepcionProveedorIngresada: sin destinatarios', ['recepcion_id' => $entityId]);

            return;
        }

        $placeholders = $this->placeholders($entityId);
        $linkConsulta = $tipo->incluir_link_consulta ? $this->linkConsulta($entityId) : null;
        $asunto = $this->aplicarPlaceholders((string) $tipo->mail_asunto, $placeholders, $linkConsulta);
        $texto = $this->aplicarPlaceholders((string) ($tipo->mail_texto ?? ''), $placeholders, $linkConsulta);
        $pdfAdjunto = $tipo->adjuntar_pdf ? $this->generarPdf($entityId) : null;

        foreach ($emails as $email) {
            try {
                $mailable = new ModuloAvisoMail(
                    $asunto,
                    $texto,
                    $tipo->nombre,
                    $linkConsulta,
                    $pdfAdjunto
                );
                if (! empty($tipo->mail_remitente)) {
                    $mailable->from($tipo->mail_remitente);
                }
                Mail::to($email)->send($mailable);
            } catch (\Throwable $e) {
                Log::warning('RecepcionProveedorIngresada: error envío', [
                    'recepcion_id' => $entityId,
                    'email' => $email,
                    'error' => $e->getMessage(),
                ]);
            }
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
        $rec->loadMissing(['proveedores', 'creousuarios', 'recepcion_proveedor_articulos.articulos', 'ordencompras']);
        $oc = $rec->ordencompras;
        $prov = $rec->proveedores;

        return [
            'numero_recepcion' => (string) ($rec->numerorecepcion ?? $entityId),
            'numero_oc' => (string) (optional($oc)->numeroordencompra ?? '—'),
            'proveedor' => (string) (optional($prov)->nombre ?? '—'),
            'fecha' => $rec->fecha ? $rec->fecha->format('d/m/Y') : '—',
            'com_anita' => RecepcionProveedorEncuestaSupport::etiquetaComAnita($rec),
            'usuario_recepcion' => (string) (optional($rec->creousuarios)->nombre ?? '—'),
            'detalle_lineas' => self::detalleLineasTexto($rec),
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

    private static function detalleLineasTexto(Recepcion_Proveedor $rec): string
    {
        $lineas = [];
        foreach ($rec->recepcion_proveedor_articulos as $linea) {
            $articulo = $linea->articulos;
            $sku = trim((string) (optional($articulo)->sku ?? ''));
            $desc = trim((string) (optional($articulo)->descripcion ?? $linea->detalle ?? ''));
            $cant = (float) $linea->cantidad;
            $rech = (float) ($linea->cantidad_rechazada ?? 0);
            $precio = (float) $linea->precio;
            $texto = sprintf(
                '%s %s — rec. %.4f precio %.4f',
                $sku !== '' ? $sku : 'Art.'.$linea->articulo_id,
                $desc !== '' ? $desc : '',
                $cant,
                $precio
            );
            if ($rech > 0.000001) {
                $texto .= sprintf(' (rech. %.4f', $rech);
                $motivo = trim((string) ($linea->motivorechazo ?? ''));
                if ($motivo !== '') {
                    $texto .= ': '.$motivo;
                }
                $texto .= ')';
            }
            $lineas[] = $texto;
        }

        return $lineas !== [] ? implode("\n", $lineas) : '—';
    }

    /** @param array<string, string> $placeholders */
    private function aplicarPlaceholders(string $plantilla, array $placeholders, ?string $linkConsulta): string
    {
        $mapa = array_merge($placeholders, ['link_consulta' => $linkConsulta ?? '']);
        $resultado = preg_replace_callback('/\{([a-z0-9_]+)\}/i', function (array $m) use ($mapa) {
            $clave = strtolower($m[1]);

            return $mapa[$clave] ?? $m[0];
        }, $plantilla);

        return is_string($resultado) ? $resultado : $plantilla;
    }
}
