<?php

namespace App\Services\Configuracion\Handlers;

use App\Contracts\Configuracion\ModuloAvisoDespachoHandlerInterface;
use App\Mail\Configuracion\ModuloAvisoMail;
use App\Models\Configuracion\ModuloAvisoTipo;
use App\Models\Stock\Transferencia_Mercaderia;
use App\Models\Stock\Transferencia_Mercaderia_Token;
use App\Services\Configuracion\ModuloAvisoService;
use App\Support\Stock\TransferenciaMercaderiaDestinatarioSupport;
use App\Support\Stock\TransferenciaMercaderiaEstados;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class StockTransferenciaMercaderiaAvisoDespachoHandler implements ModuloAvisoDespachoHandlerInterface
{
    public function __construct(
        private readonly ModuloAvisoService $moduloAvisoService,
    ) {}

    public function despachar(ModuloAvisoTipo $tipo, int $entityId, array $opciones = []): void
    {
        match ($tipo->codigo) {
            'transferencia_pendiente_aprobacion' => $this->despacharPendiente($tipo, $entityId),
            'transferencia_confirmada' => $this->despacharGenerico($tipo, $entityId, 'transferencia_confirmada'),
            'transferencia_rechazada' => $this->despacharGenerico($tipo, $entityId, 'transferencia_rechazada', $opciones),
            default => Log::warning('StockTransferenciaMercaderiaAviso: código no soportado', ['codigo' => $tipo->codigo]),
        };
    }

    public function contextoFiltro(int $entityId): array
    {
        $t = $this->cargar($entityId);

        return [
            'empresa_id' => (int) $t->empresa_id ?: null,
            'centrocosto_id' => null,
        ];
    }

    public function placeholders(int $entityId): array
    {
        return $this->placeholdersTransferencia($this->cargar($entityId));
    }

    public function linkConsulta(int $entityId): ?string
    {
        $transferencia = $this->cargar($entityId);
        $linkPublico = trim($this->linksAprobacion($transferencia)['link_consulta'] ?? '');
        if ($linkPublico !== '') {
            return $linkPublico;
        }

        if ($transferencia->estado === TransferenciaMercaderiaEstados::PENDIENTE_RECEPCION) {
            return urlAppAbsoluta('stock/transferencia-mercaderia/pendientes');
        }

        return urlAppAbsoluta('stock/transferencia-mercaderia');
    }

    public function generarPdf(int $entityId): ?array
    {
        return null;
    }

    private function despacharPendiente(ModuloAvisoTipo $tipo, int $entityId): void
    {
        $transferencia = $this->cargar($entityId);
        $placeholders = $this->placeholdersTransferencia($transferencia);
        $filtro = $this->contextoFiltro($entityId);

        $emails = [];
        $usuarioDestinoId = (int) ($transferencia->usuario_destino_id ?? 0);
        if ($usuarioDestinoId > 0 && $transferencia->usuarioDestino?->email) {
            $emails[] = strtolower((string) $transferencia->usuarioDestino->email);
        }

        foreach (TransferenciaMercaderiaDestinatarioSupport::administradoresAprobacion((int) $transferencia->deposito_destino_id) as $admin) {
            $email = strtolower(trim((string) ($admin->usuarios?->email ?? '')));
            if ($email !== '') {
                $emails[] = $email;
            }
        }

        $emails = array_values(array_unique($emails));
        $emailsModulo = $this->moduloAvisoService->resolverEmailsDestinatarios($tipo, $filtro);
        $emails = array_values(array_unique(array_merge($emails, $emailsModulo)));

        if ($emails === []) {
            Log::info('TransferenciaMercaderia aviso pendiente: sin destinatarios', ['id' => $entityId]);

            return;
        }

        $links = $this->linksAprobacion($transferencia);
        $placeholders = array_merge($placeholders, $links);
        $linkConsulta = trim($links['link_consulta'] ?? '') !== ''
            ? $links['link_consulta']
            : $this->linkConsulta($entityId);

        $this->enviarMasivo($tipo, $emails, $placeholders, $linkConsulta);
    }

    private function despacharGenerico(ModuloAvisoTipo $tipo, int $entityId, string $codigo, array $opciones = []): void
    {
        $transferencia = $this->cargar($entityId);
        $placeholders = $this->placeholdersTransferencia($transferencia);
        if ($codigo === 'transferencia_rechazada') {
            $placeholders['motivo_rechazo'] = trim((string) ($opciones['motivo'] ?? $transferencia->motivo_rechazo ?? '—'));
        }

        $filtro = $this->contextoFiltro($entityId);
        $emails = $this->moduloAvisoService->resolverEmailsDestinatarios($tipo, $filtro);

        if ($transferencia->usuarioOrigen?->email) {
            $emails[] = strtolower((string) $transferencia->usuarioOrigen->email);
        }

        $emails = array_values(array_unique(array_filter($emails)));
        if ($emails === []) {
            return;
        }

        $this->enviarMasivo($tipo, $emails, $placeholders, $tipo->incluir_link_consulta ? $this->linkConsulta($entityId) : null);
    }

    /** @param  list<string>  $emails */
    private function enviarMasivo(ModuloAvisoTipo $tipo, array $emails, array $placeholders, ?string $linkConsulta): void
    {
        $asunto = $this->aplicarPlaceholders((string) $tipo->mail_asunto, $placeholders, $linkConsulta);
        $texto = $this->aplicarPlaceholders((string) ($tipo->mail_texto ?? ''), $placeholders, $linkConsulta);

        foreach ($emails as $email) {
            try {
                $mailable = new ModuloAvisoMail($asunto, $texto, $tipo->nombre, $linkConsulta, null);
                if (! empty($tipo->mail_remitente)) {
                    $mailable->from($tipo->mail_remitente);
                }
                Mail::to($email)->send($mailable);
            } catch (\Throwable $e) {
                Log::warning('TransferenciaMercaderia aviso: error envío', [
                    'email' => $email,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    private function cargar(int $entityId): Transferencia_Mercaderia
    {
        return Transferencia_Mercaderia::query()
            ->with([
                'depositoOrigen',
                'depositoDestino',
                'usuarioOrigen',
                'usuarioDestino',
                'articulos.articuloOrigen',
                'articulos.articuloDestino',
            ])
            ->findOrFail($entityId);
    }

    /** @return array<string, string> */
    private function placeholdersTransferencia(Transferencia_Mercaderia $t): array
    {
        $lineas = [];
        foreach ($t->articulos as $linea) {
            $origen = $linea->articuloOrigen;
            $destino = $linea->articuloDestino;
            $texto = sprintf(
                '%s %s — cant. origen %.4f',
                optional($origen)->sku ?? $linea->articulo_origen_id,
                optional($origen)->descripcion ?? '',
                (float) $linea->cantidad_origen
            );
            if ($linea->fl_conversion_formula) {
                $texto .= sprintf(
                    ' → %s %s cant. destino %.4f',
                    optional($destino)->sku ?? $linea->articulo_destino_id,
                    optional($destino)->descripcion ?? '',
                    (float) $linea->cantidad_destino
                );
            }
            $lineas[] = $texto;
        }

        return [
            'codigo' => (string) $t->codigo,
            'fecha' => $t->fecha ? $t->fecha->format('d/m/Y') : '—',
            'deposito_origen' => (string) (optional($t->depositoOrigen)->nombre ?? '—'),
            'deposito_destino' => (string) (optional($t->depositoDestino)->nombre ?? '—'),
            'usuario_origen' => (string) (optional($t->usuarioOrigen)->nombre ?? '—'),
            'usuario_destino' => (string) (optional($t->usuarioDestino)->nombre ?? '—'),
            'detalle_lineas' => $lineas !== [] ? implode("\n", $lineas) : '—',
            'motivo_rechazo' => (string) ($t->motivo_rechazo ?? '—'),
        ];
    }

    /** @return array<string, string> */
    private function linksAprobacion(Transferencia_Mercaderia $transferencia): array
    {
        $tokens = Transferencia_Mercaderia_Token::query()
            ->where('transferencia_mercaderia_id', $transferencia->id)
            ->whereNull('usado_el')
            ->get()
            ->keyBy('accion');

        return [
            'link_aprobar' => isset($tokens[Transferencia_Mercaderia_Token::ACCION_APROBAR])
                ? urlAppAbsoluta('stock/transferencia-mercaderia/publico/'.$tokens[Transferencia_Mercaderia_Token::ACCION_APROBAR]->token.'/aprobar')
                : '',
            'link_rechazar' => isset($tokens[Transferencia_Mercaderia_Token::ACCION_RECHAZAR])
                ? urlAppAbsoluta('stock/transferencia-mercaderia/publico/'.$tokens[Transferencia_Mercaderia_Token::ACCION_RECHAZAR]->token.'/rechazar')
                : '',
            'link_consulta' => isset($tokens[Transferencia_Mercaderia_Token::ACCION_VISUALIZAR])
                ? urlAppAbsoluta('stock/transferencia-mercaderia/publico/'.$tokens[Transferencia_Mercaderia_Token::ACCION_VISUALIZAR]->token.'/ver')
                : '',
        ];
    }

    /** @param  array<string, string>  $placeholders */
    private function aplicarPlaceholders(string $plantilla, array $placeholders, ?string $linkConsulta): string
    {
        $linkFinal = trim($placeholders['link_consulta'] ?? '') !== ''
            ? $placeholders['link_consulta']
            : ($linkConsulta ?? '');
        $mapa = array_merge($placeholders, ['link_consulta' => $linkFinal]);
        $resultado = preg_replace_callback('/\{([a-z0-9_]+)\}/i', function (array $m) use ($mapa) {
            $clave = strtolower($m[1]);

            return $mapa[$clave] ?? $m[0];
        }, $plantilla);

        return is_string($resultado) ? $resultado : $plantilla;
    }
}
