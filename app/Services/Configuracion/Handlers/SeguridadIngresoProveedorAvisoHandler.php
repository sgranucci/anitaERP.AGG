<?php

namespace App\Services\Configuracion\Handlers;

use App\Contracts\Configuracion\ModuloAvisoDespachoHandlerInterface;
use App\Mail\Configuracion\ModuloAvisoMail;
use App\Models\Configuracion\ModuloAvisoTipo;
use App\Models\Seguridad\IngresoProveedor;
use App\Services\Configuracion\ModuloAvisoService;
use App\Support\Seguridad\IngresoProveedorEnlacePublicoSupport;
use App\Support\Seguridad\IngresoProveedorEstados;
use App\Support\Seguridad\IngresoProveedorVisitanteSupport;
use App\Support\Seguridad\UsuarioOperativoSupport;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SeguridadIngresoProveedorAvisoHandler implements ModuloAvisoDespachoHandlerInterface
{
    public function __construct(private readonly ModuloAvisoService $moduloAvisoService)
    {
    }

    public function despachar(ModuloAvisoTipo $tipo, int $entityId, array $opciones = []): void
    {
        $filtro = $this->contextoFiltro($entityId);
        $emails = $this->moduloAvisoService->resolverEmailsDestinatarios($tipo, $filtro);

        if ($tipo->codigo === 'ingreso_proveedor_rechazado') {
            $creador = $this->emailCreador($entityId);
            if ($creador !== null) {
                $emails[] = $creador;
            }
        }

        $emails = array_values(array_unique(array_filter($emails)));
        if ($emails === []) {
            Log::info('SeguridadIngresoProveedorAvisoHandler: sin destinatarios', [
                'codigo' => $tipo->codigo,
                'entity_id' => $entityId,
            ]);

            return;
        }

        $placeholders = $this->placeholders($entityId);
        $linkConsulta = $tipo->incluir_link_consulta ? $this->linkConsulta($entityId) : null;
        $asunto = $this->aplicar($tipo->mail_asunto ?? '', $placeholders, $linkConsulta);
        $texto = $this->aplicar((string) ($tipo->mail_texto ?? ''), $placeholders, $linkConsulta);

        foreach ($emails as $email) {
            try {
                $mailable = new ModuloAvisoMail($asunto, $texto, $tipo->nombre, $linkConsulta, null);
                if (! empty($tipo->mail_remitente)) {
                    $mailable->from($tipo->mail_remitente);
                }
                Mail::to($email)->queue($mailable);
            } catch (\Throwable $e) {
                Log::error('SeguridadIngresoProveedorAvisoHandler: falló envío', [
                    'email' => $email,
                    'codigo' => $tipo->codigo,
                    'entity_id' => $entityId,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    public function contextoFiltro(int $entityId): array
    {
        $ticket = $this->cargar($entityId);

        return [
            'empresa_id' => (int) ($ticket->empresa_id ?? 0) ?: null,
            'centrocosto_id' => null,
        ];
    }

    public function placeholders(int $entityId): array
    {
        $ticket = $this->cargar($entityId);

        return [
            'id' => (string) $ticket->id,
            'numero' => (string) $ticket->id,
            'titulo' => (string) ($ticket->titulo ?: '—'),
            'proveedor' => IngresoProveedorVisitanteSupport::etiquetaOrigen($ticket),
            'motivo' => (string) (optional($ticket->motivos)->nombre ?? '—'),
            'punto' => (string) (optional($ticket->puntos)->nombre ?? '—'),
            'sector' => (string) (optional($ticket->sectores)->nombre ?? '—'),
            'area' => (string) (optional($ticket->areas)->nombre ?? '—'),
            'usuario' => (string) (optional($ticket->usuarios)->nombre ?? optional($ticket->usuarios)->usuario ?? '—'),
            'comentario' => (string) ($ticket->comentario ?? ''),
            'fecha' => $ticket->fecha ? $ticket->fecha->format('d/m/Y') : '—',
            'fecha_prevista' => $ticket->fecha_prevista ? $ticket->fecha_prevista->format('d/m/Y') : '—',
            'estado' => IngresoProveedorEstados::etiqueta((string) $ticket->estado),
            'empresa' => (string) (optional($ticket->empresas)->nombre ?? '—'),
        ];
    }

    public function linkConsulta(int $entityId): ?string
    {
        return IngresoProveedorEnlacePublicoSupport::urlVisualizar($entityId);
    }

    public function generarPdf(int $entityId): ?array
    {
        return null;
    }

    private function emailCreador(int $entityId): ?string
    {
        $ticket = $this->cargar($entityId);
        $usuario = $ticket->usuarios;
        if (! UsuarioOperativoSupport::esOperativo($usuario) || empty($usuario->email)) {
            return null;
        }
        $email = strtolower(trim((string) $usuario->email));

        return filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : null;
    }

    /**
     * @param  array<string, string>  $placeholders
     */
    private function aplicar(string $texto, array $placeholders, ?string $linkConsulta): string
    {
        $mapa = $placeholders;
        $mapa['link_consulta'] = $linkConsulta ?? '';
        $resultado = preg_replace_callback('/\{([a-z0-9_]+)\}/i', static function (array $m) use ($mapa) {
            return $mapa[strtolower($m[1])] ?? $m[0];
        }, $texto);

        return is_string($resultado) ? $resultado : $texto;
    }

    private function cargar(int $entityId): IngresoProveedor
    {
        return IngresoProveedor::query()
            ->with([
                'usuarios:id,nombre,usuario,email,suspendido',
                'empresas:id,nombre',
                'proveedores' => static fn ($q) => $q->withTrashed()->select('id', 'codigo', 'nombre'),
                'motivos:id,nombre',
                'puntos:id,nombre',
                'sectores:id,nombre',
                'areas:id,nombre',
            ])
            ->findOrFail($entityId);
    }
}
