<?php

namespace App\Http\Controllers\Configuracion;

use App\Exports\Configuracion\AiConsultaOperativaExport;
use App\Http\Controllers\Controller;
use App\Services\Ai\ConsultarContextoOperativoSkill;
use App\Services\Ai\Skills\AiSkillContext;
use App\Services\Ai\Skills\AiSkillRegistry;
use App\Support\Ai\AiConsultaOperativaRouterSupport;
use App\Support\Ai\AiConsultaOperativaSchemaSupport;
use App\Support\Ai\AiConsultaOperativaSupport;
use App\Support\Ai\PedidoConsumoSectorConfirmacionSupport;
use App\Support\Ai\PedidoConsumoSectorProyeccionSupport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Diálogo operativo Fase C: NL (reglas/LLM) → intent tipado + grounding + Excel.
 */
class AiConsultaController extends Controller
{
    public function __construct(
        private AiSkillRegistry $registry,
        private PedidoConsumoSectorConfirmacionSupport $pedidoConfirmacion,
    ) {}

    public function intents(): JsonResponse
    {
        can('ejecutar-consulta-ia');

        if (! $this->registry->tiene(ConsultarContextoOperativoSkill::NOMBRE)) {
            return response()->json(['ok' => false, 'error' => 'Skill no registrada.'], 503);
        }

        $etiquetas = AiConsultaOperativaSupport::intentsEtiquetasPermitidos();
        $items = [];
        foreach ($etiquetas as $clave => $etiqueta) {
            $items[] = [
                'intent' => $clave,
                'etiqueta' => $etiqueta,
                'placeholder' => $this->placeholderIntent($clave),
                'grupo' => $this->grupoIntent($clave),
                'auto_pregunta' => $this->autoPreguntaIntent($clave),
            ];
        }

        return response()->json([
            'ok' => true,
            'intents' => $items,
            'grupos' => $this->gruposEtiquetas(),
            'ejemplos' => AiConsultaOperativaRouterSupport::ejemplosPermitidos(),
            'llm_router' => filter_var(config('ai.skills.consultar_contexto_operativo.llm_router', false), FILTER_VALIDATE_BOOLEAN),
            'skill' => ConsultarContextoOperativoSkill::NOMBRE,
        ]);
    }

    public function consultar(Request $request): JsonResponse
    {
        can('ejecutar-consulta-ia');

        $plan = $this->resolverPlan($request, false);
        if (! ($plan['ok'] ?? false)) {
            return response()->json($plan, 422);
        }

        $intent = (string) $plan['intent'];
        $params = $plan['params'];
        $interpretacion = $plan['interpretacion'];
        $fuente = $plan['fuente'];
        $pregunta = $plan['pregunta'];
        $empresaId = $plan['empresa_id'];

        if (! AiConsultaOperativaSupport::usuarioPuedeIntent($intent)) {
            return response()->json([
                'ok' => false,
                'error' => 'Su rol no puede ejecutar esta consulta IA (p. ej. mayor contable).',
                'interpretacion' => $interpretacion,
                'intent' => $intent,
                'fuente' => $fuente,
                'sugerencias' => AiConsultaOperativaRouterSupport::ejemplosPermitidos(),
            ], 403);
        }

        $result = $this->registry->ejecutar(
            ConsultarContextoOperativoSkill::NOMBRE,
            new AiSkillContext(
                entradas: [
                    'intent' => $intent,
                    'params' => $params,
                    'pregunta' => $pregunta,
                    'fuente_router' => $fuente,
                ],
                empresaId: $empresaId,
                entidadTipo: 'consulta_operativa',
            )
        );

        if (! $result->ok) {
            return response()->json([
                'ok' => false,
                'error' => $result->error ?? 'No se pudo completar la consulta.',
                'interpretacion' => $interpretacion,
                'intent' => $intent,
                'fuente' => $fuente,
                'ai_decision_id' => $result->decisionId,
                'sugerencias' => AiConsultaOperativaRouterSupport::ejemplosPermitidos(),
            ], 422);
        }

        return response()->json([
            'ok' => true,
            'intent' => $result->datos['intent'] ?? $intent,
            'interpretacion' => $interpretacion,
            'fuente' => $fuente,
            'parrafos' => $result->datos['parrafos'] ?? $result->advertencias,
            'links' => $result->datos['links'] ?? [],
            'tabla' => $result->datos['tabla'] ?? null,
            'datos' => $result->datos['datos'] ?? [],
            'params' => $params,
            'pregunta' => $pregunta,
            'exportable' => true,
            'score' => $result->score,
            'ai_decision_id' => $result->decisionId,
        ]);
    }

    /**
     * HITL: crea RQ compra o sala desde el borrador sugerido por pedido_consumo_sector.
     */
    public function confirmarPedidoConsumo(Request $request): JsonResponse
    {
        can('ejecutar-consulta-ia');

        $tipo = strtolower(trim((string) $request->input('tipo', '')));
        $decisionId = $request->input('ai_decision_id');
        $decisionId = is_numeric($decisionId) ? (int) $decisionId : null;
        $borrador = $request->input('borrador');
        if (is_string($borrador)) {
            $decoded = json_decode($borrador, true);
            $borrador = is_array($decoded) ? $decoded : [];
        }
        if (! is_array($borrador) || $borrador === []) {
            return response()->json(['ok' => false, 'message' => 'Borrador inválido.'], 422);
        }

        if ($tipo === PedidoConsumoSectorProyeccionSupport::DOCUMENTO_COMPRA) {
            can('crear-requisicion');
        } elseif ($tipo === PedidoConsumoSectorProyeccionSupport::DOCUMENTO_SALA) {
            can('crear-requisicion-sala');
        } else {
            return response()->json(['ok' => false, 'message' => 'Tipo debe ser compra o sala.'], 422);
        }

        $resultado = $this->pedidoConfirmacion->confirmar($tipo, $borrador, $decisionId);

        return response()->json($resultado, ($resultado['ok'] ?? false) ? 200 : 422);
    }

    /**
     * Excel de la última consulta tipada (re-ejecuta grounding con más filas).
     */
    public function exportar(Request $request, ?string $formato = 'EXCEL'): BinaryFileResponse|JsonResponse
    {
        can('ejecutar-consulta-ia');

        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        $formato = strtoupper(trim((string) ($formato ?: $request->input('formato', 'EXCEL'))));
        if ($formato !== 'EXCEL' && $formato !== 'CSV') {
            return response()->json(['ok' => false, 'error' => 'Formato no soportado. Use EXCEL o CSV.'], 422);
        }

        $plan = $this->resolverPlan($request, true);
        if (! ($plan['ok'] ?? false)) {
            return response()->json($plan, 422);
        }

        $intent = (string) $plan['intent'];
        $params = $plan['params'];
        if (! AiConsultaOperativaSupport::usuarioPuedeIntent($intent)) {
            return response()->json([
                'ok' => false,
                'error' => 'Su rol no puede exportar esta consulta IA.',
            ], 403);
        }
        $resultado = AiConsultaOperativaSupport::consultar($intent, $params);
        if (! ($resultado['ok'] ?? false)) {
            return response()->json([
                'ok' => false,
                'error' => $resultado['error'] ?? 'No se pudo exportar la consulta.',
            ], 422);
        }

        $export = new AiConsultaOperativaExport([
            'interpretacion' => $plan['interpretacion'],
            'intent' => $intent,
            'pregunta' => $plan['pregunta'],
            'fuente' => $plan['fuente'],
            'parrafos' => $resultado['parrafos'] ?? [],
            'tabla' => $resultado['tabla'] ?? null,
            'datos' => $resultado['datos'] ?? [],
        ]);

        $nombre = 'consulta_ia_'.$intent.'_'.date('Ymd_His');
        if ($formato === 'CSV') {
            return $export->download($nombre.'.csv', \Maatwebsite\Excel\Excel::CSV);
        }

        return $export->download($nombre.'.xlsx');
    }

    /**
     * @return array<string,mixed>
     */
    private function resolverPlan(Request $request, bool $paraExport): array
    {
        $pregunta = trim((string) $request->input('pregunta', $request->input('texto', '')));
        $intent = strtolower(trim((string) $request->input('intent', '')));
        $valor = trim((string) $request->input('valor', ''));
        $params = $request->input('params');
        if (is_string($params)) {
            $decoded = json_decode($params, true);
            $params = is_array($decoded) ? $decoded : [];
        }
        if (! is_array($params)) {
            $params = [];
        }

        $interpretacion = null;
        $fuente = 'tipado';

        if ($intent === '' || ($pregunta !== '' && $valor === '' && empty($params['sku']) && empty($params['codigo']) && empty($params['numero']) && empty($params['cuenta_codigo']))) {
            $fuenteTexto = $pregunta !== '' ? $pregunta : $valor;
            $ruta = AiConsultaOperativaRouterSupport::interpretar($fuenteTexto);
            $fuente = (string) ($ruta['fuente'] ?? 'reglas');

            if (! empty($ruta['needs_clarification'])) {
                return [
                    'ok' => false,
                    'needs_clarification' => true,
                    'clarification' => $ruta['clarification'] ?? $ruta['error'] ?? 'Necesito un dato más.',
                    'error' => $ruta['clarification'] ?? $ruta['error'] ?? 'Necesito un dato más.',
                    'fuente' => $fuente,
                    'sugerencias' => $ruta['sugerencias'] ?? AiConsultaOperativaRouterSupport::ejemplos(),
                ];
            }

            if (! ($ruta['ok'] ?? false)) {
                return [
                    'ok' => false,
                    'error' => $ruta['error'] ?? 'No se pudo interpretar la consulta.',
                    'fuente' => $fuente,
                    'sugerencias' => $ruta['sugerencias'] ?? AiConsultaOperativaRouterSupport::ejemplos(),
                ];
            }

            $intent = (string) $ruta['intent'];
            $params = array_merge($params, $ruta['params'] ?? []);
            $interpretacion = $ruta['interpretacion'] ?? null;
            $valor = (string) ($params['valor'] ?? $valor);
        } else {
            if ($valor !== '' && empty($params['valor'])) {
                $params['valor'] = $valor;
            }
            if ($valor !== '') {
                match ($intent) {
                    AiConsultaOperativaSupport::INTENT_ARTICULO_SALDO,
                    AiConsultaOperativaSupport::INTENT_ARTICULO_KARDEX => $params['sku'] = $params['sku'] ?? $valor,
                    AiConsultaOperativaSupport::INTENT_CLIENTE,
                    AiConsultaOperativaSupport::INTENT_CLIENTE_CTACTE,
                    AiConsultaOperativaSupport::INTENT_PROVEEDOR,
                    AiConsultaOperativaSupport::INTENT_PROVEEDOR_CTACTE => $params['codigo'] = $params['codigo'] ?? $valor,
                    AiConsultaOperativaSupport::INTENT_ORDENCOMPRA,
                    AiConsultaOperativaSupport::INTENT_ARBOL_OC,
                    AiConsultaOperativaSupport::INTENT_ASIENTO,
                    AiConsultaOperativaSupport::INTENT_COMPROBANTE_PROVEEDOR,
                    AiConsultaOperativaSupport::INTENT_FACTURA_VENTA => $params['numero'] = $params['numero'] ?? $valor,
                    AiConsultaOperativaSupport::INTENT_SALDO_CUENTA,
                    AiConsultaOperativaSupport::INTENT_MAYOR_CUENTA => $params['cuenta_codigo'] = $params['cuenta_codigo'] ?? $valor,
                    default => null,
                };
            }
            $mapa = AiConsultaOperativaSupport::intentsEtiquetas();
            $interpretacion = ($mapa[$intent] ?? $intent).(
                $valor !== '' ? ': '.$valor : ''
            );
        }

        if ($paraExport) {
            $params['modo_export'] = true;
            if (empty($params['max_lineas'])) {
                $params['max_lineas'] = 200;
            }
        }

        $params = AiConsultaOperativaSchemaSupport::normalizarParams($intent, $params);

        $empresaId = $request->input('empresa_id');
        $empresaId = is_numeric($empresaId) && (int) $empresaId > 0 ? (int) $empresaId : null;
        if ($empresaId) {
            $params['empresa_id'] = $empresaId;
        }

        if ($interpretacion === null || $interpretacion === '') {
            $mapa = AiConsultaOperativaSupport::intentsEtiquetas();
            $interpretacion = $mapa[$intent] ?? $intent;
        }

        return [
            'ok' => true,
            'intent' => $intent,
            'params' => $params,
            'interpretacion' => $interpretacion,
            'fuente' => $fuente,
            'pregunta' => $pregunta !== '' ? $pregunta : null,
            'empresa_id' => $empresaId,
        ];
    }

    private function placeholderIntent(string $intent): string
    {
        return match ($intent) {
            AiConsultaOperativaSupport::INTENT_ARTICULO_SALDO => 'SKU o nombre del artículo',
            AiConsultaOperativaSupport::INTENT_ARTICULO_KARDEX => 'SKU o nombre (ej. muzarella)',
            AiConsultaOperativaSupport::INTENT_CLIENTE,
            AiConsultaOperativaSupport::INTENT_CLIENTE_CTACTE => 'Código o documento de cliente',
            AiConsultaOperativaSupport::INTENT_PROVEEDOR,
            AiConsultaOperativaSupport::INTENT_PROVEEDOR_CTACTE => 'Código de proveedor',
            AiConsultaOperativaSupport::INTENT_ORDENCOMPRA,
            AiConsultaOperativaSupport::INTENT_ARBOL_OC => 'Número de OC',
            AiConsultaOperativaSupport::INTENT_ASIENTO => 'Número de asiento',
            AiConsultaOperativaSupport::INTENT_COMPROBANTE_PROVEEDOR => 'Nº o A-sucursal-número',
            AiConsultaOperativaSupport::INTENT_FACTURA_VENTA => 'Nº o punto de venta-número',
            AiConsultaOperativaSupport::INTENT_SALDO_CUENTA,
            AiConsultaOperativaSupport::INTENT_MAYOR_CUENTA => 'Código de cuenta (opc. CC / OC / empresa)',
            AiConsultaOperativaSupport::INTENT_PLAN_AGENTE => 'desvío / deuda proveedor 475 / firmar OC 1234',
            AiConsultaOperativaSupport::INTENT_PEDIDO_CONSUMO_SECTOR => 'CC 93 depósito 12 (últimos 60 días)',
            AiConsultaOperativaSupport::INTENT_COMPRAS_KPI_RESUMEN => 'Opcional: empresa 1 / este mes',
            AiConsultaOperativaSupport::INTENT_OC_PENDIENTES_FIRMA,
            AiConsultaOperativaSupport::INTENT_OC_VENCIDAS_SIN_RECEPCION,
            AiConsultaOperativaSupport::INTENT_RQ_SIN_OC => 'Opcional: empresa 1',
            AiConsultaOperativaSupport::INTENT_LEAD_TIME_OC_RECEPCION => 'Opcional: últimos 90 días / empresa 1',
            AiConsultaOperativaSupport::INTENT_TOP_PROVEEDORES_MONTO => 'Opcional: este mes / julio / empresa 1',
            default => 'Valor a consultar',
        };
    }

    private function grupoIntent(string $intent): string
    {
        return match ($intent) {
            AiConsultaOperativaSupport::INTENT_PROVEEDOR,
            AiConsultaOperativaSupport::INTENT_PROVEEDOR_CTACTE,
            AiConsultaOperativaSupport::INTENT_ORDENCOMPRA,
            AiConsultaOperativaSupport::INTENT_ARBOL_OC,
            AiConsultaOperativaSupport::INTENT_COMPROBANTE_PROVEEDOR,
            AiConsultaOperativaSupport::INTENT_COMPRAS_KPI_RESUMEN,
            AiConsultaOperativaSupport::INTENT_OC_PENDIENTES_FIRMA,
            AiConsultaOperativaSupport::INTENT_OC_VENCIDAS_SIN_RECEPCION,
            AiConsultaOperativaSupport::INTENT_LEAD_TIME_OC_RECEPCION,
            AiConsultaOperativaSupport::INTENT_TOP_PROVEEDORES_MONTO,
            AiConsultaOperativaSupport::INTENT_RQ_SIN_OC,
            AiConsultaOperativaSupport::INTENT_PEDIDO_CONSUMO_SECTOR => 'compras',
            AiConsultaOperativaSupport::INTENT_MAYOR_CUENTA,
            AiConsultaOperativaSupport::INTENT_SALDO_CUENTA,
            AiConsultaOperativaSupport::INTENT_ASIENTO => 'contable',
            AiConsultaOperativaSupport::INTENT_ARTICULO_SALDO,
            AiConsultaOperativaSupport::INTENT_ARTICULO_KARDEX => 'stock',
            AiConsultaOperativaSupport::INTENT_CLIENTE,
            AiConsultaOperativaSupport::INTENT_CLIENTE_CTACTE,
            AiConsultaOperativaSupport::INTENT_FACTURA_VENTA => 'ventas',
            AiConsultaOperativaSupport::INTENT_PLAN_AGENTE,
            AiConsultaOperativaSupport::INTENT_CONSULTAR_MANUAL => 'ayuda',
            default => 'otros',
        };
    }

    /** @return array<string, string> */
    private function gruposEtiquetas(): array
    {
        return [
            'compras' => 'Compras',
            'contable' => 'Contable',
            'stock' => 'Stock',
            'ventas' => 'Ventas',
            'ayuda' => 'Ayuda',
            'otros' => 'Otros',
        ];
    }

    private function autoPreguntaIntent(string $intent): ?string
    {
        return match ($intent) {
            AiConsultaOperativaSupport::INTENT_COMPRAS_KPI_RESUMEN => 'resumen operativo de compras',
            AiConsultaOperativaSupport::INTENT_OC_PENDIENTES_FIRMA => 'OC pendientes de firma',
            AiConsultaOperativaSupport::INTENT_OC_VENCIDAS_SIN_RECEPCION => 'OC vencidas sin recepción',
            AiConsultaOperativaSupport::INTENT_LEAD_TIME_OC_RECEPCION => 'lead time OC recepción últimos 90 días',
            AiConsultaOperativaSupport::INTENT_TOP_PROVEEDORES_MONTO => 'top proveedores por monto este mes',
            AiConsultaOperativaSupport::INTENT_RQ_SIN_OC => 'requisiciones sin OC',
            default => null,
        };
    }
}
