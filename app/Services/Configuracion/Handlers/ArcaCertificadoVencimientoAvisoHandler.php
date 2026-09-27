<?php

namespace App\Services\Configuracion\Handlers;

use App\Contracts\Configuracion\ModuloAvisoDespachoHandlerInterface;
use App\Mail\Configuracion\ModuloAvisoMail;
use App\Models\Configuracion\ModuloAvisoTipo;
use App\Services\Configuracion\ModuloAvisoService;
use App\Support\Arca\ArcaCertificadoVencimientoAvisoSupport;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Aviso de certificados ARCA por vencer o vencidos.
 * El cron arma la lista; acá se renderiza con los destinatarios del módulo de avisos.
 */
class ArcaCertificadoVencimientoAvisoHandler implements ModuloAvisoDespachoHandlerInterface
{
    public function __construct(
        private readonly ModuloAvisoService $moduloAvisoService,
    ) {
    }

    public function despachar(ModuloAvisoTipo $tipo, int $entityId, array $opciones = []): void
    {
        $certificados = $opciones['certificados'] ?? null;
        if (! is_array($certificados) || $certificados === []) {
            return;
        }

        $emails = $this->moduloAvisoService->resolverEmailsDestinatarios($tipo, [
            'empresa_id' => null,
            'centrocosto_id' => null,
        ]);
        if ($emails === []) {
            Log::info('ArcaCertificadoVencimientoAviso: sin destinatarios activos', [
                'tipo_id' => $tipo->id,
            ]);

            return;
        }

        $placeholders = $this->placeholdersDesde($certificados);
        $linkConsulta = $tipo->incluir_link_consulta ? $this->linkConsulta(0) : null;
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
                Log::error('ArcaCertificadoVencimientoAviso: falló envío', [
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
        return $this->placeholdersDesde([]);
    }

    public function linkConsulta(int $entityId): ?string
    {
        return route('certificados_arca');
    }

    public function generarPdf(int $entityId): ?array
    {
        return null;
    }

    /**
     * @param  list<array{empresa:string,servicios:string,alias:string,cuit:string,vence:string,dias:int,estado:string}>  $certificados
     * @return array<string, string>
     */
    private function placeholdersDesde(array $certificados): array
    {
        $vencidos = 0;
        foreach ($certificados as $fila) {
            if ((int) ($fila['dias'] ?? 0) <= 0) {
                $vencidos++;
            }
        }

        return [
            'fecha' => now()->timezone('America/Argentina/Buenos_Aires')->format('d/m/Y'),
            'cantidad' => (string) count($certificados),
            'cantidad_vencidos' => (string) $vencidos,
            'certificados' => ArcaCertificadoVencimientoAvisoSupport::formatearLista($certificados),
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
