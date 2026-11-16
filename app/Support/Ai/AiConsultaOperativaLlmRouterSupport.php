<?php

namespace App\Support\Ai;

use App\Services\Ai\AiGateway;
use App\Services\Ai\AiPrompt;
use App\Services\Ai\AiResult;
use Throwable;

/**
 * Clasificador NL → JSON tipado vía AiGateway (Ollama) con failover a cadena CI_LLM_*.
 */
final class AiConsultaOperativaLlmRouterSupport
{
    public static function habilitado(): bool
    {
        return filter_var(config('ai.skills.consultar_contexto_operativo.llm_router', false), FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * @param  array<string,mixed>|null  $contextoPrevio
     * @return array{
     *   ok: bool,
     *   intent?: string,
     *   params?: array<string,mixed>,
     *   interpretacion?: string,
     *   needs_clarification?: bool,
     *   clarification?: string,
     *   fuente?: string,
     *   error?: string,
     *   sugerencias?: list<string>
     * }
     */
    public static function interpretar(string $pregunta, ?array $contextoPrevio = null): array
    {
        if (! self::habilitado()) {
            return [
                'ok' => false,
                'error' => 'Router LLM deshabilitado.',
                'fuente' => 'llm',
                'sugerencias' => AiConsultaOperativaRouterSupport::ejemplos(),
            ];
        }

        $catalogo = AiConsultaOperativaSupport::intentsEtiquetas();
        $lineas = [];
        foreach ($catalogo as $clave => $etiqueta) {
            $lineas[] = '- '.$clave.': '.$etiqueta;
        }

        $system = <<<'SYS'
Sos un clasificador de consultas operativas de un ERP argentino (anitaERP).
NO inventes saldos ni movimientos. Solo interpretá la frase y devolvé JSON estricto.

Formato JSON obligatorio (una sola de estas formas):
1) {"intent":"...","params":{...},"interpretacion":"..."}
2) {"needs_clarification":true,"clarification":"pregunta concreta al usuario"}

Reglas:
- intent debe ser uno del catálogo.
- Si hay "Turno anterior" y el usuario solo cambia período/empresa/CC («y el mes pasado», «de biyemas»), reutilizá ese intent y fusioná params.
- "saldo/deuda/CT del proveedor" → proveedor_ctacte (codigo); ficha sin saldo → proveedor.
- "saldo/deuda/CT del cliente" → cliente_ctacte (codigo); ficha → cliente.
- "asiento N" → asiento (numero).
- "factura proveedor" → comprobante_proveedor; "factura de venta" → factura_venta.
- "saldo de la cuenta N o nombre" → saldo_cuenta; "mayor de la cuenta N o nombre" → mayor_cuenta.
  Cuenta: params.cuenta_codigo (número) o cuenta_nombre (texto, ej. "caja y bancos"). Empresa: empresa_id, empresa_codigo o empresa_nombre (ej. "biyemas").
  Opcional: centrocosto_codigo/CC, numero_oc, fecha_desde/fecha_hasta.
- "mayor de la OC N" / "movimientos del mayor de la OC" → mayor_cuenta con params.numero_oc (cuenta opcional).
- "saldo del artículo/insumo" → articulo_saldo; "kardex/movimientos" → articulo_kardex (valor/sku).
- "artículos de compra del sku / de tal insumo" → formula_compra (params.sku). Solo lectura.
- "en qué fórmulas aparece el insumo / sku" → formula_uso (params.sku). Solo lectura. No modifica la fórmula.
- "precios de costo / cómo saltaron los precios / historial de precios / últimas N compras del insumo o sku" → historial_precios_compra (params.sku, opcional params.limite=3, solo_insumo). Solo lectura COM.
- "proyección de pagos / qué vence para pagar / pagos a vencer" → proyeccion_pagos (opcional vencidos=true, empresa).
- "contratos por vencer / OC a vencer / suscripciones que vencen" → contratos_por_vencer (opcional dias_horizonte, tipo_alerta).
- "precio por proveedor / comparar precios del sku" → precio_por_proveedor (params.sku).
- "existencias / saldos por depósito del sku" → existencias_deposito (params.sku).
- "movimientos / mayor sin centro de costo" → mayor_cuenta con params.sin_centrocosto=true. No imputa el centro.
- "conciliación bancaria cuenta de caja X" → conciliacion_bancaria (params.cuentacaja_codigo). Solo lee la última corrida; no concilia.
- "diferencias del turno / gastronomía" → conciliacion_turno_gastronomia. Solo explica; no cierra ni cobra.
- "insumo" → solo_insumo=true. Tolera typos (muzarella/mozarella).
- "qué hago / plan para / desvíos" → plan_agente (params.evento: desvio_conciliacion|deuda_proveedor|deuda_cliente|firma_oc|stock_insumo).
- "pedido consumo / qué pedimos / planear pedido" → pedido_consumo_sector (params.centrocosto_codigo o codigo, deposito_id o deposito_codigo; opcional fecha_desde/fecha_hasta, dias_cobertura).
- "KPI / dashboard / resumen operativo de compras" → compras_kpi_resumen.
- "KPI proceso / ciclo de compra / gestión OC / % OC abiertas / circuito hasta COM" → compras_kpi_proceso.
- "KPI productividad / OC por comprador / ahorro por comprador" → compras_kpi_productividad.
- "OC pendientes de firma" → oc_pendientes_firma; "OC vencidas sin recepción" → oc_vencidas_sin_recepcion.
- "lead time OC recepción" → lead_time_oc_recepcion; "top proveedores por monto" → top_proveedores_monto; "RQ/requisiciones sin OC" → rq_sin_oc.
- "cómo hago / manual / ayuda / documentación" → consultar_manual (params.valor = frase completa).
- "cheques para depositar / en cartera / pagos a proveedores / remitos / listados explorador" → consulta_inteligente (params.pregunta = frase completa).
- OC estado/firma puntual (una OC N) → ordencompra / arbol_oc (no confundir con mayor de OC ni con KPIs agregados).
- Período: fecha_desde/fecha_hasta ISO; "este mes", "mes pasado", "julio"/"julio 2026".
- Opcional: deposito_codigo, max_lineas 1..80, campos_excluir, cruzar_con=proveedor, solo_deuda, empresa_id/empresa_nombre, centrocosto_codigo, numero_oc.
- Si falta dato crítico o hay varias cuentas/empresas ambiguas, pedí aclaración; no inventes saldos ni KPIs.
SYS;

        $ctxTxt = AiConsultaOperativaContextoSupport::resumenParaPrompt($contextoPrevio);
        $user = "Catálogo de intents:\n".implode("\n", $lineas)
            ."\n\nEjemplos:\n- ".implode("\n- ", AiConsultaOperativaRouterSupport::ejemplos());
        if ($ctxTxt !== '') {
            $user .= "\n\n".$ctxTxt;
        }
        $user .= "\n\nConsulta del usuario:\n".$pregunta
            ."\n\nRespondé SOLO JSON.";

        $prompt = new AiPrompt(
            prompt: $user,
            system: $system,
            esperaJson: true,
            temperature: 0.05,
            maxTokens: 512,
            timeout: (int) config('ai.skills.consultar_contexto_operativo.llm_timeout', 45),
            meta: ['skill' => 'consultar_contexto_operativo', 'fase' => 'router_nl'],
        );

        try {
            $pack = self::generarConFailover($prompt);
        } catch (Throwable $e) {
            return [
                'ok' => false,
                'error' => 'No se pudo consultar el modelo: '.$e->getMessage(),
                'fuente' => 'llm',
                'sugerencias' => AiConsultaOperativaRouterSupport::ejemplos(),
            ];
        }

        $result = $pack['result'];
        $fuente = $pack['fuente'];

        if (! $result->ok || ! is_array($result->json)) {
            return [
                'ok' => false,
                'error' => $result->error ?? 'El modelo no devolvió JSON válido.',
                'fuente' => $fuente,
                'sugerencias' => AiConsultaOperativaRouterSupport::ejemplos(),
            ];
        }

        $validado = AiConsultaOperativaSchemaSupport::validarPlan($result->json);
        $validado['fuente'] = $fuente;
        if ($fuente === 'llm_externo' && ($validado['ok'] ?? false) && ! empty($validado['interpretacion'])) {
            $prov = (string) ($result->meta['proveedor'] ?? '');
            if ($prov !== '' && ! str_contains((string) $validado['interpretacion'], $prov)) {
                $validado['interpretacion'] = trim((string) $validado['interpretacion']).' · '.$prov;
            }
        }

        return $validado;
    }

    /**
     * Ollama (AiGateway) → si falla, cadena CI_LLM_* (Groq/Gemini/…).
     *
     * @return array{result: AiResult, fuente: string}
     */
    private static function generarConFailover(AiPrompt $prompt): array
    {
        /** @var AiGateway $gateway */
        $gateway = app(AiGateway::class);
        $local = $gateway->generar($prompt);
        if ($local->ok && is_array($local->json)) {
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
            esperaJson: true,
            temperature: $prompt->temperature,
            maxTokens: $prompt->maxTokens,
            timeout: $timeoutExterno,
            meta: array_merge($prompt->meta, [
                'failover_desde' => $local->driver !== '' ? $local->driver : 'ollama',
                'failover_error' => $local->error,
            ]),
        ));

        if ($externo->ok && is_array($externo->json)) {
            return ['result' => $externo, 'fuente' => 'llm_externo'];
        }

        // Devolver el error más útil (preferir externo si ambos fallaron)
        $msg = trim(($local->error ?? 'Ollama sin respuesta').' | externo: '.($externo->error ?? 'sin respuesta'));

        return [
            'result' => AiResult::fallo($msg, $externo->driver ?: $local->driver, $externo->model ?: $local->model),
            'fuente' => 'llm',
        ];
    }
}
