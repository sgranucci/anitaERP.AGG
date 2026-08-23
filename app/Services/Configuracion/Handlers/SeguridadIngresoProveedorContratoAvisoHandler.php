<?php

namespace App\Services\Configuracion\Handlers;

use App\Contracts\Configuracion\ModuloAvisoDespachoHandlerInterface;
use App\Mail\Configuracion\ModuloAvisoMail;
use App\Models\Compras\Ordencompra;
use App\Models\Configuracion\ModuloAvisoTipo;
use App\Services\Configuracion\ModuloAvisoService;
use App\Support\Navegacion\ModoConsultaUrlSupport;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SeguridadIngresoProveedorContratoAvisoHandler implements ModuloAvisoDespachoHandlerInterface
{
    public function __construct(private readonly ModuloAvisoService $moduloAvisoService)
    {
    }

    public function despachar(ModuloAvisoTipo $tipo, int $entityId, array $opciones = []): void
    {
        $filtro = $this->contextoFiltro($entityId);
        $emails = array_values(array_unique(array_filter(
            $this->moduloAvisoService->resolverEmailsDestinatarios($tipo, $filtro)
        )));
        if ($emails === []) {
            Log::info('SeguridadIngresoProveedorContratoAvisoHandler: sin destinatarios', [
                'codigo' => $tipo->codigo,
                'entity_id' => $entityId,
            ]);

            return;
        }

        $placeholders = array_merge($this->placeholders($entityId), [
            'periodo' => (string) ($opciones['periodo'] ?? ''),
            'tickets' => (string) ($opciones['tickets'] ?? '0'),
        ]);
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
                Log::error('SeguridadIngresoProveedorContratoAvisoHandler: falló envío', [
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
        $oc = $this->cargar($entityId);

        return [
            'empresa_id' => (int) ($oc->empresa_id ?? 0) ?: null,
            'centrocosto_id' => null,
        ];
    }

    public function placeholders(int $entityId): array
    {
        $oc = $this->cargar($entityId);

        return [
            'id' => (string) $oc->id,
            'numero' => (string) ($oc->numeroordencompra ?? $oc->id),
            'proveedor' => (string) (optional($oc->proveedores)->nombre ?? '—'),
            'empresa' => (string) (optional($oc->empresas)->nombre ?? '—'),
            'periodo' => '',
            'tickets' => '0',
        ];
    }

    public function linkConsulta(int $entityId): ?string
    {
        return ModoConsultaUrlSupport::urlAbsolutaConConsulta('compras/ordencompra/'.$entityId.'/editar');
    }

    public function generarPdf(int $entityId): ?array
    {
        return null;
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

    private function cargar(int $entityId): Ordencompra
    {
        return Ordencompra::query()
            ->with(['proveedores:id,codigo,nombre', 'empresas:id,nombre'])
            ->findOrFail($entityId);
    }
}
