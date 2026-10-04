<?php

namespace App\Services\Configuracion\Handlers;

use App\Contracts\Configuracion\ModuloAvisoDespachoHandlerInterface;
use App\Mail\Configuracion\ModuloAvisoMail;
use App\Models\Configuracion\ModuloAvisoTipo;
use App\Services\Configuracion\ModuloAvisoService;
use App\Support\Compras\OrdencompraEntregaDiaSinRecepcionSupport;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Digest de artículos con entrega en el día y sin recepción que la cubra
 * (cron compras:avisar-entrega-dia-sin-recepcion).
 */
class ComprasEntregaDiaSinRecepcionAvisoHandler implements ModuloAvisoDespachoHandlerInterface
{
    public function __construct(
        private readonly ModuloAvisoService $moduloAvisoService,
    ) {
    }

    public function despachar(ModuloAvisoTipo $tipo, int $entityId, array $opciones = []): void
    {
        $resumen = $opciones['resumen'] ?? null;
        if (! is_array($resumen) || ! OrdencompraEntregaDiaSinRecepcionSupport::hayPendientes($resumen)) {
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

        $placeholders = $this->placeholdersDesdeResumen($resumen);
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
                Log::warning('ComprasEntregaDiaSinRecepcionAviso: error envío', [
                    'email' => $email,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    public function contextoFiltro(int $entityId): array
    {
        return [
            'empresa_id' => null,
            'centrocosto_id' => null,
        ];
    }

    public function placeholders(int $entityId): array
    {
        return $this->placeholdersDesdeResumen(
            OrdencompraEntregaDiaSinRecepcionSupport::recopilar()
        );
    }

    public function linkConsulta(int $entityId): ?string
    {
        return url('compras/ordencompra-reporte');
    }

    public function generarPdf(int $entityId): ?array
    {
        return null;
    }

    /**
     * @param  array<string, mixed>  $resumen
     * @return array<string, string>
     */
    private function placeholdersDesdeResumen(array $resumen): array
    {
        $items = is_array($resumen['items'] ?? null) ? $resumen['items'] : [];
        $total = (int) ($resumen['total'] ?? count($items));

        return [
            'fecha' => (string) ($resumen['fecha'] ?? now()->format('d/m/Y')),
            'cantidad' => (string) $total,
            'articulos' => OrdencompraEntregaDiaSinRecepcionSupport::formatearLista($items, $total),
        ];
    }

    /** @param  array<string, string>  $placeholders */
    private function aplicarPlaceholders(string $plantilla, array $placeholders, ?string $linkConsulta): string
    {
        $mapa = array_merge($placeholders, ['link_consulta' => $linkConsulta ?? '']);
        $resultado = preg_replace_callback('/\{([a-z0-9_]+)\}/i', function (array $m) use ($mapa) {
            return $mapa[strtolower($m[1])] ?? $m[0];
        }, $plantilla);

        return is_string($resultado) ? $resultado : $plantilla;
    }
}
