<?php

namespace App\Services\Configuracion\Handlers;

use App\Contracts\Configuracion\ModuloAvisoDespachoHandlerInterface;
use App\Mail\Configuracion\ModuloAvisoMail;
use App\Models\Configuracion\ModuloAvisoTipo;
use App\Models\Uif\Cliente_Uif;
use App\Services\Configuracion\ModuloAvisoService;
use App\Support\Navegacion\ModoConsultaUrlSupport;
use App\Support\Seguridad\UsuarioOperativoSupport;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Alta de cliente UIF: destinatarios del ABM módulo aviso + usuarios operativos con supervisor-uif.
 */
class UifClienteAltaAvisoHandler implements ModuloAvisoDespachoHandlerInterface
{
    public function __construct(
        private readonly ModuloAvisoService $moduloAvisoService,
    ) {
    }

    public function despachar(ModuloAvisoTipo $tipo, int $entityId, array $opciones = []): void
    {
        $filtro = $this->contextoFiltro($entityId);
        $emails = $this->moduloAvisoService->resolverEmailsDestinatarios($tipo, $filtro);
        $emails = array_merge($emails, $this->emailsSupervisoresUif());
        $emails = array_values(array_unique(array_filter($emails)));

        if ($emails === []) {
            Log::info('UifClienteAltaAviso: sin destinatarios', ['cliente_uif_id' => $entityId]);

            return;
        }

        $placeholders = $this->placeholders($entityId);
        $linkConsulta = $tipo->incluir_link_consulta ? $this->linkConsulta($entityId) : null;
        $asunto = $this->aplicarPlaceholders((string) $tipo->mail_asunto, $placeholders, $linkConsulta);
        $texto = $this->aplicarPlaceholders((string) ($tipo->mail_texto ?? ''), $placeholders, $linkConsulta);

        foreach ($emails as $email) {
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
                Mail::to($email)->queue($mailable);
            } catch (\Throwable $e) {
                Log::warning('UifClienteAltaAviso: error envío', [
                    'cliente_uif_id' => $entityId,
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
        $cliente = Cliente_Uif::query()
            ->with(['usuarios:id,nombre,usuario', 'tipodocumentos:id,nombre'])
            ->findOrFail($entityId);

        $tipoDoc = trim((string) (optional($cliente->tipodocumentos)->nombre ?? ''));

        return [
            'id' => (string) $cliente->id,
            'nombre' => (string) ($cliente->nombre ?? '—'),
            'numerodocumento' => (string) ($cliente->numerodocumento ?? '—'),
            'tipodocumento' => $tipoDoc !== '' ? $tipoDoc : '—',
            'cuit' => (string) ($cliente->cuit ?? '—'),
            'usuario_alta' => (string) (optional($cliente->usuarios)->nombre
                ?? optional($cliente->usuarios)->usuario
                ?? '—'),
            'fecha' => $cliente->created_at
                ? $cliente->created_at->format('d/m/Y H:i')
                : '—',
        ];
    }

    public function linkConsulta(int $entityId): ?string
    {
        return ModoConsultaUrlSupport::urlAbsolutaConConsulta(
            'uif/cliente_uif/'.$entityId.'/editar?uif_tab=2'
        );
    }

    public function generarPdf(int $entityId): ?array
    {
        return null;
    }

    /**
     * @return list<string>
     */
    private function emailsSupervisoresUif(): array
    {
        $emails = UsuarioOperativoSupport::query()
            ->whereExists(function ($q) {
                $q->selectRaw('1')
                    ->from('usuario_rol')
                    ->join('permiso_rol', 'usuario_rol.rol_id', '=', 'permiso_rol.rol_id')
                    ->join('permiso', 'permiso.id', '=', 'permiso_rol.permiso_id')
                    ->whereColumn('usuario_rol.usuario_id', 'usuario.id')
                    ->where('permiso.slug', 'supervisor-uif');
            })
            ->whereNotNull('email')
            ->where('email', '!=', '')
            ->pluck('email')
            ->all();

        $out = [];
        foreach ($emails as $email) {
            $email = strtolower(trim((string) $email));
            if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $out[] = $email;
            }
        }

        return $out;
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
