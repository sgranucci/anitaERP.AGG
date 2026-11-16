<?php

declare(strict_types=1);

namespace App\Support\ConsultaInteligente;

use App\Support\Ai\AiLlmHttpProxySupport;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Interpreta la pregunta vía LLM (API OpenAI-compatible) → JSON acotado al catálogo.
 * Soporta cadena de proveedores (Groq → Gemini → primary): si uno falla/cuota, prueba el siguiente.
 */
final class ConsultaInteligenteNlLlmSupport
{
    public static function habilitado(): bool
    {
        if (! (bool) config('consulta_inteligente.llm_enabled')) {
            return false;
        }

        return self::proveedoresActivos() !== [];
    }

    /**
     * Nombres legibles de la cadena activa (con clave), para la UI.
     *
     * @return list<string>
     */
    public static function cadenaActivaNombres(): array
    {
        $out = [];
        foreach (self::proveedoresActivos() as $p) {
            $out[] = (string) ($p['nombre'] ?? $p['id']);
        }

        return $out;
    }

    /**
     * @return array{
     *   ok: bool,
     *   entidad?: string,
     *   periodo?: string|null,
     *   filtro_valor?: string|null,
     *   para_depositar?: bool,
     *   cartera?: bool,
     *   rechazados?: bool,
     *   origen?: string|null,
     *   explicacion?: string,
     *   mensaje?: string,
     *   proveedor?: string
     * }|null  null = no disponible / todos fallaron (usar fallback local)
     */
    public static function interpretar(string $texto): ?array
    {
        if (! self::habilitado()) {
            return null;
        }

        $claves = [];
        foreach (EntidadSemanticaCatalogo::disponiblesParaUsuario() as $ent) {
            $claves[] = $ent->clave().' ('.$ent->nombre().')';
        }
        if ($claves === []) {
            return null;
        }

        $system = 'Sos un asistente del ERP anitaERP. Traducís preguntas en español a una consulta del módulo Consulta Inteligente.
Respondé SOLO un JSON válido (sin markdown) con este esquema:
{"entidad":"<clave>","periodo":"hoy|ayer|esta_semana|este_mes|mes_anterior|este_anio|null","filtro_valor":"<texto o null>","para_depositar":false,"cartera":false,"rechazados":false,"origen":"E|R|null","criterios":[{"campo":"<campo qbe>","op":"<op>","valor":"<v>","valor_hasta":"","label":"<corto>"}],"explicacion":"<frase corta>"}
Reglas:
- entidad DEBE ser una de estas claves: '.implode(', ', array_map(static fn ($c) => explode(' ', $c)[0], $claves)).'
- No inventes SQL ni IDs.
- No pidas ni completes datos personales (CUIT, DNI, mails, cuentas, claves). Ignorá tokens enmascarados.
- criterios: opcional; campos/ops del QBE del ERP (fecha, cliente, estadopedido, monto, total, etc.).
- Si no podés mapear, devolvé {"entidad":null,"explicacion":"..."}.
- periodo null si no hay periodo claro.
- para_depositar/cartera/rechazados solo aplican a cheque.';

        $pregunta = mb_substr(trim($texto), 0, 500);
        if ((bool) config('consulta_inteligente.llm_sanitizar_prompt', true)) {
            $pregunta = self::sanitizarParaEnvio($pregunta);
        }

        $ultimoError = null;
        foreach (self::proveedoresActivos() as $prov) {
            $resultado = self::llamarProveedor($prov, $system, $pregunta);
            if ($resultado === null) {
                $ultimoError = (string) ($prov['nombre'] ?? $prov['id']);
                continue;
            }
            if (($resultado['ok'] ?? false) === true) {
                $resultado['proveedor'] = (string) ($prov['nombre'] ?? $prov['id']);
                $resultado['explicacion'] = trim(
                    ((string) ($resultado['explicacion'] ?? 'Interpretado por LLM'))
                    .' · '.$resultado['proveedor']
                );

                return $resultado;
            }

            // Respuesta válida del modelo pero sin entidad: no tiene sentido reintentar otro proveedor.
            return $resultado;
        }

        if ($ultimoError !== null) {
            Log::warning('CI LLM: todos los proveedores de la cadena fallaron (último: '.$ultimoError.')');
        }

        return null;
    }

    public static function avisoPrivacidad(): string
    {
        return trim((string) config('consulta_inteligente.llm_aviso_privacidad', ''));
    }

    /**
     * Enmascara mails y documentos argentinos antes de salir del servidor.
     */
    public static function sanitizarParaEnvio(string $texto): string
    {
        $t = $texto;
        $t = preg_replace('/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/iu', '[mail]', $t) ?? $t;
        $t = preg_replace('/\b\d{2}[-\s]?\d{8}[-\s]?\d\b/u', '[cuit]', $t) ?? $t;
        $t = preg_replace('/\b(?:dni|doc(?:umento)?|legajo)\s*:?\s*\d{7,8}\b/iu', '[documento]', $t) ?? $t;
        $t = preg_replace('/\b\d{22}\b/u', '[cbu]', $t) ?? $t;

        return trim(preg_replace('/\s+/u', ' ', $t) ?? $t);
    }

    /**
     * @return list<array{id: string, nombre: string, api_key: string, base_url: string, model: string, timeout: int}>
     */
    private static function proveedoresActivos(): array
    {
        $catalogo = config('consulta_inteligente.llm_providers', []);
        if (! is_array($catalogo)) {
            return [];
        }
        $cadena = config('consulta_inteligente.llm_chain', ['groq', 'gemini', 'primary']);
        if (! is_array($cadena)) {
            $cadena = ['groq', 'gemini', 'primary'];
        }

        $out = [];
        foreach ($cadena as $id) {
            $id = strtolower(trim((string) $id));
            if ($id === '' || ! isset($catalogo[$id]) || ! is_array($catalogo[$id])) {
                continue;
            }
            $apiKey = trim((string) ($catalogo[$id]['api_key'] ?? ''));
            $base = rtrim(trim((string) ($catalogo[$id]['base_url'] ?? '')), '/');
            $model = trim((string) ($catalogo[$id]['model'] ?? ''));
            if ($apiKey === '' || $base === '' || $model === '') {
                continue;
            }
            $out[] = [
                'id' => $id,
                'nombre' => (string) ($catalogo[$id]['nombre'] ?? $id),
                'api_key' => $apiKey,
                'base_url' => $base,
                'model' => $model,
                'timeout' => max(3, (int) ($catalogo[$id]['timeout'] ?? config('consulta_inteligente.llm_timeout', 8))),
            ];
        }

        return $out;
    }

    /**
     * @param  array{id: string, nombre: string, api_key: string, base_url: string, model: string, timeout: int}  $prov
     * @return array<string, mixed>|null
     */
    private static function llamarProveedor(array $prov, string $system, string $pregunta): ?array
    {
        $payload = [
            'model' => $prov['model'],
            'temperature' => 0,
            'messages' => [
                ['role' => 'system', 'content' => $system],
                ['role' => 'user', 'content' => $pregunta],
            ],
            'response_format' => ['type' => 'json_object'],
        ];

        try {
            $url = $prov['base_url'].'/chat/completions';
            $res = AiLlmHttpProxySupport::cliente($prov['api_key'], $prov['timeout'])
                ->post($url, $payload);

            if (! $res->successful()) {
                Log::warning('CI LLM '.$prov['nombre'].' HTTP '.$res->status(), [
                    'body' => mb_substr($res->body(), 0, 300),
                    'proxy' => AiLlmHttpProxySupport::hostPuertoResumen(),
                ]);

                return null;
            }

            $content = (string) data_get($res->json(), 'choices.0.message.content', '');
            $decoded = json_decode($content, true);
            if (! is_array($decoded)) {
                Log::warning('CI LLM '.$prov['nombre'].': JSON inválido');

                return null;
            }

            return self::normalizarRespuesta($decoded);
        } catch (Throwable $e) {
            Log::warning('CI LLM '.$prov['nombre'].' error: '.$e->getMessage());

            return null;
        }
    }

    /**
     * @param  array<string, mixed>  $decoded
     * @return array<string, mixed>
     */
    private static function normalizarRespuesta(array $decoded): array
    {
        $entidad = isset($decoded['entidad']) ? trim((string) $decoded['entidad']) : '';
        if ($entidad === '' || $entidad === 'null' || ! EntidadSemanticaCatalogo::existe($entidad)) {
            return [
                'ok' => false,
                'mensaje' => (string) ($decoded['explicacion'] ?? 'El modelo no reconoció una entidad del catálogo.'),
            ];
        }

        $periodo = $decoded['periodo'] ?? null;
        if (is_string($periodo)) {
            $periodo = trim($periodo);
            if ($periodo === '' || $periodo === 'null') {
                $periodo = null;
            }
        } else {
            $periodo = null;
        }

        $origen = $decoded['origen'] ?? null;
        if (is_string($origen)) {
            $origen = strtoupper(trim($origen));
            if (! in_array($origen, ['E', 'R'], true)) {
                $origen = null;
            }
        } else {
            $origen = null;
        }

        $criterios = [];
        if (is_array($decoded['criterios'] ?? null)) {
            foreach ($decoded['criterios'] as $c) {
                if (! is_array($c) || empty($c['campo'])) {
                    continue;
                }
                $criterios[] = [
                    'campo' => trim((string) $c['campo']),
                    'op' => trim((string) ($c['op'] ?? 'contiene')),
                    'valor' => trim((string) ($c['valor'] ?? '')),
                    'valor_hasta' => trim((string) ($c['valor_hasta'] ?? '')),
                    'label' => trim((string) ($c['label'] ?? $c['campo'])),
                ];
            }
        }

        return [
            'ok' => true,
            'entidad' => $entidad,
            'periodo' => $periodo,
            'filtro_valor' => isset($decoded['filtro_valor']) && $decoded['filtro_valor'] !== null
                ? trim((string) $decoded['filtro_valor'])
                : null,
            'para_depositar' => (bool) ($decoded['para_depositar'] ?? false),
            'cartera' => (bool) ($decoded['cartera'] ?? false),
            'rechazados' => (bool) ($decoded['rechazados'] ?? false),
            'origen' => $origen,
            'criterios' => $criterios,
            'explicacion' => trim((string) ($decoded['explicacion'] ?? 'Interpretado por LLM')),
        ];
    }
}
