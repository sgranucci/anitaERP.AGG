<?php

namespace App\Support\Ai;

use App\Services\Ai\AiGateway;
use App\Services\Ai\AiPrompt;
use App\Services\Ai\AiResult;
use Throwable;

/**
 * Redacta un resumen narrativo a partir del snapshot ya grounded (sin inventar números).
 * Ollama primero; si falla, cadena externa CI_LLM_*.
 */
final class AiConsultaOperativaLlmRedactarSupport
{
    public static function habilitado(): bool
    {
        return filter_var(
            config('ai.skills.consultar_contexto_operativo.llm_redactar', false),
            FILTER_VALIDATE_BOOLEAN
        );
    }

    /**
     * @param  list<string>  $parrafos
     * @param  array{columnas?: list<array{key?: string, label?: string}>, filas?: list<array<string, mixed>>}|null  $tabla
     * @return array{texto: string, fuente: string}|null
     */
    public static function redactarDetalle(
        string $pregunta,
        string $intent,
        string $interpretacion,
        array $parrafos,
        ?array $tabla = null,
    ): ?array {
        $texto = self::redactar($pregunta, $intent, $interpretacion, $parrafos, $tabla);
        if ($texto === null) {
            return null;
        }

        return [
            'texto' => $texto,
            'fuente' => self::$ultimaFuente ?? 'llm',
        ];
    }

    private static ?string $ultimaFuente = null;

    /**
     * @param  list<string>  $parrafos
     * @param  array{columnas?: list<array{key?: string, label?: string}>, filas?: list<array<string, mixed>>}|null  $tabla
     */
    public static function redactar(
        string $pregunta,
        string $intent,
        string $interpretacion,
        array $parrafos,
        ?array $tabla = null,
    ): ?string {
        self::$ultimaFuente = null;
        if (! self::habilitado()) {
            return null;
        }

        $hechos = [];
        foreach (array_slice($parrafos, 0, 16) as $p) {
            $p = trim((string) $p);
            if ($p !== '') {
                $hechos[] = '- '.$p;
            }
        }

        $filasTxt = [];
        if (is_array($tabla) && ! empty($tabla['filas']) && is_array($tabla['filas'])) {
            $cols = [];
            foreach (($tabla['columnas'] ?? []) as $col) {
                if (is_array($col) && ! empty($col['key'])) {
                    $cols[] = (string) $col['key'];
                }
            }
            foreach (array_slice($tabla['filas'], 0, 12) as $fila) {
                if (! is_array($fila)) {
                    continue;
                }
                $celdas = [];
                foreach ($cols as $ck) {
                    if (isset($fila[$ck]) && is_scalar($fila[$ck])) {
                        $celdas[] = $ck.': '.$fila[$ck];
                    }
                }
                if ($celdas !== []) {
                    $filasTxt[] = '- '.implode(' | ', $celdas);
                }
            }
        }

        if ($hechos === [] && $filasTxt === []) {
            return null;
        }

        $system = <<<'SYS'
Sos un asistente del ERP anitaERP. Redactás un resumen breve en español rioplatense
a partir de HECHOS ya obtenidos del sistema. Reglas estrictas:
- NO inventes saldos, fechas, montos ni códigos que no estén en los hechos.
- NO digas que "estimás" o "suponés" números.
- 2 a 5 oraciones; tono operativo y claro.
- Si hay tabla, mencioná solo totales o filas destacadas que figuren en los hechos.
- Podés sugerir un siguiente paso de consulta (sin ejecutar nada).
- Respondé SOLO el texto del resumen, sin JSON ni markdown.
SYS;

        $user = "Pregunta del usuario:\n".$pregunta
            ."\n\nIntent: ".$intent
            ."\nInterpretación: ".$interpretacion
            ."\n\nHechos del ERP:\n".implode("\n", $hechos);
        if ($filasTxt !== []) {
            $user .= "\n\nMuestra de tabla (máx. 12 filas):\n".implode("\n", $filasTxt);
        }
        $user .= "\n\nResumen:";

        $prompt = new AiPrompt(
            prompt: $user,
            system: $system,
            esperaJson: false,
            temperature: 0.2,
            maxTokens: 450,
            timeout: (int) config('ai.skills.consultar_contexto_operativo.llm_redactar_timeout', 40),
            meta: ['skill' => 'consultar_contexto_operativo', 'fase' => 'redactar_nl'],
        );

        try {
            $pack = self::generarConFailover($prompt);
        } catch (Throwable $e) {
            return null;
        }

        $result = $pack['result'];
        if (! $result->ok) {
            return null;
        }

        $texto = trim((string) ($result->texto ?? ''));
        if ($texto === '') {
            return null;
        }
        if ($texto[0] === '{' && str_contains($texto, 'resumen')) {
            $decoded = json_decode($texto, true);
            if (is_array($decoded) && ! empty($decoded['resumen']) && is_string($decoded['resumen'])) {
                $texto = trim($decoded['resumen']);
            }
        }

        $texto = preg_replace('/^["“]|["”]$/u', '', $texto) ?? $texto;
        $texto = trim($texto);
        if (mb_strlen($texto) < 20) {
            return null;
        }

        self::$ultimaFuente = $pack['fuente'];

        return mb_substr($texto, 0, 1200);
    }

    /**
     * @return array{result: AiResult, fuente: string}
     */
    private static function generarConFailover(AiPrompt $prompt): array
    {
        /** @var AiGateway $gateway */
        $gateway = app(AiGateway::class);
        $local = $gateway->generar($prompt);
        if ($local->ok && trim($local->texto) !== '') {
            return ['result' => $local, 'fuente' => 'llm'];
        }

        if (! AiLlmCadenaExternaSupport::habilitadoParaCopiloto()) {
            return ['result' => $local, 'fuente' => 'llm'];
        }

        $timeoutExterno = (int) config(
            'ai.skills.consultar_contexto_operativo.llm_failover_timeout',
            config('consulta_inteligente.llm_timeout', 8)
        );
        $externo = AiLlmCadenaExternaSupport::generar(new AiPrompt(
            prompt: $prompt->prompt,
            system: $prompt->system,
            esperaJson: false,
            temperature: $prompt->temperature,
            maxTokens: $prompt->maxTokens,
            timeout: $timeoutExterno,
            meta: array_merge($prompt->meta, [
                'failover_desde' => $local->driver !== '' ? $local->driver : 'ollama',
                'failover_error' => $local->error,
            ]),
        ));

        if ($externo->ok && trim($externo->texto) !== '') {
            return ['result' => $externo, 'fuente' => 'llm_externo'];
        }

        return ['result' => $local->ok ? $local : $externo, 'fuente' => 'llm'];
    }
}
