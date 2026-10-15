<?php

namespace App\Services\Configuracion\Handlers;

use App\Contracts\Configuracion\ModuloAvisoDespachoHandlerInterface;
use App\Mail\Configuracion\ModuloAvisoMail;
use App\Models\Compras\Proveedor;
use App\Models\Configuracion\ModuloAvisoTipo;
use App\Services\Configuracion\ModuloAvisoService;
use App\Support\Navegacion\ModoConsultaUrlSupport;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Aviso al suspender un proveedor desde el padrón ARCA del ABM.
 */
class ComprasProveedorPadronArcaSuspensionAvisoHandler implements ModuloAvisoDespachoHandlerInterface
{
    public function __construct(
        private readonly ModuloAvisoService $moduloAvisoService,
    ) {}

    public function despachar(ModuloAvisoTipo $tipo, int $entityId, array $opciones = []): void
    {
        $emails = $this->moduloAvisoService->resolverEmailsDestinatarios($tipo, $this->contextoFiltro($entityId));
        $emails = array_values(array_unique(array_filter(array_map(
            static fn ($email) => strtolower(trim((string) $email)),
            $emails
        ))));

        if ($emails === []) {
            Log::info('ProveedorPadronArcaSuspensionAviso: sin destinatarios', [
                'proveedor_id' => $entityId,
            ]);

            return;
        }

        $placeholders = $this->placeholders($entityId);
        foreach (['motivo', 'mensaje', 'detalle'] as $clave) {
            $valor = trim((string) ($opciones[$clave] ?? ''));
            if ($valor !== '') {
                $placeholders[$clave] = $valor;
            }
        }

        $linkConsulta = $tipo->incluir_link_consulta ? $this->linkConsulta($entityId) : null;
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
                Log::warning('ProveedorPadronArcaSuspensionAviso: error envío', [
                    'proveedor_id' => $entityId,
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
        $proveedor = Proveedor::query()
            ->with(['tipossuspensionproveedores:id,nombre', 'condicionivas:id,nombre'])
            ->find($entityId);

        $usuario = auth()->user();
        $quien = $usuario
            ? trim((string) ($usuario->nombre ?? $usuario->usuario ?? ''))
            : '';

        if ($proveedor === null) {
            return [
                'codigo' => (string) $entityId,
                'nombre' => '',
                'cuit' => '',
                'condicioniva' => '',
                'motivo' => 'Sin motivo',
                'mensaje' => '',
                'detalle' => '',
                'usuario' => $quien !== '' ? $quien : '—',
                'fecha' => now()->format('d/m/Y H:i'),
            ];
        }

        return [
            'codigo' => (string) ($proveedor->codigo ?? $entityId),
            'nombre' => (string) ($proveedor->nombre ?? ''),
            'cuit' => (string) ($proveedor->nroinscripcion ?? ''),
            'condicioniva' => (string) (optional($proveedor?->condicionivas)->nombre ?? ''),
            'motivo' => (string) (optional($proveedor?->tipossuspensionproveedores)->nombre ?? 'Sin motivo'),
            'mensaje' => '',
            'detalle' => '',
            'usuario' => $quien !== '' ? $quien : '—',
            'fecha' => now()->format('d/m/Y H:i'),
        ];
    }

    public function linkConsulta(int $entityId): ?string
    {
        return ModoConsultaUrlSupport::route('editar_proveedor', ['id' => $entityId]);
    }

    public function generarPdf(int $entityId): ?array
    {
        return null;
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
