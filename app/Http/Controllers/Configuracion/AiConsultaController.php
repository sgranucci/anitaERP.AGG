<?php

namespace App\Http\Controllers\Configuracion;

use App\Exports\Configuracion\AiConsultaOperativaExport;
use App\Http\Controllers\Controller;
use App\Services\Ai\ConsultarContextoOperativoSkill;
use App\Services\Ai\Skills\AiSkillContext;
use App\Services\Ai\Skills\AiSkillRegistry;
use App\Models\Ai\AiAgenteEvento;
use App\Support\Ai\AiConsultaAlertaUmbralSupport;
use App\Support\Ai\AiConsultaHitlAccionSupport;
use App\Support\Ai\AiConsultaNotificacionSupport;
use App\Support\Ai\AiConsultaOperativaAsistenteRolSupport;
use App\Support\Ai\AiConsultaOperativaBriefSupport;
use App\Support\Ai\AiConsultaOperativaContextoSupport;
use App\Support\Ai\AiConsultaOperativaLlmRedactarSupport;
use App\Support\Ai\AiConsultaOperativaPreferenciasSupport;
use App\Support\Ai\AiConsultaOperativaRouterSupport;
use App\Support\Ai\AiConsultaOperativaSchemaSupport;
use App\Support\Ai\AiConsultaOperativaSupport;
use App\Support\Ai\AiLlmCadenaExternaSupport;
use App\Support\Ai\PedidoConsumoSectorConfirmacionSupport;
use App\Support\Ai\PedidoConsumoSectorProyeccionSupport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
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

        $prefs = AiConsultaOperativaPreferenciasSupport::leer();
        $rolPrefs = AiConsultaOperativaAsistenteRolSupport::normalizar(
            (string) ($prefs['asistente_rol'] ?? AiConsultaOperativaAsistenteRolSupport::AUTO)
        );
        $rol = AiConsultaOperativaAsistenteRolSupport::efectivo($rolPrefs);
        $gruposRol = AiConsultaOperativaAsistenteRolSupport::gruposPermitidos($rol);

        $etiquetas = AiConsultaOperativaSupport::intentsEtiquetasPermitidos();
        $items = [];
        foreach ($etiquetas as $clave => $etiqueta) {
            $grupo = $this->grupoIntent($clave);
            if ($gruposRol !== null && ! in_array($grupo, $gruposRol, true)) {
                continue;
            }
            $items[] = [
                'intent' => $clave,
                'etiqueta' => $etiqueta,
                'placeholder' => $this->placeholderIntent($clave),
                'grupo' => $grupo,
                'auto_pregunta' => $this->autoPreguntaIntent($clave),
            ];
        }

        $ctx = AiConsultaOperativaContextoSupport::leer();
        $hitlPendientes = 0;
        if (can('listar-ai-decisiones', false) && Schema::hasTable('ai_agente_evento')) {
            $hitlPendientes = (int) AiAgenteEvento::query()
                ->whereIn('estado', [AiAgenteEvento::ESTADO_PENDIENTE, AiAgenteEvento::ESTADO_VISTO])
                ->count();
        }
        $briefSugerido = AiConsultaOperativaPreferenciasSupport::debeSugerirBrief();
        if ($briefSugerido) {
            // Una sugerencia por día/sesión (el botón queda visible en este load)
            AiConsultaOperativaPreferenciasSupport::marcarBriefSugerido();
            AiConsultaNotificacionSupport::avisarBriefSugerido();
        }

        $alertaUmbral = null;
        try {
            $alertaUmbral = AiConsultaAlertaUmbralSupport::evaluarYAvisarUsuarioActual();
        } catch (\Throwable) {
            $alertaUmbral = null;
        }

        return response()->json([
            'ok' => true,
            'intents' => $items,
            'grupos' => $this->gruposEtiquetas(),
            'ejemplos' => AiConsultaOperativaAsistenteRolSupport::ejemplos($rol),
            'asistentes_rol' => AiConsultaOperativaAsistenteRolSupport::etiquetas(),
            'asistente_rol_efectivo' => $rol,
            'asistente_rol_detectado' => AiConsultaOperativaAsistenteRolSupport::detectarDesdePermisos(),
            'llm_router' => filter_var(config('ai.skills.consultar_contexto_operativo.llm_router', false), FILTER_VALIDATE_BOOLEAN),
            'llm_redactar' => AiConsultaOperativaLlmRedactarSupport::habilitado(),
            'llm_failover_externo' => AiLlmCadenaExternaSupport::habilitadoParaCopiloto(),
            'llm_failover_cadena' => AiLlmCadenaExternaSupport::cadenaActivaNombres(),
            'multiturn' => AiConsultaOperativaContextoSupport::habilitado(),
            'contexto_activo' => $ctx !== null,
            'contexto_resumen' => $ctx['interpretacion'] ?? null,
            'preferencias' => $prefs,
            'aviso' => [
                'hitl_pendientes' => $hitlPendientes,
                // Con auto_brief: el FAB arma el brief solo (una vez/día); sin botón manual.
                'brief_sugerido' => false,
                'brief_auto_ejecutar' => $briefSugerido,
                'alerta_umbral' => ($alertaUmbral && ($alertaUmbral['disparar'] ?? false))
                    ? ['lineas' => $alertaUmbral['lineas'] ?? []]
                    : null,
            ],
            'skill' => ConsultarContextoOperativoSkill::NOMBRE,
        ]);
    }

    /**
     * PDF 1 hoja del brief en sesión.
     */
    public function exportarBriefPdf()
    {
        can('ejecutar-consulta-ia');

        $data = session(AiConsultaOperativaBriefSupport::SESSION_KEY);
        $items = is_array($data) ? ($data['items'] ?? []) : [];
        if (! is_array($items) || $items === []) {
            return response()->json(['ok' => false, 'error' => 'El brief está vacío. Armalo primero.'], 422);
        }

        $prefs = AiConsultaOperativaPreferenciasSupport::leer();
        $html = view('configuracion.ai_consulta.brief_dia_pdf', [
            'items' => $items,
            'generadoAt' => $data['generado_at'] ?? now()->format('d/m/Y H:i'),
            'asistenteRol' => AiConsultaOperativaAsistenteRolSupport::etiquetas()[
                AiConsultaOperativaAsistenteRolSupport::efectivo($prefs['asistente_rol'] ?? null)
            ] ?? null,
        ])->render();

        $ruta = storage_path('pdf/listados/brief_dia_'.(auth()->id() ?: '0').'.pdf');
        \App\Support\Reportes\DompdfListadoSupport::guardar($html, $ruta, [
            'paper' => 'a4',
            'orientation' => 'portrait',
            'titulo_corto' => 'Brief del día',
        ]);

        return response()->download($ruta, 'brief_dia_'.date('Ymd_Hi').'.pdf');
    }

    public function consultar(Request $request): JsonResponse
    {
        can('ejecutar-consulta-ia');

        if ($request->boolean('reiniciar_contexto')) {
            AiConsultaOperativaContextoSupport::limpiar();
            $preguntaSoloReinicio = trim((string) $request->input('pregunta', $request->input('texto', '')));
            if ($preguntaSoloReinicio === '' && trim((string) $request->input('intent', '')) === '') {
                return response()->json([
                    'ok' => true,
                    'reiniciado' => true,
                    'contexto_activo' => false,
                    'mensaje' => 'Contexto del chat reiniciado.',
                ]);
            }
        }

        $plan = $this->resolverPlan($request, false);
        if (! ($plan['ok'] ?? false)) {
            return response()->json($plan, 422);
        }

        $intent = (string) $plan['intent'];
        $params = AiConsultaOperativaPreferenciasSupport::aplicarAParams(
            is_array($plan['params'] ?? null) ? $plan['params'] : []
        );
        $interpretacion = $plan['interpretacion'];
        $fuente = $plan['fuente'];
        $pregunta = $plan['pregunta'];
        $empresaId = $plan['empresa_id'] ?: ($params['empresa_id'] ?? null);
        $empresaId = is_numeric($empresaId) ? (int) $empresaId : null;
        $seguimiento = ! empty($plan['seguimiento']);

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

        $parrafos = $result->datos['parrafos'] ?? $result->advertencias;
        if (! is_array($parrafos)) {
            $parrafos = [];
        }
        $tabla = $result->datos['tabla'] ?? null;

        $resumenPack = AiConsultaOperativaLlmRedactarSupport::redactarDetalle(
            (string) ($pregunta ?: $interpretacion),
            (string) ($result->datos['intent'] ?? $intent),
            (string) $interpretacion,
            $parrafos,
            is_array($tabla) ? $tabla : null,
        );
        $resumenNl = is_array($resumenPack) ? ($resumenPack['texto'] ?? null) : null;
        $resumenFuente = is_array($resumenPack) ? ($resumenPack['fuente'] ?? null) : null;

        AiConsultaOperativaContextoSupport::guardar(
            (string) ($result->datos['intent'] ?? $intent),
            $params,
            is_string($pregunta) ? $pregunta : null,
            (string) $interpretacion,
            is_string($resumenNl) ? $resumenNl : null,
        );

        return response()->json([
            'ok' => true,
            'intent' => $result->datos['intent'] ?? $intent,
            'interpretacion' => $interpretacion,
            'fuente' => $fuente,
            'seguimiento' => $seguimiento,
            'resumen_nl' => $resumenNl,
            'resumen_fuente' => $resumenFuente,
            'parrafos' => $parrafos,
            'links' => $result->datos['links'] ?? [],
            'tabla' => $tabla,
            'datos' => $result->datos['datos'] ?? [],
            'params' => $params,
            'pregunta' => $pregunta,
            'exportable' => $intent !== AiConsultaOperativaSupport::INTENT_CONSULTA_INTELIGENTE,
            'score' => $result->score,
            'ai_decision_id' => $result->decisionId,
            'contexto_activo' => true,
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
     * HITL chat: visto / descartar / resolver evento de agente.
     */
    public function hitlEvento(Request $request): JsonResponse
    {
        can('ejecutar-consulta-ia');
        can('listar-ai-decisiones');

        $accion = strtolower(trim((string) $request->input('accion', '')));
        $eventoId = (int) $request->input('evento_id', 0);
        $resultado = AiConsultaHitlAccionSupport::transicionarEvento(
            $accion,
            $eventoId,
            Auth::id() ? (int) Auth::id() : null
        );

        return response()->json($resultado, ($resultado['ok'] ?? false) ? 200 : 422);
    }

    /**
     * HITL chat: depositar cheques urgentes del aging (requiere cuenta de caja).
     */
    public function hitlDepositarCheques(Request $request): JsonResponse
    {
        can('ejecutar-consulta-ia');
        can('editar-cheque');

        $ids = $request->input('cheque_ids', []);
        if (is_string($ids)) {
            $decoded = json_decode($ids, true);
            $ids = is_array($decoded) ? $decoded : (preg_split('/[,\s]+/', $ids) ?: []);
        }
        if (! is_array($ids)) {
            $ids = [];
        }
        $decisionId = $request->input('ai_decision_id');
        $decisionId = is_numeric($decisionId) ? (int) $decisionId : null;

        $resultado = AiConsultaHitlAccionSupport::depositarCheques(
            $ids,
            $request->input('cuentacaja'),
            $request->input('fecha') ? (string) $request->input('fecha') : null,
            $request->input('nro_boleta') ? (string) $request->input('nro_boleta') : null,
            $decisionId
        );

        return response()->json($resultado, ($resultado['ok'] ?? false) ? 200 : 422);
    }

    /**
     * HITL chat: confirmar recepciones COM en borrador.
     */
    public function hitlConfirmarRecepciones(Request $request): JsonResponse
    {
        can('ejecutar-consulta-ia');
        can('confirmar-recepcion-proveedor');

        $ids = $request->input('recepcion_ids', []);
        if (is_string($ids)) {
            $decoded = json_decode($ids, true);
            $ids = is_array($decoded) ? $decoded : (preg_split('/[,\s]+/', $ids) ?: []);
        }
        if (! is_array($ids)) {
            $ids = [];
        }
        $decisionId = $request->input('ai_decision_id');
        $decisionId = is_numeric($decisionId) ? (int) $decisionId : null;

        $resultado = AiConsultaHitlAccionSupport::confirmarRecepciones($ids, $decisionId);

        return response()->json($resultado, ($resultado['ok'] ?? false) ? 200 : 422);
    }

    /**
     * HITL chat: eliminar recepciones COM en borrador.
     */
    public function hitlEliminarRecepcionesBorrador(Request $request): JsonResponse
    {
        can('ejecutar-consulta-ia');
        can('borrar-recepcion-proveedor');

        $ids = $request->input('recepcion_ids', []);
        if (is_string($ids)) {
            $decoded = json_decode($ids, true);
            $ids = is_array($decoded) ? $decoded : (preg_split('/[,\s]+/', $ids) ?: []);
        }
        if (! is_array($ids)) {
            $ids = [];
        }
        $decisionId = $request->input('ai_decision_id');
        $decisionId = is_numeric($decisionId) ? (int) $decisionId : null;

        $resultado = AiConsultaHitlAccionSupport::eliminarRecepcionesBorrador($ids, $decisionId);

        return response()->json($resultado, ($resultado['ok'] ?? false) ? 200 : 422);
    }

    /**
     * HITL chat: confirmar recepción de TM pendientes.
     */
    public function hitlConfirmarTransferencias(Request $request): JsonResponse
    {
        can('ejecutar-consulta-ia');
        can('aprobar-transferencia-mercaderia');

        $ids = $request->input('transferencia_ids', []);
        if (is_string($ids)) {
            $decoded = json_decode($ids, true);
            $ids = is_array($decoded) ? $decoded : (preg_split('/[,\s]+/', $ids) ?: []);
        }
        if (! is_array($ids)) {
            $ids = [];
        }
        $decisionId = $request->input('ai_decision_id');
        $decisionId = is_numeric($decisionId) ? (int) $decisionId : null;

        $resultado = AiConsultaHitlAccionSupport::confirmarTransferenciasPendientes($ids, $decisionId);

        return response()->json($resultado, ($resultado['ok'] ?? false) ? 200 : 422);
    }

    /**
     * HITL chat: caucionar cheques urgentes del aging (requiere nro de caución).
     */
    public function hitlCaucionarCheques(Request $request): JsonResponse
    {
        can('ejecutar-consulta-ia');
        can('editar-cheque');

        $ids = $request->input('cheque_ids', []);
        if (is_string($ids)) {
            $decoded = json_decode($ids, true);
            $ids = is_array($decoded) ? $decoded : (preg_split('/[,\s]+/', $ids) ?: []);
        }
        if (! is_array($ids)) {
            $ids = [];
        }
        $decisionId = $request->input('ai_decision_id');
        $decisionId = is_numeric($decisionId) ? (int) $decisionId : null;

        $resultado = AiConsultaHitlAccionSupport::caucionarCheques(
            $ids,
            (string) $request->input('nro_caucion', ''),
            $request->input('fecha') ? (string) $request->input('fecha') : null,
            $decisionId
        );

        return response()->json($resultado, ($resultado['ok'] ?? false) ? 200 : 422);
    }

    /**
     * HITL chat: crear borrador de propuesta de pago (deuda vencida, tipo LEGACY).
     */
    public function hitlCrearBorradorPropuesta(Request $request): JsonResponse
    {
        can('ejecutar-consulta-ia');
        can('crear-propuesta-pago');

        $empresaId = (int) $request->input('empresa_id', 0);
        if ($empresaId <= 0) {
            $prefs = AiConsultaOperativaPreferenciasSupport::leer();
            $empresaId = (int) ($prefs['empresa_id'] ?? 0);
        }
        $decisionId = $request->input('ai_decision_id');
        $decisionId = is_numeric($decisionId) ? (int) $decisionId : null;

        $resultado = AiConsultaHitlAccionSupport::crearBorradorPropuestaPago(
            $empresaId,
            $request->input('fecha_hasta') ? (string) $request->input('fecha_hasta') : null,
            $decisionId
        );

        return response()->json($resultado, ($resultado['ok'] ?? false) ? 200 : 422);
    }

    /**
     * HITL chat: enviar propuesta BORRADOR/RECHAZADA a aprobación (árbol; light → AUTORIZADA).
     * No ejecuta OP ni firma niveles.
     */
    public function hitlEnviarAprobacionPropuesta(Request $request): JsonResponse
    {
        can('ejecutar-consulta-ia');
        can('enviar-aprobacion-propuesta-pago');

        $id = (int) $request->input('propuesta_pago_id', 0);
        $decisionId = $request->input('ai_decision_id');
        $decisionId = is_numeric($decisionId) ? (int) $decisionId : null;

        $resultado = AiConsultaHitlAccionSupport::enviarAprobacionPropuestaPago($id, $decisionId);

        return response()->json($resultado, ($resultado['ok'] ?? false) ? 200 : 422);
    }

    /**
     * Preferencias del copiloto (sesión).
     */
    public function guardarPreferencias(Request $request): JsonResponse
    {
        can('ejecutar-consulta-ia');

        $prefs = AiConsultaOperativaPreferenciasSupport::guardar([
            'empresa_id' => $request->input('empresa_id'),
            'centrocosto_codigo' => $request->input('centrocosto_codigo'),
            'deposito_codigo' => $request->input('deposito_codigo'),
            'auto_brief' => $request->input('auto_brief'),
            'notify_brief_campanita' => $request->input('notify_brief_campanita'),
            'notify_brief_mail' => $request->input('notify_brief_mail'),
            'asistente_rol' => $request->input('asistente_rol'),
            'notify_alerta_umbral' => $request->input('notify_alerta_umbral'),
            'umbral_cheques_vencidos' => $request->input('umbral_cheques_vencidos'),
            'umbral_deuda_ap' => $request->input('umbral_deuda_ap'),
            'umbral_tickets_abiertos' => $request->input('umbral_tickets_abiertos'),
            'umbral_stock_critico' => $request->input('umbral_stock_critico'),
        ]);

        $rol = AiConsultaOperativaAsistenteRolSupport::normalizar(
            (string) ($prefs['asistente_rol'] ?? AiConsultaOperativaAsistenteRolSupport::AUTO)
        );

        return response()->json([
            'ok' => true,
            'preferencias' => $prefs,
            'ejemplos' => AiConsultaOperativaAsistenteRolSupport::ejemplos($rol),
        ]);
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

        $seguimiento = false;
        if ($intent === '' || ($pregunta !== '' && $valor === '' && empty($params['sku']) && empty($params['codigo']) && empty($params['numero']) && empty($params['cuenta_codigo']))) {
            $fuenteTexto = $pregunta !== '' ? $pregunta : $valor;
            $contexto = AiConsultaOperativaContextoSupport::leer();
            $ruta = AiConsultaOperativaRouterSupport::interpretar($fuenteTexto, $contexto);
            $fuente = (string) ($ruta['fuente'] ?? 'reglas');
            $seguimiento = ! empty($ruta['seguimiento']) || $fuente === 'seguimiento';

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
                    AiConsultaOperativaSupport::INTENT_ARTICULO_KARDEX,
                    AiConsultaOperativaSupport::INTENT_FORMULA_COMPRA,
                    AiConsultaOperativaSupport::INTENT_FORMULA_USO => $params['sku'] = $params['sku'] ?? $valor,
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
                    AiConsultaOperativaSupport::INTENT_CONSULTA_INTELIGENTE => $params['pregunta'] = $params['pregunta'] ?? $valor,
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
            'seguimiento' => $seguimiento,
        ];
    }

    private function placeholderIntent(string $intent): string
    {
        return match ($intent) {
            AiConsultaOperativaSupport::INTENT_ARTICULO_SALDO => 'SKU o nombre del artículo',
            AiConsultaOperativaSupport::INTENT_ARTICULO_KARDEX => 'SKU o nombre (ej. muzarella)',
            AiConsultaOperativaSupport::INTENT_FORMULA_COMPRA => 'SKU del insumo (artículos de compra)',
            AiConsultaOperativaSupport::INTENT_FORMULA_USO => 'SKU o nombre del insumo',
            AiConsultaOperativaSupport::INTENT_CONCILIACION_BANCARIA => 'Cuenta de caja (ej. cuenta de caja 300 mes pasado)',
            AiConsultaOperativaSupport::INTENT_CONCILIACION_TURNO => 'Opcional: empresa o PC',
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
            AiConsultaOperativaSupport::INTENT_MAYOR_CUENTA => 'Código o nombre de cuenta (opc. empresa / CC / OC)',
            AiConsultaOperativaSupport::INTENT_PLAN_AGENTE => 'desvío / deuda proveedor 475 / firmar OC 1234',
            AiConsultaOperativaSupport::INTENT_PEDIDO_CONSUMO_SECTOR => 'CC 93 depósito 12 (últimos 60 días)',
            AiConsultaOperativaSupport::INTENT_COMPRAS_KPI_RESUMEN => 'Opcional: empresa biyemas / este mes',
            AiConsultaOperativaSupport::INTENT_COMPRAS_KPI_PROCESO => 'Opcional: últimos 90 días / empresa biyemas',
            AiConsultaOperativaSupport::INTENT_COMPRAS_KPI_PRODUCTIVIDAD => 'Opcional: este mes / últimos 90 días / empresa',
            AiConsultaOperativaSupport::INTENT_OC_PENDIENTES_FIRMA,
            AiConsultaOperativaSupport::INTENT_OC_VENCIDAS_SIN_RECEPCION,
            AiConsultaOperativaSupport::INTENT_RQ_SIN_OC => 'Opcional: empresa biyemas o id',
            AiConsultaOperativaSupport::INTENT_LEAD_TIME_OC_RECEPCION => 'Opcional: últimos 90 días / empresa biyemas',
            AiConsultaOperativaSupport::INTENT_TOP_PROVEEDORES_MONTO => 'Opcional: este mes / julio / empresa biyemas',
            AiConsultaOperativaSupport::INTENT_CONSULTA_INTELIGENTE => 'Ej.: cheques para depositar / pagos este mes',
            AiConsultaOperativaSupport::INTENT_CHEQUE_AGING => 'Opcional: empresa / bucket vencido',
            AiConsultaOperativaSupport::INTENT_CHEQUE_CASHFLOW => 'Opcional: 8 semanas / empresa',
            AiConsultaOperativaSupport::INTENT_STOCK_CRITICO => 'Opcional: umbral 5 / depósito',
            AiConsultaOperativaSupport::INTENT_BRIEF_DIA => 'armar / mostrar / limpiar',
            AiConsultaOperativaSupport::INTENT_COLA_HITL => 'Opcional: pendientes / resueltos',
            AiConsultaOperativaSupport::INTENT_PROPUESTA_PAGO_RESUMEN => 'Opcional: empresa',
            AiConsultaOperativaSupport::INTENT_CASH_POSITION => 'Opcional: empresa',
            AiConsultaOperativaSupport::INTENT_AP_AGING => 'Opcional: empresa',
            AiConsultaOperativaSupport::INTENT_AR_AGING => 'Opcional: empresa',
            AiConsultaOperativaSupport::INTENT_EMPLEADO_LEGAJO => 'Legajo (ej. 1234)',
            AiConsultaOperativaSupport::INTENT_LIQUIDACION_RESUMEN => 'Número de liquidación',
            AiConsultaOperativaSupport::INTENT_LIQUIDACIONES_PERIODO => 'Período AAAAMM / este mes / noviembre 2026',
            AiConsultaOperativaSupport::INTENT_LIQUIDACIONES_PENDIENTES_CIERRE => 'Período AAAAMM / este mes (pendientes de cierre)',
            AiConsultaOperativaSupport::INTENT_EMPLEADOS_INGRESADOS => 'Período AAAAMM / este mes',
            AiConsultaOperativaSupport::INTENT_PRECARGA_PENDIENTES => 'Opcional: empresa',
            AiConsultaOperativaSupport::INTENT_RECEPCION_BORRADORES => 'Opcional: empresa',
            AiConsultaOperativaSupport::INTENT_TM_PENDIENTES => 'Opcional',
            AiConsultaOperativaSupport::INTENT_INGRESO_EGRESO_PERIODO => 'Opcional: este mes / fechas',
            AiConsultaOperativaSupport::INTENT_TICKETS_KPI => 'Opcional',
            AiConsultaOperativaSupport::INTENT_TICKETS_ABIERTOS => 'Opcional: top por antigüedad',
            AiConsultaOperativaSupport::INTENT_ENTIDAD_DOLOR => 'Ej.: cheques para depositar / facturas sin remito',
            AiConsultaOperativaSupport::INTENT_MIS_APROBACIONES => 'Opcional: fuente / tipo',
            AiConsultaOperativaSupport::INTENT_PATRON_ANALITICO => 'Ej.: proveedores con CUIT duplicado / de Lanús',
            AiConsultaOperativaSupport::INTENT_HISTORIAL_PRECIOS_COMPRA => 'SKU / insumo (ej. muzarella) · opc. últimas N',
            AiConsultaOperativaSupport::INTENT_PROYECCION_PAGOS => 'Opcional: vencidos / empresa',
            AiConsultaOperativaSupport::INTENT_CONTRATOS_POR_VENCER => 'Opcional: 60 días / vencidos / preaviso',
            AiConsultaOperativaSupport::INTENT_PRECIO_POR_PROVEEDOR => 'SKU / insumo',
            AiConsultaOperativaSupport::INTENT_EXISTENCIAS_DEPOSITO => 'SKU / insumo',
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
            AiConsultaOperativaSupport::INTENT_COMPRAS_KPI_PROCESO,
            AiConsultaOperativaSupport::INTENT_COMPRAS_KPI_PRODUCTIVIDAD,
            AiConsultaOperativaSupport::INTENT_OC_PENDIENTES_FIRMA,
            AiConsultaOperativaSupport::INTENT_OC_VENCIDAS_SIN_RECEPCION,
            AiConsultaOperativaSupport::INTENT_LEAD_TIME_OC_RECEPCION,
            AiConsultaOperativaSupport::INTENT_TOP_PROVEEDORES_MONTO,
            AiConsultaOperativaSupport::INTENT_RQ_SIN_OC,
            AiConsultaOperativaSupport::INTENT_PEDIDO_CONSUMO_SECTOR,
            AiConsultaOperativaSupport::INTENT_PROPUESTA_PAGO_RESUMEN,
            AiConsultaOperativaSupport::INTENT_CASH_POSITION,
            AiConsultaOperativaSupport::INTENT_AP_AGING,
            AiConsultaOperativaSupport::INTENT_PROYECCION_PAGOS,
            AiConsultaOperativaSupport::INTENT_CONTRATOS_POR_VENCER,
            AiConsultaOperativaSupport::INTENT_PRECIO_POR_PROVEEDOR,
            AiConsultaOperativaSupport::INTENT_PRECARGA_PENDIENTES => 'compras',
            AiConsultaOperativaSupport::INTENT_MAYOR_CUENTA,
            AiConsultaOperativaSupport::INTENT_SALDO_CUENTA,
            AiConsultaOperativaSupport::INTENT_ASIENTO,
            AiConsultaOperativaSupport::INTENT_CONCILIACION_BANCARIA => 'contable',
            AiConsultaOperativaSupport::INTENT_ARTICULO_SALDO,
            AiConsultaOperativaSupport::INTENT_ARTICULO_KARDEX,
            AiConsultaOperativaSupport::INTENT_FORMULA_COMPRA,
            AiConsultaOperativaSupport::INTENT_FORMULA_USO,
            AiConsultaOperativaSupport::INTENT_HISTORIAL_PRECIOS_COMPRA,
            AiConsultaOperativaSupport::INTENT_EXISTENCIAS_DEPOSITO,
            AiConsultaOperativaSupport::INTENT_STOCK_CRITICO,
            AiConsultaOperativaSupport::INTENT_RECEPCION_BORRADORES,
            AiConsultaOperativaSupport::INTENT_TM_PENDIENTES => 'stock',
            AiConsultaOperativaSupport::INTENT_CLIENTE,
            AiConsultaOperativaSupport::INTENT_CLIENTE_CTACTE,
            AiConsultaOperativaSupport::INTENT_FACTURA_VENTA,
            AiConsultaOperativaSupport::INTENT_CONCILIACION_TURNO,
            AiConsultaOperativaSupport::INTENT_AR_AGING => 'ventas',
            AiConsultaOperativaSupport::INTENT_CHEQUE_AGING,
            AiConsultaOperativaSupport::INTENT_CHEQUE_CASHFLOW,
            AiConsultaOperativaSupport::INTENT_INGRESO_EGRESO_PERIODO => 'tesoreria',
            AiConsultaOperativaSupport::INTENT_EMPLEADO_LEGAJO,
            AiConsultaOperativaSupport::INTENT_LIQUIDACION_RESUMEN,
            AiConsultaOperativaSupport::INTENT_LIQUIDACIONES_PERIODO,
            AiConsultaOperativaSupport::INTENT_LIQUIDACIONES_PENDIENTES_CIERRE,
            AiConsultaOperativaSupport::INTENT_EMPLEADOS_INGRESADOS => 'sueldos',
            AiConsultaOperativaSupport::INTENT_TICKETS_KPI,
            AiConsultaOperativaSupport::INTENT_TICKETS_ABIERTOS => 'tickets',
            AiConsultaOperativaSupport::INTENT_PLAN_AGENTE,
            AiConsultaOperativaSupport::INTENT_CONSULTAR_MANUAL,
            AiConsultaOperativaSupport::INTENT_CONSULTA_INTELIGENTE,
            AiConsultaOperativaSupport::INTENT_ENTIDAD_DOLOR,
            AiConsultaOperativaSupport::INTENT_MIS_APROBACIONES,
            AiConsultaOperativaSupport::INTENT_PATRON_ANALITICO,
            AiConsultaOperativaSupport::INTENT_BRIEF_DIA,
            AiConsultaOperativaSupport::INTENT_COLA_HITL => 'ayuda',
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
            'tesoreria' => 'Tesorería',
            'sueldos' => 'Sueldos',
            'tickets' => 'Tickets',
            'ayuda' => 'Ayuda',
            'otros' => 'Otros',
        ];
    }

    private function autoPreguntaIntent(string $intent): ?string
    {
        return match ($intent) {
            AiConsultaOperativaSupport::INTENT_COMPRAS_KPI_RESUMEN => 'resumen operativo de compras',
            AiConsultaOperativaSupport::INTENT_COMPRAS_KPI_PROCESO => 'KPI proceso de compras últimos 90 días',
            AiConsultaOperativaSupport::INTENT_COMPRAS_KPI_PRODUCTIVIDAD => 'KPI productividad de compras últimos 90 días',
            AiConsultaOperativaSupport::INTENT_OC_PENDIENTES_FIRMA => 'OC pendientes de firma',
            AiConsultaOperativaSupport::INTENT_OC_VENCIDAS_SIN_RECEPCION => 'OC vencidas sin recepción',
            AiConsultaOperativaSupport::INTENT_LEAD_TIME_OC_RECEPCION => 'lead time OC recepción últimos 90 días',
            AiConsultaOperativaSupport::INTENT_TOP_PROVEEDORES_MONTO => 'top proveedores por monto este mes',
            AiConsultaOperativaSupport::INTENT_RQ_SIN_OC => 'requisiciones sin OC',
            AiConsultaOperativaSupport::INTENT_CONSULTA_INTELIGENTE => 'cheques para depositar',
            AiConsultaOperativaSupport::INTENT_CHEQUE_AGING => 'aging de cheques',
            AiConsultaOperativaSupport::INTENT_CHEQUE_CASHFLOW => 'cashflow de cheques 8 semanas',
            AiConsultaOperativaSupport::INTENT_STOCK_CRITICO => 'stock crítico de insumos',
            AiConsultaOperativaSupport::INTENT_BRIEF_DIA => 'armar brief del día',
            AiConsultaOperativaSupport::INTENT_COLA_HITL => 'cola HITL',
            AiConsultaOperativaSupport::INTENT_PROPUESTA_PAGO_RESUMEN => 'propuestas de pago',
            AiConsultaOperativaSupport::INTENT_CASH_POSITION => 'cash position',
            AiConsultaOperativaSupport::INTENT_AP_AGING => 'aging de proveedores',
            AiConsultaOperativaSupport::INTENT_AR_AGING => 'aging de clientes',
            AiConsultaOperativaSupport::INTENT_EMPLEADO_LEGAJO => null,
            AiConsultaOperativaSupport::INTENT_LIQUIDACION_RESUMEN => null,
            AiConsultaOperativaSupport::INTENT_LIQUIDACIONES_PERIODO => 'liquidaciones este mes',
            AiConsultaOperativaSupport::INTENT_LIQUIDACIONES_PENDIENTES_CIERRE => 'liquidaciones pendientes de cierre',
            AiConsultaOperativaSupport::INTENT_EMPLEADOS_INGRESADOS => 'empleados ingresados este mes',
            AiConsultaOperativaSupport::INTENT_RECEPCION_BORRADORES => 'recepciones en borrador',
            AiConsultaOperativaSupport::INTENT_TM_PENDIENTES => 'transferencias pendientes',
            AiConsultaOperativaSupport::INTENT_INGRESO_EGRESO_PERIODO => 'ingresos y egresos este mes',
            AiConsultaOperativaSupport::INTENT_TICKETS_KPI => 'resumen de tickets',
            AiConsultaOperativaSupport::INTENT_TICKETS_ABIERTOS => 'tickets abiertos',
            AiConsultaOperativaSupport::INTENT_PRECARGA_PENDIENTES => 'precargas pendientes',
            AiConsultaOperativaSupport::INTENT_ENTIDAD_DOLOR => 'cheques para depositar',
            AiConsultaOperativaSupport::INTENT_MIS_APROBACIONES => 'mis aprobaciones',
            AiConsultaOperativaSupport::INTENT_PATRON_ANALITICO => 'proveedores con cuit duplicado',
            AiConsultaOperativaSupport::INTENT_HISTORIAL_PRECIOS_COMPRA => 'precios de costo del insumo muzarella en las ultimas 3 compras',
            AiConsultaOperativaSupport::INTENT_PROYECCION_PAGOS => 'proyeccion de pagos',
            AiConsultaOperativaSupport::INTENT_CONTRATOS_POR_VENCER => 'contratos por vencer a 60 dias',
            AiConsultaOperativaSupport::INTENT_PRECIO_POR_PROVEEDOR => 'precio por proveedor del sku 3048',
            AiConsultaOperativaSupport::INTENT_EXISTENCIAS_DEPOSITO => 'existencias por deposito del sku 3048',
            default => null,
        };
    }
}
