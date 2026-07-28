<?php

namespace App\Support\Ai;

/**
 * Allowlist de intents/params del diálogo operativo (validación post-LLM / post-reglas).
 */
final class AiConsultaOperativaSchemaSupport
{
    /** Tope chat (mayor/CT con tabla scrolleable). */
    public const MAX_LINEAS = 80;

    /** Tope para Excel (mayor / CT). */
    public const MAX_LINEAS_EXPORT = 500;

    /** @var list<string> */
    public const CAMPOS_MAYOR = ['fecha', 'asiento', 'debe', 'haber', 'detalle', 'contraparte', 'proveedor'];

    /** @var list<string> */
    public const CRUZAR_CON = ['proveedor'];

    /** @return list<string> */
    public static function intentsPermitidos(): array
    {
        return array_keys(AiConsultaOperativaSupport::intentsEtiquetas());
    }

    /**
     * Normaliza y valida un plan tipado.
     *
     * @param  array<string,mixed>  $plan
     * @return array{
     *   ok: bool,
     *   intent?: string,
     *   params?: array<string,mixed>,
     *   interpretacion?: string,
     *   needs_clarification?: bool,
     *   clarification?: string,
     *   error?: string,
     *   sugerencias?: list<string>
     * }
     */
    public static function validarPlan(array $plan): array
    {
        if (! empty($plan['needs_clarification']) || ! empty($plan['clarification'])) {
            $pregunta = trim((string) ($plan['clarification'] ?? $plan['pregunta_aclaracion'] ?? ''));
            if ($pregunta === '') {
                $pregunta = 'Necesito un dato más para completar la consulta (código, cuenta o período).';
            }

            return [
                'ok' => false,
                'needs_clarification' => true,
                'clarification' => $pregunta,
                'error' => $pregunta,
                'sugerencias' => AiConsultaOperativaRouterSupport::ejemplos(),
            ];
        }

        $intent = strtolower(trim((string) ($plan['intent'] ?? '')));
        if ($intent === '' || ! in_array($intent, self::intentsPermitidos(), true)) {
            return [
                'ok' => false,
                'error' => 'Intent no reconocido. Reformule o use un ejemplo.',
                'sugerencias' => AiConsultaOperativaRouterSupport::ejemplos(),
            ];
        }

        $paramsIn = $plan['params'] ?? [];
        if (! is_array($paramsIn)) {
            $paramsIn = [];
        }

        $params = self::normalizarParams($intent, $paramsIn);
        $interpretacion = trim((string) ($plan['interpretacion'] ?? ''));
        if ($interpretacion === '') {
            $mapa = AiConsultaOperativaSupport::intentsEtiquetas();
            $interpretacion = $mapa[$intent] ?? $intent;
        }

        return [
            'ok' => true,
            'intent' => $intent,
            'params' => $params,
            'interpretacion' => $interpretacion,
        ];
    }

    /**
     * @param  array<string,mixed>  $params
     * @return array<string,mixed>
     */
    public static function normalizarParams(string $intent, array $params): array
    {
        $out = [];

        foreach (['sku', 'codigo', 'documento', 'numero', 'valor', 'cuenta_codigo', 'deposito_codigo', 'evento', 'descripcion'] as $k) {
            if (isset($params[$k]) && is_scalar($params[$k])) {
                $v = trim((string) $params[$k]);
                if ($v !== '') {
                    $out[$k] = $v;
                }
            }
        }

        if (isset($params['empresa_id']) && is_numeric($params['empresa_id']) && (int) $params['empresa_id'] > 0) {
            $out['empresa_id'] = (int) $params['empresa_id'];
        }
        if (isset($params['deposito_id']) && is_numeric($params['deposito_id']) && (int) $params['deposito_id'] > 0) {
            $out['deposito_id'] = (int) $params['deposito_id'];
        }

        if (isset($params['centrocosto_id']) && is_numeric($params['centrocosto_id']) && (int) $params['centrocosto_id'] > 0) {
            $out['centrocosto_id'] = (int) $params['centrocosto_id'];
        }
        if (isset($params['centrocosto_codigo']) && is_scalar($params['centrocosto_codigo'])) {
            $v = trim((string) $params['centrocosto_codigo']);
            if ($v !== '') {
                $out['centrocosto_codigo'] = $v;
            }
        }
        if (isset($params['deposito_consumo_id']) && is_numeric($params['deposito_consumo_id']) && (int) $params['deposito_consumo_id'] > 0) {
            $out['deposito_consumo_id'] = (int) $params['deposito_consumo_id'];
        }
        if (isset($params['deposito_origen_id']) && is_numeric($params['deposito_origen_id']) && (int) $params['deposito_origen_id'] > 0) {
            $out['deposito_origen_id'] = (int) $params['deposito_origen_id'];
        }
        if (isset($params['dias_cobertura']) && is_numeric($params['dias_cobertura'])) {
            $out['dias_cobertura'] = max(1, (int) $params['dias_cobertura']);
        }
        if (isset($params['multiplicador_evento']) && is_numeric($params['multiplicador_evento'])) {
            $out['multiplicador_evento'] = (float) $params['multiplicador_evento'];
        }
        if (! empty($params['solo_sabados'])) {
            $out['solo_sabados'] = true;
        }
        if (isset($params['lead_time_dias']) && is_numeric($params['lead_time_dias'])) {
            $out['lead_time_dias'] = max(0, (int) $params['lead_time_dias']);
        }
        if (isset($params['buffer_dias']) && is_numeric($params['buffer_dias'])) {
            $out['buffer_dias'] = max(0, (int) $params['buffer_dias']);
        }
        if (! empty($params['solo_insumo'])
            && in_array($intent, [
                AiConsultaOperativaSupport::INTENT_ARTICULO_SALDO,
                AiConsultaOperativaSupport::INTENT_ARTICULO_KARDEX,
                AiConsultaOperativaSupport::INTENT_PEDIDO_CONSUMO_SECTOR,
            ], true)) {
            $out['solo_insumo'] = true;
        }
        if (! empty($params['solo_deuda'])
            && $intent === AiConsultaOperativaSupport::INTENT_PROVEEDOR_CTACTE) {
            $out['solo_deuda'] = true;
        }

        foreach (['fecha_desde', 'fecha_hasta'] as $k) {
            if (! empty($params[$k]) && is_string($params[$k])) {
                $iso = self::normalizarFecha($params[$k]);
                if ($iso !== null) {
                    $out[$k] = $iso;
                }
            }
        }

        $modoExport = ! empty($params['modo_export']);
        $tope = $modoExport ? self::MAX_LINEAS_EXPORT : self::MAX_LINEAS;
        $defaultMax = $modoExport ? min(200, $tope) : 60;
        $max = isset($params['max_lineas']) ? (int) $params['max_lineas'] : $defaultMax;
        $out['max_lineas'] = max(1, min($tope, $max > 0 ? $max : $defaultMax));
        if ($modoExport) {
            $out['modo_export'] = true;
        }

        $excluir = $params['campos_excluir'] ?? [];
        if (is_string($excluir)) {
            $excluir = preg_split('/[,\s]+/', $excluir) ?: [];
        }
        if (is_array($excluir)) {
            $out['campos_excluir'] = array_values(array_intersect(
                array_map(static fn ($c) => strtolower(trim((string) $c)), $excluir),
                self::CAMPOS_MAYOR
            ));
        } else {
            $out['campos_excluir'] = [];
        }

        $cruzar = isset($params['cruzar_con']) ? strtolower(trim((string) $params['cruzar_con'])) : '';
        if ($cruzar !== '' && in_array($cruzar, self::CRUZAR_CON, true)) {
            $out['cruzar_con'] = $cruzar;
        }

        // Aliases por intent
        return match ($intent) {
            AiConsultaOperativaSupport::INTENT_ARTICULO_SALDO,
            AiConsultaOperativaSupport::INTENT_ARTICULO_KARDEX => self::aliasValor($out, 'sku'),
            AiConsultaOperativaSupport::INTENT_CLIENTE,
            AiConsultaOperativaSupport::INTENT_CLIENTE_CTACTE,
            AiConsultaOperativaSupport::INTENT_PROVEEDOR,
            AiConsultaOperativaSupport::INTENT_PROVEEDOR_CTACTE => self::aliasValor($out, 'codigo'),
            AiConsultaOperativaSupport::INTENT_ORDENCOMPRA,
            AiConsultaOperativaSupport::INTENT_ARBOL_OC,
            AiConsultaOperativaSupport::INTENT_ASIENTO,
            AiConsultaOperativaSupport::INTENT_COMPROBANTE_PROVEEDOR,
            AiConsultaOperativaSupport::INTENT_FACTURA_VENTA => self::aliasValor($out, 'numero'),
            AiConsultaOperativaSupport::INTENT_SALDO_CUENTA,
            AiConsultaOperativaSupport::INTENT_MAYOR_CUENTA => self::aliasValor($out, 'cuenta_codigo'),
            AiConsultaOperativaSupport::INTENT_PLAN_AGENTE => $out,
            AiConsultaOperativaSupport::INTENT_PEDIDO_CONSUMO_SECTOR => self::aliasValor($out, 'codigo'),
            default => $out,
        };
    }

    /**
     * @param  array<string,mixed>  $out
     * @return array<string,mixed>
     */
    private static function aliasValor(array $out, string $clave): array
    {
        if (empty($out[$clave]) && ! empty($out['valor'])) {
            $out[$clave] = $out['valor'];
        }
        if (! empty($out[$clave]) && empty($out['valor'])) {
            $out['valor'] = $out[$clave];
        }

        return $out;
    }

    private static function normalizarFecha(string $raw): ?string
    {
        $raw = trim($raw);
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $raw) === 1) {
            return $raw;
        }
        if (preg_match('/^(\d{1,2})[\/\-](\d{1,2})[\/\-](\d{4})$/', $raw, $m) === 1) {
            return sprintf('%04d-%02d-%02d', (int) $m[3], (int) $m[2], (int) $m[1]);
        }

        return null;
    }
}
