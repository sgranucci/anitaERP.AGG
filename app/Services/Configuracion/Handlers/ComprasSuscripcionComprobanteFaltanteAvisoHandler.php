<?php

namespace App\Services\Configuracion\Handlers;

use App\Contracts\Configuracion\ModuloAvisoDespachoHandlerInterface;
use App\Mail\Configuracion\ModuloAvisoMail;
use App\Models\Configuracion\ModuloAvisoTipo;
use App\Services\Configuracion\ModuloAvisoService;
use App\Support\Compras\SuscripcionComprobantePendienteSupport;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Digest de facturas de portal de suscripción pendientes (dueño o escalamiento).
 */
class ComprasSuscripcionComprobanteFaltanteAvisoHandler implements ModuloAvisoDespachoHandlerInterface
{
    public function __construct(
        private readonly ModuloAvisoService $moduloAvisoService,
    ) {}

    public function despachar(ModuloAvisoTipo $tipo, int $entityId, array $opciones = []): void
    {
        $digest = $opciones['digest'] ?? null;
        if (! is_array($digest) || ! SuscripcionComprobantePendienteSupport::hayPendientes($digest)) {
            return;
        }

        $emails = $opciones['emails'] ?? null;
        if (! is_array($emails) || $emails === []) {
            $emails = $this->moduloAvisoService->resolverEmailsDestinatarios($tipo, [
                'empresa_id' => $opciones['empresa_id'] ?? null,
                'centrocosto_id' => null,
            ]);
        }

        $emails = array_values(array_unique(array_filter(array_map(
            static fn ($e) => strtolower(trim((string) $e)),
            $emails
        ))));
        if ($emails === []) {
            return;
        }

        $placeholders = $this->placeholdersDesdeDigest($digest);
        $linkConsulta = $tipo->incluir_link_consulta ? $this->linkConsulta(0) : null;
        $asunto = $this->aplicarPlaceholders((string) $tipo->mail_asunto, $placeholders, $linkConsulta);
        $texto = $this->aplicarPlaceholders((string) ($tipo->mail_texto ?? ''), $placeholders, $linkConsulta);

        foreach ($emails as $email) {
            if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                continue;
            }
            try {
                $mailable = new ModuloAvisoMail($asunto, $texto, $tipo->nombre, $linkConsulta, null);
                if (! empty($tipo->mail_remitente)) {
                    $mailable->from($tipo->mail_remitente);
                }
                Mail::to($email)->queue($mailable);
            } catch (\Throwable $e) {
                Log::warning('SuscripcionComprobanteFaltanteAviso: error envío', [
                    'email' => $email,
                    'codigo' => $tipo->codigo,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    public function contextoFiltro(int $entityId): array
    {
        return ['empresa_id' => null, 'centrocosto_id' => null];
    }

    public function placeholders(int $entityId): array
    {
        return [
            'fecha' => now()->format('d/m/Y'),
            'cantidad' => '0',
            'pendientes' => '(sin ítems)',
        ];
    }

    public function linkConsulta(int $entityId): ?string
    {
        return url('compras/suscripciones/comprobantes-pendientes');
    }

    public function generarPdf(int $entityId): ?array
    {
        return null;
    }

    /**
     * @param  array<string, mixed>  $digest
     * @return array<string, string>
     */
    private function placeholdersDesdeDigest(array $digest): array
    {
        $cantidad = (int) ($digest['cantidad'] ?? 0);

        return [
            'fecha' => (string) ($digest['fecha'] ?? now()->format('d/m/Y')),
            'cantidad' => (string) $cantidad,
            'pendientes' => SuscripcionComprobantePendienteSupport::formatearLista(
                $digest['filas_mail'] ?? [],
                $cantidad
            ),
        ];
    }

    /** @param  array<string, string>  $placeholders */
    private function aplicarPlaceholders(string $plantilla, array $placeholders, ?string $linkConsulta): string
    {
        $mapa = array_merge($placeholders, ['link_consulta' => $linkConsulta ?? '']);

        return (string) preg_replace_callback('/\{([a-z0-9_]+)\}/i', function (array $m) use ($mapa) {
            return $mapa[$m[1]] ?? $m[0];
        }, $plantilla);
    }
}
