<?php

namespace App\Services\Configuracion\Handlers;

use App\Contracts\Configuracion\ModuloAvisoDespachoHandlerInterface;
use App\Mail\Configuracion\ModuloAvisoMail;
use App\Models\Configuracion\ModuloAvisoTipo;
use App\Models\Ventas\Cliente;
use App\Services\Configuracion\ModuloAvisoService;
use App\Support\Navegacion\ModoConsultaUrlSupport;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Cliente retenido / problemas AFIP-ARCA → mail al vendedor (+ CC del ABM si hay).
 */
class VentasClienteRetenidoAfipAvisoDespachoHandler implements ModuloAvisoDespachoHandlerInterface
{
    public function __construct(
        private readonly ModuloAvisoService $moduloAvisoService,
    ) {
    }

    public function despachar(ModuloAvisoTipo $tipo, int $entityId, array $opciones = []): void
    {
        $emails = $this->resolverEmails($tipo, $entityId);
        if ($emails === []) {
            Log::info('ClienteRetenidoAfipAviso: sin destinatarios', ['cliente_id' => $entityId]);

            return;
        }

        $placeholders = $this->placeholders($entityId);
        foreach (['motivo', 'detalle'] as $clave) {
            $valor = trim((string) ($opciones[$clave] ?? ''));
            if ($valor !== '') {
                $placeholders[$clave] = $valor;
            }
        }

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
                Log::warning('ClienteRetenidoAfipAviso: error envío', [
                    'cliente_id' => $entityId,
                    'email' => $email,
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
        $cliente = Cliente::query()
            ->with(['vendedores:id,codigo,nombre,email'])
            ->find($entityId);

        return [
            'codigo' => (string) ($cliente->codigo ?? $entityId),
            'cliente' => (string) ($cliente->nombre ?? ''),
            'nombre' => (string) ($cliente->nombre ?? ''),
            'vendedor' => (string) (optional($cliente?->vendedores)->nombre ?? '—'),
            'motivo' => 'Problemas de AFIP / padrón ARCA',
            'detalle' => '',
            'fecha' => now()->format('d/m/Y H:i'),
        ];
    }

    public function linkConsulta(int $entityId): ?string
    {
        return ModoConsultaUrlSupport::route('editar_cliente', ['id' => $entityId]);
    }

    public function generarPdf(int $entityId): ?array
    {
        return null;
    }

    /**
     * @return list<string>
     */
    private function resolverEmails(ModuloAvisoTipo $tipo, int $entityId): array
    {
        $emails = $this->moduloAvisoService->resolverEmailsDestinatarios(
            $tipo,
            $this->contextoFiltro($entityId)
        );

        $cliente = Cliente::query()
            ->with(['vendedores:id,email'])
            ->find($entityId);
        $vendorEmail = strtolower(trim((string) (optional($cliente?->vendedores)->email ?? '')));
        if ($vendorEmail !== '' && filter_var($vendorEmail, FILTER_VALIDATE_EMAIL)) {
            $emails[] = $vendorEmail;
        }

        $uniq = [];
        foreach ($emails as $email) {
            $email = strtolower(trim((string) $email));
            if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                continue;
            }
            $uniq[$email] = $email;
        }

        return array_values($uniq);
    }

    /**
     * @param  array<string, string>  $placeholders
     */
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
