<?php

namespace App\Services\Configuracion\Handlers;

use App\Contracts\Configuracion\ModuloAvisoDespachoHandlerInterface;
use App\Mail\Configuracion\ModuloAvisoMail;
use App\Models\Compras\Requisicion;
use App\Models\Configuracion\ModuloAvisoTipo;
use App\Services\Configuracion\ModuloAvisoService;
use App\Support\Navegacion\ModoConsultaUrlSupport;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Aviso de cumplimiento de requisición de compra al generador de la requisición
 * (más los destinatarios configurados en el módulo de avisos).
 */
class ComprasRequisicionCumplidaAvisoDespachoHandler implements ModuloAvisoDespachoHandlerInterface
{
    public function __construct(
        private readonly ModuloAvisoService $moduloAvisoService,
    ) {
    }

    public function despachar(ModuloAvisoTipo $tipo, int $entityId, array $opciones = []): void
    {
        $req = $this->cargar($entityId);
        if (! $req) {
            return;
        }

        $filtro = $this->contextoFiltro($entityId);
        $emails = $this->moduloAvisoService->resolverEmailsDestinatarios($tipo, $filtro);

        // Generador de la requisición (creousuario).
        $emailGenerador = strtolower(trim((string) ($req->usuarios?->email ?? '')));
        if ($emailGenerador !== '' && filter_var($emailGenerador, FILTER_VALIDATE_EMAIL)) {
            $emails[] = $emailGenerador;
        }

        $emails = array_values(array_unique(array_filter($emails)));
        if ($emails === []) {
            return;
        }

        $placeholders = $this->placeholders($entityId);
        $placeholders['cumplimiento_numero'] = (string) ($opciones['cumplimiento_numero'] ?? '');
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
                Log::warning('ComprasRequisicionCumplidaAviso: error envío', [
                    'email' => $email,
                    'requisicion_id' => $entityId,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    public function contextoFiltro(int $entityId): array
    {
        $req = $this->cargar($entityId);

        return [
            'empresa_id' => $req && $req->empresa_id ? (int) $req->empresa_id : null,
            'centrocosto_id' => $req && $req->centrocosto_id ? (int) $req->centrocosto_id : null,
        ];
    }

    public function placeholders(int $entityId): array
    {
        $req = $this->cargar($entityId);
        if (! $req) {
            return [];
        }
        $cc = $req->centrocostos;

        return [
            'numero' => (string) ($req->numerorequisicion ?? $entityId),
            'solicitante' => (string) ($req->usuarios?->nombre ?? '—'),
            'empresa' => (string) ($req->empresas?->nombre ?? '—'),
            'centro_costo' => trim(($cc->codigo ?? '').' '.($cc->nombre ?? '')) ?: '—',
            'fecha' => $req->fecha ? \Carbon\Carbon::parse($req->fecha)->format('d/m/Y') : '—',
            'estado' => (string) ($req->estado ?? '—'),
        ];
    }

    public function linkConsulta(int $entityId): ?string
    {
        return ModoConsultaUrlSupport::urlAbsolutaConConsulta('compras/requisicion/'.$entityId.'/editar');
    }

    public function generarPdf(int $entityId): ?array
    {
        return null;
    }

    private function cargar(int $entityId): ?Requisicion
    {
        return Requisicion::query()
            ->with(['empresas', 'centrocostos', 'usuarios'])
            ->find($entityId);
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
