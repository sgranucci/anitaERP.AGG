<?php

namespace App\Services\Configuracion\Handlers;

use App\Contracts\Configuracion\ModuloAvisoDespachoHandlerInterface;
use App\Mail\Configuracion\ModuloAvisoMail;
use App\Models\Configuracion\ModuloAvisoTipo;
use App\Services\Configuracion\ModuloAvisoService;
use App\Support\Compras\OrdencompraAlertasAbiertasSupport;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Digest diario de OC abiertas (cron compras:alertas-ordencompra-abiertas).
 */
class ComprasOrdencompraAlertasAbiertasAvisoHandler implements ModuloAvisoDespachoHandlerInterface
{
    public function __construct(
        private readonly ModuloAvisoService $moduloAvisoService,
    ) {
    }

    public function despachar(ModuloAvisoTipo $tipo, int $entityId, array $opciones = []): void
    {
        $alertas = $opciones['alertas'] ?? null;
        if (! is_array($alertas) || ! OrdencompraAlertasAbiertasSupport::hayAlertas($alertas)) {
            return;
        }

        $emails = $opciones['emails'] ?? null;
        if (! is_array($emails) || $emails === []) {
            $filtro = [
                'empresa_id' => $opciones['empresa_id'] ?? null,
                'centrocosto_id' => null,
            ];
            $emails = $this->moduloAvisoService->resolverEmailsDestinatarios($tipo, $filtro);
        }

        $emails = array_values(array_unique(array_filter(array_map(
            static fn ($e) => strtolower(trim((string) $e)),
            $emails
        ))));
        if ($emails === []) {
            return;
        }

        $placeholders = $this->placeholdersDesdeAlertas($alertas);
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
                Log::warning('ComprasOrdencompraAlertasAbiertasAviso: error envío', [
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
        return $this->placeholdersDesdeAlertas(
            OrdencompraAlertasAbiertasSupport::recopilar()
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
     * @param  array<string, mixed>  $alertas
     * @return array<string, string>
     */
    private function placeholdersDesdeAlertas(array $alertas): array
    {
        $dias = (int) ($alertas['dias_sin_recepcion'] ?? config('compras.oc_alertas_abiertas.dias_sin_recepcion', 7));

        return [
            'fecha' => (string) ($alertas['fecha'] ?? now()->format('d/m/Y')),
            'dias_sin_recepcion' => (string) $dias,
            'cantidad_sin_recepcion' => (string) ((int) ($alertas['total_sin_recepcion'] ?? 0)),
            'cantidad_parciales' => (string) ((int) ($alertas['total_parciales'] ?? 0)),
            'cantidad_vencidas' => (string) ((int) ($alertas['total_vencidas'] ?? 0)),
            'cantidad_saldos_pendientes' => (string) ((int) ($alertas['total_saldos_pendientes'] ?? 0)),
            'oc_sin_recepcion' => OrdencompraAlertasAbiertasSupport::formatearLista(
                $alertas['sin_recepcion'] ?? [],
                (int) ($alertas['total_sin_recepcion'] ?? 0)
            ),
            'oc_parcialmente_recibidas' => OrdencompraAlertasAbiertasSupport::formatearLista(
                $alertas['parciales'] ?? [],
                (int) ($alertas['total_parciales'] ?? 0)
            ),
            'oc_vencidas' => OrdencompraAlertasAbiertasSupport::formatearLista(
                $alertas['vencidas'] ?? [],
                (int) ($alertas['total_vencidas'] ?? 0)
            ),
            'saldos_pendientes' => OrdencompraAlertasAbiertasSupport::formatearLista(
                $alertas['saldos_pendientes'] ?? [],
                (int) ($alertas['total_saldos_pendientes'] ?? 0)
            ),
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
