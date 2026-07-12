<?php

namespace App\Services\Configuracion\Handlers;

use App\Contracts\Configuracion\ModuloAvisoDespachoHandlerInterface;
use App\Mail\Configuracion\ModuloAvisoMail;
use App\Models\Configuracion\ModuloAvisoTipo;
use App\Repositories\Stock\Recepcion_ProveedorRepositoryInterface;
use App\Support\Stock\RecepcionProveedorEnlacePublicoSupport;
use App\Support\Stock\RecepcionProveedorEncuestaSupport;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Aviso al usuario que cargó la recepción cuando compras liberó el cambio de precio.
 * Enlace público con token (sin login ERP), según política OperacionPublicaTokenSupport.
 */
class StockRecepcionProveedorPrecioLiberadoAvisoHandler implements ModuloAvisoDespachoHandlerInterface
{
    public function __construct(
        private readonly Recepcion_ProveedorRepositoryInterface $repository,
    ) {
    }

    public function despachar(ModuloAvisoTipo $tipo, int $entityId, array $opciones = []): void
    {
        $rec = $this->repository->find($entityId);
        $rec->loadMissing(['proveedores', 'creousuarios', 'ordencompras']);

        $email = trim((string) (optional($rec->creousuarios)->email ?? ''));
        if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            Log::info('RecepcionProveedorPrecioLiberado: usuario sin email', ['recepcion_id' => $entityId]);

            return;
        }

        $usuarioId = (int) ($rec->creousuario_id ?? 0);
        $placeholders = $this->placeholders($entityId);
        $linkConsulta = $tipo->incluir_link_consulta
            ? RecepcionProveedorEnlacePublicoSupport::urlConsultaMail(
                $entityId,
                $usuarioId > 0 ? $usuarioId : null
            )
            : null;

        $asunto = $this->aplicarPlaceholders((string) $tipo->mail_asunto, $placeholders, $linkConsulta);
        $texto = $this->aplicarPlaceholders((string) ($tipo->mail_texto ?? ''), $placeholders, $linkConsulta);

        try {
            $mailable = new ModuloAvisoMail(
                $asunto,
                $texto,
                $tipo->nombre,
                $linkConsulta,
                null
            );
            if (! empty($tipo->mail_remitente)) {
                $mailable->from($tipo->mail_remitente);
            }
            Mail::to($email)->send($mailable);
        } catch (\Throwable $e) {
            Log::warning('RecepcionProveedorPrecioLiberado: error envío', [
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
            'usuario_recepcion' => (string) (optional($rec->creousuarios)->nombre ?? '—'),
        ];
    }

    public function linkConsulta(int $entityId): ?string
    {
        $rec = $this->repository->find($entityId);
        $usuarioId = (int) ($rec->creousuario_id ?? 0);

        return RecepcionProveedorEnlacePublicoSupport::urlConsultaMail(
            $entityId,
            $usuarioId > 0 ? $usuarioId : null
        );
    }

    public function generarPdf(int $entityId): ?array
    {
        return null;
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
