<?php

namespace App\Services\Configuracion\Handlers;

use App\Contracts\Configuracion\ModuloAvisoDespachoHandlerInterface;
use App\Mail\Configuracion\ModuloAvisoMail;
use App\Models\Configuracion\ModuloAvisoTipo;
use App\Services\Configuracion\ModuloAvisoService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Resumen de empleados vigentes que deben la libreta sanitaria.
 * El cron arma la lista; los destinatarios se editan en Avisos por módulo.
 */
class SueldosLibretaSanitariaAvisoHandler implements ModuloAvisoDespachoHandlerInterface
{
    public function __construct(
        private readonly ModuloAvisoService $moduloAvisoService,
    ) {
    }

    public function despachar(ModuloAvisoTipo $tipo, int $entityId, array $opciones = []): void
    {
        $cantidad = (int) ($opciones['cantidad'] ?? 0);
        if ($cantidad <= 0) {
            return;
        }

        $emails = $this->moduloAvisoService->resolverEmailsDestinatarios($tipo, $this->contextoFiltro($entityId));
        if ($emails === []) {
            Log::info('SueldosLibretaSanitariaAviso: sin destinatarios activos', [
                'tipo_id' => $tipo->id,
            ]);

            return;
        }

        $placeholders = array_merge($this->placeholders($entityId), [
            'fecha' => (string) ($opciones['fecha'] ?? now()->timezone('America/Argentina/Buenos_Aires')->format('d/m/Y')),
            'cantidad' => (string) $cantidad,
            'empleados' => (string) ($opciones['empleados'] ?? ''),
        ]);
        $linkConsulta = $tipo->incluir_link_consulta ? $this->linkConsulta($entityId) : null;
        $asunto = $this->aplicarPlaceholders((string) $tipo->mail_asunto, $placeholders, $linkConsulta);
        $texto = $this->aplicarPlaceholders((string) ($tipo->mail_texto ?? ''), $placeholders, $linkConsulta);

        foreach ($emails as $email) {
            try {
                $mailable = new ModuloAvisoMail($asunto, $texto, $tipo->nombre, $linkConsulta, null);
                if (! empty($tipo->mail_remitente)) {
                    $mailable->from($tipo->mail_remitente);
                }
                Mail::to($email)->queue($mailable);
            } catch (\Throwable $e) {
                Log::error('SueldosLibretaSanitariaAviso: falló envío', [
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
        return [
            'fecha' => now()->timezone('America/Argentina/Buenos_Aires')->format('d/m/Y'),
            'cantidad' => '0',
            'empleados' => '',
        ];
    }

    public function linkConsulta(int $entityId): ?string
    {
        return route('consultar_empleado_sueldos');
    }

    public function generarPdf(int $entityId): ?array
    {
        return null;
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
