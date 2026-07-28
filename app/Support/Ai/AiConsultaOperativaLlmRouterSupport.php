<?php

namespace App\Support\Ai;

use App\Services\Ai\AiGateway;
use App\Services\Ai\AiPrompt;
use Throwable;

/**
 * Clasificador NL → JSON tipado vía AiGateway (solo interpretación; sin números de negocio).
 */
final class AiConsultaOperativaLlmRouterSupport
{
    public static function habilitado(): bool
    {
        return filter_var(config('ai.skills.consultar_contexto_operativo.llm_router', false), FILTER_VALIDATE_BOOLEAN);
    }

    /**
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
    public static function interpretar(string $pregunta): array
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
- "saldo/deuda/CT del proveedor" → proveedor_ctacte (codigo); ficha sin saldo → proveedor.
- "saldo/deuda/CT del cliente" → cliente_ctacte (codigo); ficha → cliente.
- "asiento N" → asiento (numero).
- "factura proveedor" → comprobante_proveedor; "factura de venta" → factura_venta.
- "saldo de la cuenta N" → saldo_cuenta; "mayor de la cuenta N" → mayor_cuenta.
- "saldo del artículo/insumo" → articulo_saldo; "kardex/movimientos" → articulo_kardex (valor/sku).
- "insumo" → solo_insumo=true. Tolera typos (muzarella/mozarella).
- "qué hago / plan para / desvíos" → plan_agente (params.evento: desvio_conciliacion|deuda_proveedor|deuda_cliente|firma_oc|stock_insumo).
- "pedido consumo / qué pedimos / planear pedido" → pedido_consumo_sector (params.centrocosto_codigo o codigo, deposito_id o deposito_codigo; opcional fecha_desde/fecha_hasta, dias_cobertura).
- "cómo hago / manual / ayuda / documentación" → consultar_manual (params.valor = frase completa).
- OC → ordencompra; firmar/árbol → arbol_oc.
- Período: fecha_desde/fecha_hasta ISO; "este mes", "mes pasado", "julio"/"julio 2026".
- Opcional: deposito_codigo, max_lineas 1..80, campos_excluir, cruzar_con=proveedor, solo_deuda.
- Si falta dato crítico, pedí aclaración; no inventes saldos.
SYS;

        $user = "Catálogo de intents:\n".implode("\n", $lineas)
            ."\n\nEjemplos:\n- ".implode("\n- ", AiConsultaOperativaRouterSupport::ejemplos())
            ."\n\nConsulta del usuario:\n".$pregunta
            ."\n\nRespondé SOLO JSON.";

        try {
            /** @var AiGateway $gateway */
            $gateway = app(AiGateway::class);
            $result = $gateway->generar(new AiPrompt(
                prompt: $user,
                system: $system,
                esperaJson: true,
                temperature: 0.05,
                maxTokens: 512,
                timeout: (int) config('ai.skills.consultar_contexto_operativo.llm_timeout', 45),
                meta: ['skill' => 'consultar_contexto_operativo', 'fase' => 'router_nl'],
            ));
        } catch (Throwable $e) {
            return [
                'ok' => false,
                'error' => 'No se pudo consultar el modelo: '.$e->getMessage(),
                'fuente' => 'llm',
                'sugerencias' => AiConsultaOperativaRouterSupport::ejemplos(),
            ];
        }

        if (! $result->ok || ! is_array($result->json)) {
            return [
                'ok' => false,
                'error' => $result->error ?? 'El modelo no devolvió JSON válido.',
                'fuente' => 'llm',
                'sugerencias' => AiConsultaOperativaRouterSupport::ejemplos(),
            ];
        }

        $validado = AiConsultaOperativaSchemaSupport::validarPlan($result->json);
        $validado['fuente'] = 'llm';

        return $validado;
    }
}
