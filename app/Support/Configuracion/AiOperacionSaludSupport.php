<?php

namespace App\Support\Configuracion;

use App\Models\Ai\AiAgenteEvento;
use App\Models\Ai\AiDecision;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Salud operativa de la plataforma IA (punto 1 del roadmap): mail, batch, flags, backlog HITL.
 */
final class AiOperacionSaludSupport
{
    /**
     * @return array{
     *   items: list<array{clave: string, ok: bool, etiqueta: string, detalle: string}>,
     *   eventos_pendientes: int,
     *   decisiones_hoy: int,
     *   tasa_aceptacion_30d: ?float
     * }
     */
    public static function snapshot(): array
    {
        $items = [];

        $items[] = self::itemFlag(
            'ai_habilitado',
            'Plataforma IA',
            (bool) config('ai.habilitado'),
            'AI_HABILITADO'
        );
        $items[] = self::itemFlag(
            'kill_switch',
            'Kill-switch',
            ! (bool) config('ai.kill_switch'),
            (bool) config('ai.kill_switch') ? 'ACTIVO (corta modelos)' : 'inactivo'
        );
        $items[] = self::itemFlag(
            'mail_ingesta',
            'Mail → precarga',
            (bool) config('precarga_comprobante_mail.habilitada'),
            'PRECARGA_MAIL_INGESTA_HABILITADA / schedule cada '
            .(int) config('precarga_comprobante_mail.intervalo_minutos', 5).' min'
        );
        $items[] = self::itemFlag(
            'batch_ia',
            'Batch IA / carpeta',
            (bool) config('precarga_comprobante_batch_ia.habilitada'),
            'PRECARGA_BATCH_IA_HABILITADA'
        );
        $items[] = self::itemFlag(
            'agente_evento',
            'Agente por evento',
            (bool) config('ai.agente_evento.habilitado', true),
            'AI_AGENTE_EVENTO_HABILITADO'
        );
        $items[] = self::itemFlag(
            'rag_manuales',
            'RAG manuales',
            (bool) config('ai.rag_manuales.habilitado', true),
            'AI_RAG_MANUALES_HABILITADO / ai:indexar-manuales'
        );
        $mcpOk = (bool) config('ai.mcp.habilitado', false)
            && trim((string) config('ai.mcp.token', '')) !== '';
        $items[] = self::itemFlag(
            'mcp',
            'MCP HTTP',
            $mcpOk,
            $mcpOk ? 'AI_MCP_HABILITADO + token' : 'AI_MCP_HABILITADO/TOKEN'
        );

        $logMail = storage_path('logs/ingesta-facturas-mail-schedule.log');
        $items[] = [
            'clave' => 'log_mail',
            'ok' => is_file($logMail) && (time() - (int) filemtime($logMail)) < 86400,
            'etiqueta' => 'Schedule mail (log 24h)',
            'detalle' => is_file($logMail)
                ? 'Última escritura: '.date('d/m/Y H:i', (int) filemtime($logMail))
                : 'Sin log aún (cron schedule:run / mail apagado)',
        ];

        $jobsMail = 0;
        try {
            if (Schema::hasTable('jobs')) {
                $jobsMail = (int) DB::table('jobs')
                    ->where('payload', 'like', '%ProcesarFacturaMailJob%')
                    ->count();
            }
        } catch (Throwable) {
            $jobsMail = 0;
        }
        $items[] = [
            'clave' => 'cola_mail',
            'ok' => $jobsMail < 50,
            'etiqueta' => 'Cola jobs mail',
            'detalle' => $jobsMail.' job(s) ProcesarFacturaMailJob en cola'
                .($jobsMail >= 50 ? ' — revisar worker' : ''),
        ];

        $eventosPendientes = 0;
        try {
            if (Schema::hasTable('ai_agente_evento')) {
                $eventosPendientes = (int) AiAgenteEvento::query()
                    ->where('estado', AiAgenteEvento::ESTADO_PENDIENTE)
                    ->count();
            }
        } catch (Throwable) {
            $eventosPendientes = 0;
        }

        $decisionesHoy = 0;
        $tasa30 = null;
        try {
            if (Schema::hasTable('ai_decision')) {
                $decisionesHoy = (int) AiDecision::query()->whereDate('created_at', now()->toDateString())->count();
                $kpis = AiDecisionKpisSupport::calcular([
                    'consultar' => true,
                    'fecha_desde' => now()->subDays(30)->toDateString(),
                    'fecha_hasta' => now()->toDateString(),
                ]);
                $tasa30 = $kpis['tasa_aceptacion'] ?? null;
            }
        } catch (Throwable) {
            // ignore
        }

        $items[] = [
            'clave' => 'kpi_aceptacion',
            'ok' => $tasa30 === null || $tasa30 >= 0.5,
            'etiqueta' => 'Aceptación 30d (meta ≥ 50%)',
            'detalle' => $tasa30 === null
                ? 'Sin decisiones en el período'
                : number_format($tasa30 * 100, 1, ',', '.').'%',
        ];

        return [
            'items' => $items,
            'eventos_pendientes' => $eventosPendientes,
            'decisiones_hoy' => $decisionesHoy,
            'tasa_aceptacion_30d' => $tasa30,
        ];
    }

    /**
     * @return list<AiAgenteEvento>
     */
    public static function eventosPendientes(int $limite = 15): array
    {
        if (! Schema::hasTable('ai_agente_evento')) {
            return [];
        }

        return AiAgenteEvento::query()
            ->where('estado', AiAgenteEvento::ESTADO_PENDIENTE)
            ->orderByDesc('id')
            ->limit($limite)
            ->get()
            ->all();
    }

    /**
     * @return array{clave: string, ok: bool, etiqueta: string, detalle: string}
     */
    private static function itemFlag(string $clave, string $etiqueta, bool $ok, string $detalle): array
    {
        return [
            'clave' => $clave,
            'ok' => $ok,
            'etiqueta' => $etiqueta,
            'detalle' => $detalle,
        ];
    }
}
