<?php

namespace App\Services\Configuracion\Handlers;

use App\Contracts\Configuracion\ModuloAvisoDespachoHandlerInterface;
use App\Mail\Configuracion\ModuloAvisoMail;
use App\Models\Compras\Comprobante_Proveedor;
use App\Models\Configuracion\ModuloAvisoTipo;
use App\Models\Contable\BienUso;
use App\Services\Configuracion\ModuloAvisoService;
use App\Support\Navegacion\ModoConsultaUrlSupport;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;

class ContableBienUsoProvisorioAvisoHandler implements ModuloAvisoDespachoHandlerInterface
{
    public function __construct(
        private readonly ModuloAvisoService $moduloAvisoService,
    ) {
    }

    public function despachar(ModuloAvisoTipo $tipo, int $entityId, array $opciones = []): void
    {
        $emails = $this->moduloAvisoService->resolverEmailsDestinatarios($tipo, $this->contextoFiltro($entityId));
        $emails = array_values(array_unique(array_filter($emails)));
        if ($emails === []) {
            Log::info('ContableBienUsoProvisorioAviso: sin destinatarios', [
                'comprobante_proveedor_id' => $entityId,
            ]);

            return;
        }

        $placeholders = $this->placeholders($entityId);
        $linkConsulta = $tipo->incluir_link_consulta ? $this->linkConsulta($entityId) : null;
        $placeholders['link_consulta'] = $linkConsulta ?? '';

        $asunto = $this->aplicarPlaceholders((string) $tipo->mail_asunto, $placeholders);
        $texto = $this->aplicarPlaceholders((string) ($tipo->mail_texto ?? ''), $placeholders);

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
                Log::warning('ContableBienUsoProvisorioAviso: error envío', [
                    'comprobante_proveedor_id' => $entityId,
                    'email' => $email,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    public function contextoFiltro(int $entityId): array
    {
        $cp = Comprobante_Proveedor::query()->find($entityId);

        return [
            'empresa_id' => $cp ? (int) $cp->empresa_id : null,
            'centrocosto_id' => null,
        ];
    }

    public function placeholders(int $entityId): array
    {
        $cp = Comprobante_Proveedor::query()
            ->with(['proveedores:id,codigo,nombre', 'tipotransaccion_compras:id,abreviatura'])
            ->findOrFail($entityId);

        $abrev = strtoupper(trim((string) ($cp->tipotransaccion_compras?->abreviatura ?? '')));
        $factura = trim(
            $abrev.' '
            .($cp->letra ?? '').'-'
            .str_pad((string) ($cp->sucursal ?? 0), 4, '0', STR_PAD_LEFT).'-'
            .(string) ($cp->numerocomprobante ?? '')
        );

        $bienes = Schema::hasColumn('bien_uso', 'comprobante_proveedor_id')
            ? BienUso::query()
                ->where('comprobante_proveedor_id', $entityId)
                ->where('estado', 'P')
                ->orderBy('id')
                ->get(['id', 'descripcion', 'valor_origen'])
            : collect();

        $lineas = [];
        foreach ($bienes as $bien) {
            $url = ModoConsultaUrlSupport::urlAbsolutaConConsulta('contable/bien-uso/'.$bien->id.'/editar');
            $lineas[] = sprintf(
                '- #%d %s ($ %s): %s',
                $bien->id,
                (string) ($bien->descripcion ?? ''),
                number_format((float) $bien->valor_origen, 2, ',', '.'),
                $url ?? ('/contable/bien-uso/'.$bien->id.'/editar')
            );
        }

        return [
            'id' => (string) $cp->id,
            'factura' => $factura !== '' ? $factura : '#'.$cp->id,
            'proveedor' => (string) ($cp->proveedores?->nombre
                ?? $cp->proveedor_nombre_eventual
                ?? '—'),
            'cantidad' => (string) $bienes->count(),
            'bienes' => $lineas !== [] ? implode("\n", $lineas) : '(sin bienes provisorios)',
            'fecha' => $cp->fechacomprobante ? $cp->fechacomprobante->format('d/m/Y') : '—',
        ];
    }

    public function linkConsulta(int $entityId): ?string
    {
        return url('/contable/bien-uso?filtro_estado=P&comprobante_proveedor_id='.$entityId);
    }

    public function generarPdf(int $entityId): ?array
    {
        return null;
    }

    /** @param  array<string, string>  $placeholders */
    private function aplicarPlaceholders(string $plantilla, array $placeholders): string
    {
        $resultado = preg_replace_callback('/\{([a-z0-9_]+)\}/i', function (array $m) use ($placeholders) {
            $clave = strtolower($m[1]);

            return $placeholders[$clave] ?? $m[0];
        }, $plantilla);

        return is_string($resultado) ? $resultado : $plantilla;
    }
}
