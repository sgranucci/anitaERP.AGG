<?php

namespace App\Support\Ai;

use Illuminate\Support\Facades\Session;

/**
 * Contexto multi-turn del copiloto (sesión): último intent/params para seguimientos
 * («y el mes pasado», «de biyemas», «con CC 85»).
 */
final class AiConsultaOperativaContextoSupport
{
    public const SESSION_KEY = 'ai_consulta_operativa_contexto';

    public static function habilitado(): bool
    {
        return filter_var(
            config('ai.skills.consultar_contexto_operativo.multiturn', true),
            FILTER_VALIDATE_BOOLEAN
        );
    }

    /**
     * @return array{
     *   intent: string,
     *   params: array<string,mixed>,
     *   pregunta?: string|null,
     *   interpretacion?: string|null,
     *   resumen?: string|null,
     *   ts?: int
     * }|null
     */
    public static function leer(): ?array
    {
        if (! self::habilitado()) {
            return null;
        }
        $ctx = Session::get(self::SESSION_KEY);
        if (! is_array($ctx) || empty($ctx['intent'])) {
            return null;
        }

        return $ctx;
    }

    /**
     * @param  array<string,mixed>  $params
     */
    public static function guardar(
        string $intent,
        array $params,
        ?string $pregunta = null,
        ?string $interpretacion = null,
        ?string $resumen = null,
    ): void {
        if (! self::habilitado()) {
            return;
        }

        $seguros = [];
        foreach ($params as $k => $v) {
            if (is_scalar($v) || $v === null) {
                $seguros[(string) $k] = $v;
            } elseif (is_array($v) && in_array((string) $k, ['campos_excluir'], true)) {
                $seguros[(string) $k] = array_values(array_filter($v, 'is_scalar'));
            }
        }

        Session::put(self::SESSION_KEY, [
            'intent' => strtolower(trim($intent)),
            'params' => $seguros,
            'pregunta' => $pregunta,
            'interpretacion' => $interpretacion,
            'resumen' => $resumen !== null ? mb_substr($resumen, 0, 400) : null,
            'ts' => time(),
        ]);
    }

    public static function limpiar(): void
    {
        Session::forget(self::SESSION_KEY);
    }

    /**
     * ¿La frase parece un seguimiento del turno anterior (no una consulta nueva completa)?
     *
     * @param  array<string,mixed>  $contexto
     */
    public static function pareceSeguimiento(string $pregunta, array $contexto): bool
    {
        if (($contexto['intent'] ?? '') === '') {
            return false;
        }

        $t = trim(preg_replace('/\s+/u', ' ', $pregunta) ?? '');
        if ($t === '' || mb_strlen($t) > 120) {
            return false;
        }

        $norm = mb_strtolower($t, 'UTF-8');
        $norm = strtr($norm, [
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n',
        ]);

        // Frases que arrancan una consulta nueva (no fusionar)
        if (preg_match('/\b(saldo|mayor|kardex|asiento|factura|proveedor|cliente|oc\b|orden|manual|kpi|pedido consumo|articulo|insumo|cheque|consulta inteligente)\b/u', $norm) === 1
            && ! preg_match('/^(y |ahora |tambien |también |mismo |misma |solo |con |sin |de |del |la |el )/u', $norm)) {
            // «saldo del proveedor…» es consulta nueva; «y julio» no entra acá
            if (! preg_match('/^(y |ahora |tambien |también |lo mismo|la misma|eso |esa |ese )/u', $norm)) {
                return false;
            }
        }

        if (preg_match('/^(y |ahora |tambien |también |lo mismo|la misma|eso |esa |ese |solo |con |sin )/u', $norm) === 1) {
            return true;
        }

        // Solo período / empresa / CC / filtros cortos
        if (preg_match('/^(este mes|mes pasado|mes anterior|julio|enero|febrero|marzo|abril|mayo|junio|agosto|septiembre|setiembre|octubre|noviembre|diciembre)(\s+\d{4})?$/u', $norm) === 1) {
            return true;
        }
        if (preg_match('/^(empresa\s+\S+|biyemas|kandiko|rebisco|cc\s*\d+|sin centro( de costo)?|solo deuda)$/u', $norm) === 1) {
            return true;
        }
        if (preg_match('/^(mas|más)\s+detalle$/u', $norm) === 1) {
            return true;
        }

        return false;
    }

    /**
     * Fusiona params del contexto con los del turno actual (el actual gana en claves presentes).
     *
     * @param  array<string,mixed>  $previos
     * @param  array<string,mixed>  $nuevos
     * @return array<string,mixed>
     */
    public static function fusionarParams(array $previos, array $nuevos): array
    {
        $out = $previos;
        foreach ($nuevos as $k => $v) {
            if ($v === null || $v === '') {
                continue;
            }
            if (is_bool($v) && $v === false && array_key_exists($k, $out)) {
                unset($out[$k]);

                continue;
            }
            $out[$k] = $v;
        }

        return $out;
    }

    /**
     * Texto corto para el prompt del LLM router.
     *
     * @param  array<string,mixed>|null  $contexto
     */
    public static function resumenParaPrompt(?array $contexto): string
    {
        if ($contexto === null || empty($contexto['intent'])) {
            return '';
        }
        $params = $contexto['params'] ?? [];
        $parts = [];
        foreach (['cuenta_codigo', 'cuenta_nombre', 'codigo', 'sku', 'numero', 'numero_oc', 'empresa_nombre', 'centrocosto_codigo', 'fecha_desde', 'fecha_hasta'] as $k) {
            if (! empty($params[$k]) && is_scalar($params[$k])) {
                $parts[] = $k.'='.$params[$k];
            }
        }

        return 'Turno anterior: intent='.$contexto['intent']
            .(isset($contexto['pregunta']) && $contexto['pregunta'] !== '' ? '; pregunta="'.mb_substr((string) $contexto['pregunta'], 0, 120).'"' : '')
            .($parts !== [] ? '; params: '.implode(', ', $parts) : '')
            .(! empty($contexto['interpretacion']) ? '; entendí="'.mb_substr((string) $contexto['interpretacion'], 0, 100).'"' : '');
    }
}
