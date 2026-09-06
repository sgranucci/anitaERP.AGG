<?php

namespace App\Services\Configuracion;

use App\Models\Configuracion\AnitaNotificacion;
use App\Support\Configuracion\AnitaNotificacionRetencionSupport;
use App\Support\Configuracion\AnitaNotificacionWebhookSupport;
use App\Support\Configuracion\ArbolAprobacionContextoSupport;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Centro de avisos in-app + fan-out opcional a Slack/Teams (webhooks).
 *
 * Webhooks quedan APAGADOS por default (config/anita_notificacion.php).
 * Preferir webhook por usuario; el global publica a un canal compartido.
 */
class AnitaNotificacionService
{
    public const TIPO_APROBACION = 'aprobacion';

    public const TIPO_DIGEST = 'digest';

    public const TIPO_SISTEMA = 'sistema';

    public function estaHabilitado(): bool
    {
        return (bool) config('anita_notificacion.habilitado', true);
    }

    public function productoresExtraHabilitados(): bool
    {
        return $this->estaHabilitado()
            && (bool) config('anita_notificacion.productores_extra', true);
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    public function crear(
        int $usuarioId,
        string $titulo,
        ?string $cuerpo = null,
        ?string $url = null,
        string $tipo = self::TIPO_SISTEMA,
        array $meta = []
    ): ?AnitaNotificacion {
        if (! $this->estaHabilitado()) {
            return null;
        }
        if ($usuarioId <= 0 || trim($titulo) === '') {
            return null;
        }

        try {
            $n = AnitaNotificacion::query()->create([
                'usuario_id' => $usuarioId,
                'tipo' => substr($tipo, 0, 40),
                'titulo' => mb_substr(trim($titulo), 0, 180),
                'cuerpo' => $cuerpo !== null ? mb_substr(trim($cuerpo), 0, 500) : null,
                'url' => $url !== null ? mb_substr(trim($url), 0, 500) : null,
                'meta' => $meta !== [] ? $meta : null,
            ]);
        } catch (Throwable $e) {
            Log::warning('anita_notificacion_crear_error', [
                'usuario_id' => $usuarioId,
                'error' => $e->getMessage(),
            ]);

            return null;
        }

        $this->enviarWebhooksExternos($n);

        return $n;
    }

    /**
     * Aviso genérico (tickets, legajos, alertas operativas).
     *
     * @param  array<string, mixed>  $meta
     */
    public function avisarSistema(
        int $usuarioId,
        string $titulo,
        ?string $cuerpo = null,
        ?string $url = null,
        array $meta = []
    ): ?AnitaNotificacion {
        if (! $this->productoresExtraHabilitados()) {
            return null;
        }

        return $this->crear($usuarioId, $titulo, $cuerpo, $url, self::TIPO_SISTEMA, $meta);
    }

    /**
     * Crea el mismo aviso para varios usuarios (deduplica IDs).
     *
     * @param  iterable<int|string>  $usuarioIds
     * @param  array<string, mixed>  $meta
     * @return list<?AnitaNotificacion>
     */
    public function avisarSistemaAUsuarios(
        iterable $usuarioIds,
        string $titulo,
        ?string $cuerpo = null,
        ?string $url = null,
        array $meta = []
    ): array {
        if (! $this->productoresExtraHabilitados()) {
            return [];
        }

        $creadas = [];
        $vistos = [];
        foreach ($usuarioIds as $id) {
            $usuarioId = (int) $id;
            if ($usuarioId <= 0 || isset($vistos[$usuarioId])) {
                continue;
            }
            $vistos[$usuarioId] = true;
            $creadas[] = $this->crear($usuarioId, $titulo, $cuerpo, $url, self::TIPO_SISTEMA, $meta);
        }

        return $creadas;
    }

    /**
     * Aviso al firmante cuando sale un mail de aprobación del árbol.
     *
     * @param  array<string, mixed>  $extras
     */
    public function avisarAprobacionPendiente(
        int $usuarioId,
        string $tipoArbol,
        mixed $documento,
        array $extras = []
    ): void {
        $tipo = strtoupper(trim($tipoArbol));
        $etiqueta = ArbolAprobacionContextoSupport::etiquetaTipo($tipo);
        $numero = $this->numeroDesdeDocumento($documento);
        $esRecordatorio = ! empty($extras['es_recordatorio']);
        $titulo = $esRecordatorio
            ? "Recordatorio: {$etiqueta} {$numero}"
            : "Pendiente de aprobación: {$etiqueta} {$numero}";
        $cuerpo = $esRecordatorio
            ? 'Sigue sin respuesta. Abrí la bandeja para resolverlo.'
            : 'Tenés un nuevo paso para firmar en Mis aprobaciones.';
        $url = (string) ($extras['link_bandeja'] ?? url('mis-aprobaciones'));

        $this->crear($usuarioId, $titulo, $cuerpo, $url, self::TIPO_APROBACION, [
            'tipo_arbol' => $tipo,
            'es_recordatorio' => $esRecordatorio,
        ]);
    }

    public function avisarDigest(int $usuarioId, int $total, int $urgentes, string $linkBandeja): void
    {
        if ($total <= 0) {
            return;
        }
        $titulo = $total === 1
            ? 'Tenés 1 pendiente en tu bandeja'
            : "Tenés {$total} pendientes en tu bandeja";
        $cuerpo = $urgentes > 0
            ? ($urgentes === 1 ? '1 es urgente.' : "{$urgentes} son urgentes.")
            : 'Revisá Mis aprobaciones para poner al día tu cola.';

        $this->crear($usuarioId, $titulo, $cuerpo, $linkBandeja, self::TIPO_DIGEST, [
            'total' => $total,
            'urgentes' => $urgentes,
        ]);
    }

    public function contarNoLeidas(int $usuarioId): int
    {
        if ($usuarioId <= 0 || ! $this->estaHabilitado()) {
            return 0;
        }

        return (int) AnitaNotificacion::query()
            ->where('usuario_id', $usuarioId)
            ->whereNull('leida_at')
            ->count();
    }

    /**
     * @return Collection<int, AnitaNotificacion>
     */
    public function listarRecientes(int $usuarioId, int $limit = 15): Collection
    {
        if ($usuarioId <= 0 || ! $this->estaHabilitado()) {
            return collect();
        }

        return AnitaNotificacion::query()
            ->where('usuario_id', $usuarioId)
            ->orderByDesc('id')
            ->limit(max(1, min(50, $limit)))
            ->get();
    }

    public function marcarLeida(int $usuarioId, int $id): bool
    {
        $n = AnitaNotificacion::query()
            ->where('usuario_id', $usuarioId)
            ->where('id', $id)
            ->first();
        if (! $n) {
            return false;
        }
        if ($n->leida_at === null) {
            $n->leida_at = now();
            $n->save();
        }

        return true;
    }

    public function marcarTodasLeidas(int $usuarioId): int
    {
        if ($usuarioId <= 0) {
            return 0;
        }

        return (int) AnitaNotificacion::query()
            ->where('usuario_id', $usuarioId)
            ->whereNull('leida_at')
            ->update(['leida_at' => now()]);
    }

    /**
     * Borra avisos viejos según retención configurada.
     *
     * @return array{leidas: int, no_leidas: int}
     */
    public function purgar(?int $diasLeidas = null, ?int $diasNoLeidas = null): array
    {
        $diasLeidas ??= (int) config('anita_notificacion.retencion.dias_leidas', 90);
        $diasNoLeidas ??= (int) config('anita_notificacion.retencion.dias_no_leidas', 180);

        $stats = ['leidas' => 0, 'no_leidas' => 0];
        $cortes = AnitaNotificacionRetencionSupport::cortes($diasLeidas, $diasNoLeidas);

        if ($cortes['corte_leidas'] !== null) {
            $stats['leidas'] = (int) AnitaNotificacion::query()
                ->whereNotNull('leida_at')
                ->where('leida_at', '<', $cortes['corte_leidas'])
                ->delete();
        }

        if ($cortes['corte_no_leidas'] !== null) {
            $stats['no_leidas'] = (int) AnitaNotificacion::query()
                ->whereNull('leida_at')
                ->where('created_at', '<', $cortes['corte_no_leidas'])
                ->delete();
        }

        return $stats;
    }

    private function numeroDesdeDocumento(mixed $documento): string
    {
        if (! is_object($documento)) {
            return '';
        }
        foreach (['numerorequisicion', 'numeroordencompra', 'numerosolicitudpago', 'numeroordenventa', 'numero', 'sku', 'codigo', 'id'] as $campo) {
            if (isset($documento->{$campo}) && (string) $documento->{$campo} !== '') {
                return (string) $documento->{$campo};
            }
        }

        return '';
    }

    private function enviarWebhooksExternos(AnitaNotificacion $n): void
    {
        $cfg = config('anita_notificacion.webhooks', []);
        $legacy = config('arbolaprobacion.webhooks', []);
        $porUsuario = AnitaNotificacionWebhookSupport::mapaDesdeConfigYJson(
            is_array(config('anita_notificacion.webhooks.por_usuario', []))
                ? config('anita_notificacion.webhooks.por_usuario', [])
                : [],
            trim((string) env('ANITA_NOTIFICACION_WEBHOOKS_POR_USUARIO', ''))
        );

        $destinos = AnitaNotificacionWebhookSupport::resolverParaUsuario(
            (int) $n->usuario_id,
            is_array($cfg) ? $cfg : [],
            is_array($legacy) ? $legacy : [],
            $porUsuario
        );
        if ($destinos === null) {
            return;
        }

        $urlApp = $n->url ?: url('mis-aprobaciones');

        if ($destinos['slack_url'] !== '') {
            $this->postWebhook(
                $destinos['slack_url'],
                AnitaNotificacionWebhookSupport::payloadSlack((string) $n->titulo, $n->cuerpo, $urlApp),
                'slack'
            );
        }

        if ($destinos['teams_url'] !== '') {
            $this->postWebhook(
                $destinos['teams_url'],
                AnitaNotificacionWebhookSupport::payloadTeams((string) $n->titulo, $n->cuerpo, $urlApp),
                'teams'
            );
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function postWebhook(string $url, array $payload, string $canal): void
    {
        try {
            Http::timeout(8)->post($url, $payload);
        } catch (Throwable $e) {
            Log::warning('anita_notificacion_webhook_error', [
                'canal' => $canal,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
