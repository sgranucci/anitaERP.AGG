<?php

namespace App\Support\Ai;

use App\Models\Ai\AiAgenteEvento;
use App\Models\Compras\PropuestaPago;
use App\Support\Caja\ChequeCarteraAgingSupport;
use App\Support\Caja\ChequeCashflowSemanalSupport;
use App\Support\Compras\CashPositionSupport;
use App\Support\Compras\ComprasKpisOperativosSupport;
use App\Support\Compras\PropuestaPagoModoSupport;
use App\Support\Stock\ArticuloUsoInsumoSupport;
use App\Support\Stock\UsuarioDepositoAutorizado;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Snapshots de decisión de negocio para el copiloto (cash, aging, stock, brief).
 * Solo lectura; reutiliza supports canónicos.
 */
final class AiConsultaDecisionSupport
{
    /**
     * @param  array<string,mixed>  $params
     * @return array<string,mixed>
     */
    public static function chequeAging(array $params): array
    {
        if (! self::puedeOMcp($params, can('listar-cheque', false))) {
            return self::fallo(AiConsultaOperativaSupport::INTENT_CHEQUE_AGING, 'Sin permiso para listar cheques.');
        }

        $empresaId = ! empty($params['empresa_id']) ? (int) $params['empresa_id'] : null;
        $hasta = ! empty($params['fecha_hasta']) ? (string) $params['fecha_hasta'] : date('Y-m-d');
        $bucket = isset($params['bucket']) ? trim((string) $params['bucket']) : '';
        $filtros = [];
        if ($bucket !== '' && in_array($bucket, ChequeCarteraAgingSupport::BUCKETS, true)) {
            $filtros['bucket'] = $bucket;
        }
        if ($empresaId) {
            $filtros['empresa_id'] = $empresaId;
        }

        $resumen = ChequeCarteraAgingSupport::resumir($empresaId, $hasta, $filtros);
        $buckets = $resumen['buckets'] ?? [];
        $vencidos = (int) ($buckets['vencido']['cantidad'] ?? 0);
        $prox = (int) ($buckets['0_7']['cantidad'] ?? 0);
        $montoVenc = (float) ($buckets['vencido']['monto'] ?? 0);
        $montoProx = (float) ($buckets['0_7']['monto'] ?? 0);

        $parrafos = [
            'Aging cartera CHT al '.$hasta,
            'Vencidos: '.$vencidos.' · $ '.number_format($montoVenc, 2, ',', '.'),
            'Por vencer 0–7 días: '.$prox.' · $ '.number_format($montoProx, 2, ',', '.'),
            'Cartera total: '.(int) ($resumen['total_cantidad_cartera'] ?? 0)
                .' cheques · $ '.number_format((float) ($resumen['total_monto_cartera'] ?? 0), 2, ',', '.'),
        ];

        $filas = [];
        foreach ($buckets as $key => $b) {
            if ((int) ($b['cantidad'] ?? 0) === 0) {
                continue;
            }
            $filas[] = [
                'bucket' => (string) ($b['label'] ?? $key),
                'cantidad' => (string) (int) $b['cantidad'],
                'monto' => number_format((float) $b['monto'], 2, ',', '.'),
            ];
        }

        $detalle = [];
        $depositables = [];
        foreach (array_slice($resumen['filas'] ?? [], 0, 20) as $f) {
            if (! in_array((string) ($f['bucket'] ?? ''), ['vencido', '0_7'], true)) {
                continue;
            }
            $chequeId = (int) ($f['id'] ?? 0);
            $detalle[] = [
                'id' => (string) $chequeId,
                'cheque' => (string) ($f['numerocheque'] ?? $f['id']),
                'cliente' => (string) ($f['cliente'] ?? ''),
                'pago' => (string) ($f['fechapago'] ?? ''),
                'bucket' => (string) ($f['bucket_label'] ?? ''),
                'monto' => number_format((float) ($f['monto'] ?? 0), 2, ',', '.'),
            ];
            if ($chequeId > 0 && ! empty($f['puede_depositar'])) {
                $depositables[] = [
                    'id' => $chequeId,
                    'numero' => (string) ($f['numerocheque'] ?? $chequeId),
                    'monto' => (float) ($f['monto'] ?? 0),
                    'cliente' => (string) ($f['cliente'] ?? ''),
                    'empresa_id' => (int) ($f['empresa_id'] ?? 0),
                ];
            }
        }
        $depositables = array_slice($depositables, 0, 10);

        $links = [
            [
                'etiqueta' => 'Abrir aging cartera',
                'url' => route('aging_cheque_cartera', array_filter([
                    'empresa_id' => $empresaId,
                    'hasta' => $hasta,
                    'para_depositar' => 1,
                ])),
            ],
            [
                'etiqueta' => 'Cheques para depositar (explorador)',
                'url' => route('cheque', ['para_depositar' => 1]),
            ],
        ];

        return [
            'ok' => true,
            'intent' => AiConsultaOperativaSupport::INTENT_CHEQUE_AGING,
            'score' => 0.9,
            'parrafos' => $parrafos,
            'links' => $links,
            'tabla' => [
                'columnas' => [
                    ['key' => 'bucket', 'label' => 'Tramo'],
                    ['key' => 'cantidad', 'label' => 'Cant.'],
                    ['key' => 'monto', 'label' => 'Monto'],
                ],
                'filas' => $filas,
            ],
            'datos' => [
                'hasta' => $hasta,
                'vencidos' => $vencidos,
                'proximos_0_7' => $prox,
                'detalle_urgente' => $detalle,
                'depositar' => [
                    'cheque_ids' => array_column($depositables, 'id'),
                    'preview' => $depositables,
                    'puede_depositar' => $depositables !== [] && can('editar-cheque', false),
                ],
                // Misma cartera elegible (origen R, sin caución/depósito): HITL caucionar
                'caucionar' => [
                    'cheque_ids' => array_column($depositables, 'id'),
                    'preview' => $depositables,
                    'puede_caucionar' => $depositables !== [] && can('editar-cheque', false),
                ],
                'pasos' => [
                    [
                        'etiqueta' => 'Ver vencidos / por depositar',
                        'frase' => 'cheques para depositar',
                        'url' => route('aging_cheque_cartera', ['para_depositar' => 1, 'hasta' => $hasta]),
                    ],
                    [
                        'etiqueta' => 'Cashflow semanal',
                        'frase' => 'cashflow de cheques',
                        'url' => route('cashflow_cheque'),
                    ],
                ],
            ],
        ];
    }

    /**
     * Cola HITL de agentes (pendientes / vistos) con acciones en el chat.
     *
     * @param  array<string,mixed>  $params
     * @return array<string,mixed>
     */
    public static function colaHitl(array $params): array
    {
        if (! self::puedeOMcp($params, can('listar-ai-decisiones', false))) {
            return self::fallo(AiConsultaOperativaSupport::INTENT_COLA_HITL, 'Sin permiso para la cola HITL.');
        }
        if (! Schema::hasTable('ai_agente_evento')) {
            return self::fallo(AiConsultaOperativaSupport::INTENT_COLA_HITL, 'La cola HITL no está disponible.');
        }

        $estado = strtolower(trim((string) ($params['estado'] ?? 'abiertos')));
        $filtros = [];
        if (in_array($estado, ['pendiente', 'visto', 'descartado', 'resuelto'], true)) {
            $filtros['estado'] = $estado;
        }

        $coleccion = AiAgenteEventoHitlSupport::listar($filtros, false);
        if ($estado === 'abiertos' || $estado === '') {
            $coleccion = $coleccion->filter(static function ($e) {
                return in_array((string) $e->estado, [
                    AiAgenteEvento::ESTADO_PENDIENTE,
                    AiAgenteEvento::ESTADO_VISTO,
                ], true);
            })->values();
        }

        $max = isset($params['max_lineas']) ? max(1, min(40, (int) $params['max_lineas'])) : 15;
        $filas = [];
        $acciones = [];
        foreach ($coleccion->take($max) as $e) {
            $id = (int) $e->id;
            $filas[] = [
                'id' => (string) $id,
                'evento' => (string) ($e->evento ?? ''),
                'severidad' => (string) ($e->severidad ?? ''),
                'estado' => (string) ($e->estado ?? ''),
                'resumen' => mb_substr((string) ($e->resumen ?? ''), 0, 80),
            ];
            $url = AiAgenteEventoHitlSupport::urlEntidad($e);
            $acciones[] = [
                'evento_id' => $id,
                'etiqueta' => '#'.$id.' · '.mb_substr((string) ($e->resumen ?? $e->evento), 0, 50),
                'estado' => (string) $e->estado,
                'puede_resolver' => ! in_array((string) $e->estado, [
                    AiAgenteEvento::ESTADO_RESUELTO,
                    AiAgenteEvento::ESTADO_DESCARTADO,
                ], true),
                'url' => $url,
            ];
        }

        $pendientes = $coleccion->where('estado', AiAgenteEvento::ESTADO_PENDIENTE)->count();
        $parrafos = [
            'Cola HITL de agentes',
            'Abiertos listados: '.count($filas)
                .($coleccion->count() > $max ? ' (de '.$coleccion->count().')' : ''),
            'Pendientes (sin ver): '.$pendientes,
        ];
        if ($filas === []) {
            $parrafos[] = 'No hay eventos abiertos. Bien.';
        }

        return [
            'ok' => true,
            'intent' => AiConsultaOperativaSupport::INTENT_COLA_HITL,
            'score' => 0.9,
            'parrafos' => $parrafos,
            'links' => [
                [
                    'etiqueta' => 'Abrir cola HITL',
                    'url' => route('ai_agente_evento'),
                ],
            ],
            'tabla' => [
                'columnas' => [
                    ['key' => 'id', 'label' => 'ID'],
                    ['key' => 'evento', 'label' => 'Evento'],
                    ['key' => 'severidad', 'label' => 'Sev.'],
                    ['key' => 'estado', 'label' => 'Estado'],
                    ['key' => 'resumen', 'label' => 'Resumen'],
                ],
                'filas' => $filas,
            ],
            'datos' => [
                'hitl_eventos' => $acciones,
                'puede_hitl' => true,
                'cantidad' => count($filas),
            ],
        ];
    }

    private static function puedeCashPosition(): bool
    {
        return can('listar-propuesta-pago', false)
            || can('listar-pagoproveedor', false)
            || can('listar-cuentacorriente-proveedor', false);
    }

    /** Canal MCP (Bearer): sin sesión de rol; el token autoriza lecturas tipadas. */
    private static function esMcpBridge(array $params): bool
    {
        return ! empty($params['_mcp_bridge']);
    }

    private static function puedeOMcp(array $params, bool $puedeSesion): bool
    {
        return self::esMcpBridge($params) || $puedeSesion;
    }

    /**
     * Cash position: IB vs deuda vencida vs propuestas.
     *
     * @param  array<string,mixed>  $params
     * @return array<string,mixed>
     */
    public static function cashPosition(array $params): array
    {
        if (! self::puedeOMcp($params, self::puedeCashPosition())) {
            return self::fallo(
                AiConsultaOperativaSupport::INTENT_CASH_POSITION,
                'Sin permiso para cash position / AP.'
            );
        }

        $empresaId = ! empty($params['empresa_id']) ? (int) $params['empresa_id'] : null;
        $res = CashPositionSupport::resumir($empresaId);
        $forecast = $res['forecast'] ?? [];
        $buckets = is_array($forecast['buckets'] ?? null) ? $forecast['buckets'] : [];

        $parrafos = [
            'Cash position (ERP)',
            'Saldos Interbanking: $ '.number_format((float) ($res['total_saldos_ib'] ?? 0), 2, ',', '.'),
            'Deuda vencida CT: $ '.number_format((float) ($res['total_deuda'] ?? 0), 2, ',', '.')
                .' ('.(is_countable($res['deuda_vencida'] ?? null) ? count($res['deuda_vencida']) : 0).' líneas)',
            'Propuestas abiertas: $ '.number_format((float) ($res['total_propuestas'] ?? 0), 2, ',', '.'),
            'Disponible vs deuda: $ '.number_format((float) ($res['disponible_vs_deuda'] ?? 0), 2, ',', '.'),
            'Disponible vs propuestas: $ '.number_format((float) ($res['disponible_vs_propuestas'] ?? 0), 2, ',', '.'),
        ];
        if ($empresaId) {
            $parrafos[] = 'Filtro empresa_id='.$empresaId;
        }

        $filas = [
            ['concepto' => 'Saldos IB', 'monto' => number_format((float) ($res['total_saldos_ib'] ?? 0), 2, ',', '.')],
            ['concepto' => 'Deuda vencida', 'monto' => number_format((float) ($res['total_deuda'] ?? 0), 2, ',', '.')],
            ['concepto' => 'Propuestas abiertas', 'monto' => number_format((float) ($res['total_propuestas'] ?? 0), 2, ',', '.')],
            ['concepto' => 'Disp. vs deuda', 'monto' => number_format((float) ($res['disponible_vs_deuda'] ?? 0), 2, ',', '.')],
            ['concepto' => 'Disp. vs propuestas', 'monto' => number_format((float) ($res['disponible_vs_propuestas'] ?? 0), 2, ',', '.')],
        ];
        foreach ($buckets as $b) {
            if (! is_array($b)) {
                continue;
            }
            $filas[] = [
                'concepto' => 'Forecast · '.((string) ($b['etiqueta'] ?? $b['clave'] ?? '')),
                'monto' => number_format((float) ($b['monto'] ?? 0), 2, ',', '.')
                    .' ('.(int) ($b['cantidad'] ?? 0).')',
            ];
        }

        return [
            'ok' => true,
            'intent' => AiConsultaOperativaSupport::INTENT_CASH_POSITION,
            'score' => 0.9,
            'parrafos' => $parrafos,
            'links' => [
                [
                    'etiqueta' => 'Abrir cash position',
                    'url' => route('cash_position', array_filter(['empresa_id' => $empresaId])),
                ],
                [
                    'etiqueta' => 'Propuestas de pago',
                    'url' => route('propuesta_pago'),
                ],
            ],
            'tabla' => [
                'columnas' => [
                    ['key' => 'concepto', 'label' => 'Concepto'],
                    ['key' => 'monto', 'label' => 'Monto'],
                ],
                'filas' => $filas,
            ],
            'datos' => [
                'total_ib' => (float) ($res['total_saldos_ib'] ?? 0),
                'total_deuda' => (float) ($res['total_deuda'] ?? 0),
                'crear_borrador_propuesta' => self::metaCrearBorradorPropuesta($empresaId),
                'pasos' => [
                    [
                        'etiqueta' => 'Aging proveedores (top deuda)',
                        'frase' => 'aging de proveedores',
                        'url' => route('cash_position'),
                    ],
                    [
                        'etiqueta' => 'Propuestas de pago',
                        'frase' => 'propuestas de pago',
                        'url' => route('propuesta_pago'),
                    ],
                ],
            ],
        ];
    }

    /**
     * Aging / top deuda vencida de proveedores (desde CashPositionSupport).
     *
     * @param  array<string,mixed>  $params
     * @return array<string,mixed>
     */
    public static function apAging(array $params): array
    {
        if (! self::puedeOMcp($params, self::puedeCashPosition())) {
            return self::fallo(
                AiConsultaOperativaSupport::INTENT_AP_AGING,
                'Sin permiso para aging de proveedores / AP.'
            );
        }

        $empresaId = ! empty($params['empresa_id']) ? (int) $params['empresa_id'] : null;
        $max = isset($params['max_lineas']) ? max(1, min(40, (int) $params['max_lineas'])) : 15;
        $res = CashPositionSupport::resumir($empresaId);
        $forecast = CashPositionSupport::forecastBuckets($empresaId, (float) ($res['total_saldos_ib'] ?? 0));
        $buckets = $forecast['buckets'] ?? [];

        $porProveedor = [];
        foreach ($res['deuda_vencida'] ?? [] as $cc) {
            $provId = (int) ($cc->proveedor_id ?? 0);
            $codigo = (string) ($cc->proveedores->codigo ?? $provId);
            $nombre = (string) ($cc->proveedores->nombre ?? '');
            $key = $provId > 0 ? (string) $provId : $codigo;
            if (! isset($porProveedor[$key])) {
                $porProveedor[$key] = [
                    'codigo' => $codigo,
                    'nombre' => $nombre,
                    'saldo' => 0.0,
                    'lineas' => 0,
                ];
            }
            $porProveedor[$key]['saldo'] += (float) ($cc->saldo ?? 0);
            $porProveedor[$key]['lineas']++;
        }
        uasort($porProveedor, static fn ($a, $b) => ($b['saldo'] <=> $a['saldo']));
        $top = array_slice(array_values($porProveedor), 0, $max);

        $parrafos = [
            'Aging proveedores · deuda vencida CT',
            'Total vencido: $ '.number_format((float) ($res['total_deuda'] ?? 0), 2, ',', '.'),
            'Proveedores en top: '.count($top),
        ];
        foreach ($buckets as $b) {
            if (! is_array($b) || (float) ($b['monto'] ?? 0) <= 0) {
                continue;
            }
            $parrafos[] = ((string) ($b['etiqueta'] ?? '')).': $ '
                .number_format((float) $b['monto'], 2, ',', '.')
                .' ('.(int) ($b['cantidad'] ?? 0).')';
        }

        $filas = [];
        $pasos = [];
        foreach ($top as $p) {
            $filas[] = [
                'codigo' => (string) $p['codigo'],
                'proveedor' => mb_substr((string) $p['nombre'], 0, 40),
                'lineas' => (string) (int) $p['lineas'],
                'saldo' => number_format((float) $p['saldo'], 2, ',', '.'),
            ];
            $pasos[] = [
                'etiqueta' => 'CT proveedor '.$p['codigo'],
                'frase' => 'saldo del proveedor '.$p['codigo'].' solo deuda',
                'url' => route('proveedor_cuentacorriente_reporte', [
                    'consultar' => 1,
                    'proveedor_codigo' => $p['codigo'],
                ]),
            ];
        }
        if ($filas === []) {
            $parrafos[] = 'No hay deuda vencida pendiente en el filtro.';
        }

        return [
            'ok' => true,
            'intent' => AiConsultaOperativaSupport::INTENT_AP_AGING,
            'score' => 0.89,
            'parrafos' => $parrafos,
            'links' => [
                [
                    'etiqueta' => 'Cash position',
                    'url' => route('cash_position', array_filter(['empresa_id' => $empresaId])),
                ],
                [
                    'etiqueta' => 'Reporte CT proveedores',
                    'url' => route('proveedor_cuentacorriente_reporte'),
                ],
            ],
            'tabla' => [
                'columnas' => [
                    ['key' => 'codigo', 'label' => 'Código'],
                    ['key' => 'proveedor', 'label' => 'Proveedor'],
                    ['key' => 'lineas', 'label' => 'Lín.'],
                    ['key' => 'saldo', 'label' => 'Saldo venc.'],
                ],
                'filas' => $filas,
            ],
            'datos' => [
                'total_deuda' => (float) ($res['total_deuda'] ?? 0),
                'pasos' => array_slice($pasos, 0, 8),
                'crear_borrador_propuesta' => self::metaCrearBorradorPropuesta($empresaId),
            ],
        ];
    }

    /**
     * @return array{puede: bool, empresa_id: int|null, fecha_hasta: string, mensaje?: string}
     */
    private static function metaCrearBorradorPropuesta(?int $empresaId): array
    {
        $prefsEmpresa = AiConsultaOperativaPreferenciasSupport::leer()['empresa_id'] ?? null;
        $emp = $empresaId && $empresaId > 0
            ? $empresaId
            : ($prefsEmpresa ? (int) $prefsEmpresa : null);
        $puede = can('crear-propuesta-pago', false);
        $meta = [
            'puede' => $puede && $emp !== null && $emp > 0,
            'empresa_id' => $emp,
            'fecha_hasta' => date('Y-m-d'),
        ];
        if ($puede && ($emp === null || $emp <= 0)) {
            $meta['mensaje'] = 'Fijá empresa en preferencias del FAB para crear el borrador.';
        }

        return $meta;
    }

    /**
     * Resumen de propuestas de pago (AP) por estado.
     *
     * @param  array<string,mixed>  $params
     * @return array<string,mixed>
     */
    public static function propuestaPagoResumen(array $params): array
    {
        if (! self::puedeOMcp($params, can('listar-propuesta-pago', false))) {
            return self::fallo(
                AiConsultaOperativaSupport::INTENT_PROPUESTA_PAGO_RESUMEN,
                'Sin permiso para propuestas de pago.'
            );
        }
        if (! Schema::hasTable('propuesta_pago')) {
            return self::fallo(
                AiConsultaOperativaSupport::INTENT_PROPUESTA_PAGO_RESUMEN,
                'Tabla de propuestas no disponible.'
            );
        }

        $q = PropuestaPago::query();
        if (! empty($params['empresa_id'])) {
            $q->where('empresa_id', (int) $params['empresa_id']);
        }

        $porEstado = (clone $q)
            ->selectRaw('estado, COUNT(*) as cant, COALESCE(SUM(monto_total),0) as monto')
            ->groupBy('estado')
            ->orderBy('estado')
            ->get();

        $filas = [];
        $parrafos = ['Propuestas de pago (ERP)'];
        $abiertas = 0;
        $montoAbiertas = 0.0;
        foreach ($porEstado as $r) {
            $est = (string) ($r->estado ?? '');
            $cant = (int) ($r->cant ?? 0);
            $monto = (float) ($r->monto ?? 0);
            $filas[] = [
                'estado' => $est,
                'cantidad' => (string) $cant,
                'monto' => number_format($monto, 2, ',', '.'),
            ];
            if (in_array($est, ['BORRADOR', 'EN_APROBACION', 'AUTORIZADA', 'EJECUTADA_PARCIAL'], true)) {
                $abiertas += $cant;
                $montoAbiertas += $monto;
            }
        }
        $parrafos[] = 'Abiertas (borrador / aprobación / autorizada / parcial): '.$abiertas
            .' · $ '.number_format($montoAbiertas, 2, ',', '.');
        if ($filas === []) {
            $parrafos[] = 'No hay propuestas en el filtro.';
        }

        $recientes = (clone $q)->orderByDesc('id')->limit(8)->get(['id', 'estado', 'fecha', 'monto_total', 'detalle', 'empresa_id']);
        $pasos = [];
        foreach ($recientes as $p) {
            $pasos[] = [
                'etiqueta' => 'Propuesta #'.$p->id.' · '.$p->estado,
                'frase' => '',
                'url' => route('editar_propuesta_pago', $p->id).'?origen=modal_consulta&vista=consulta',
            ];
        }

        $enviarMeta = self::metaEnviarAprobacionPropuestas($params);

        return [
            'ok' => true,
            'intent' => AiConsultaOperativaSupport::INTENT_PROPUESTA_PAGO_RESUMEN,
            'score' => 0.88,
            'parrafos' => $parrafos,
            'links' => [
                [
                    'etiqueta' => 'Listado propuestas de pago',
                    'url' => route('propuesta_pago'),
                ],
                [
                    'etiqueta' => 'Órdenes de pago / ingresos-egresos',
                    'url' => route('pagoproveedor'),
                ],
            ],
            'tabla' => [
                'columnas' => [
                    ['key' => 'estado', 'label' => 'Estado'],
                    ['key' => 'cantidad', 'label' => 'Cant.'],
                    ['key' => 'monto', 'label' => 'Monto'],
                ],
                'filas' => $filas,
            ],
            'datos' => [
                'abiertas' => $abiertas,
                'monto_abiertas' => $montoAbiertas,
                'pasos' => $pasos,
                'crear_borrador_propuesta' => self::metaCrearBorradorPropuesta(
                    ! empty($params['empresa_id']) ? (int) $params['empresa_id'] : null
                ),
                'enviar_aprobacion_propuestas' => $enviarMeta,
            ],
        ];
    }

    /**
     * Borradores/rechazadas elegibles para HITL «enviar a aprobación».
     *
     * @param  array<string,mixed>  $params
     * @return array{puede: bool, preview: list<array<string,mixed>>, mensaje?: string}
     */
    private static function metaEnviarAprobacionPropuestas(array $params): array
    {
        $puede = can('enviar-aprobacion-propuesta-pago', false);
        if (! $puede) {
            return ['puede' => false, 'preview' => [], 'mensaje' => 'Sin permiso enviar-aprobacion-propuesta-pago.'];
        }
        if (! Schema::hasTable('propuesta_pago')) {
            return ['puede' => false, 'preview' => []];
        }

        $q = PropuestaPago::query()
            ->whereIn('estado', ['BORRADOR', 'RECHAZADA'])
            ->orderByDesc('id')
            ->limit(5);
        if (! empty($params['empresa_id'])) {
            $q->where('empresa_id', (int) $params['empresa_id']);
        }

        $preview = [];
        foreach ($q->get(['id', 'estado', 'monto_total', 'detalle', 'empresa_id']) as $p) {
            $empId = (int) ($p->empresa_id ?? 0);
            $preview[] = [
                'id' => (int) $p->id,
                'estado' => (string) $p->estado,
                'monto' => (float) ($p->monto_total ?? 0),
                'detalle' => mb_substr((string) ($p->detalle ?? ''), 0, 80),
                'empresa_id' => $empId,
                'exige_arbol' => $empId > 0 ? PropuestaPagoModoSupport::exigeArbol($empId) : true,
                'url' => route('editar_propuesta_pago', $p->id).'?origen=modal_consulta&vista=consulta',
            ];
        }

        return [
            'puede' => $preview !== [],
            'preview' => $preview,
            'mensaje' => $preview === [] ? 'No hay propuestas en BORRADOR/RECHAZADA para enviar.' : null,
        ];
    }

    /**
     * @param  array<string,mixed>  $params
     * @return array<string,mixed>
     */
    public static function chequeCashflow(array $params): array
    {
        if (! can('listar-cheque', false)) {
            return self::fallo(AiConsultaOperativaSupport::INTENT_CHEQUE_CASHFLOW, 'Sin permiso para listar cheques.');
        }

        $empresaId = ! empty($params['empresa_id']) ? (int) $params['empresa_id'] : null;
        $semanas = isset($params['semanas']) ? max(1, min(12, (int) $params['semanas'])) : 8;
        $desde = ! empty($params['fecha_desde']) ? (string) $params['fecha_desde'] : null;

        $resumen = ChequeCashflowSemanalSupport::resumir($empresaId, $desde, $semanas);
        $tot = $resumen['totales'] ?? [];
        $parrafos = [
            'Cashflow de cheques · '.$semanas.' semanas'
                .(isset($resumen['desde']) ? ' desde '.$resumen['desde'] : ''),
            'CHT (cartera): $ '.number_format((float) ($tot['cht_monto'] ?? 0), 2, ',', '.'),
            'CHP (emitidos): $ '.number_format((float) ($tot['chp_monto'] ?? 0), 2, ',', '.'),
            'Total: $ '.number_format((float) ($tot['monto'] ?? 0), 2, ',', '.')
                .' · '.(int) ($tot['cantidad'] ?? 0).' cheques',
        ];

        $filas = [];
        foreach (array_slice($resumen['semanas'] ?? [], 0, 12) as $sem) {
            $filas[] = [
                'semana' => (string) ($sem['label'] ?? ''),
                'cht' => number_format((float) ($sem['cht']['monto'] ?? 0), 2, ',', '.'),
                'chp' => number_format((float) ($sem['chp']['monto'] ?? 0), 2, ',', '.'),
                'total' => number_format((float) ($sem['total_monto'] ?? 0), 2, ',', '.'),
            ];
        }

        return [
            'ok' => true,
            'intent' => AiConsultaOperativaSupport::INTENT_CHEQUE_CASHFLOW,
            'score' => 0.88,
            'parrafos' => $parrafos,
            'links' => [
                [
                    'etiqueta' => 'Abrir cashflow semanal',
                    'url' => route('cashflow_cheque', array_filter([
                        'empresa_id' => $empresaId,
                        'semanas' => $semanas,
                        'desde' => $desde,
                    ])),
                ],
            ],
            'tabla' => [
                'columnas' => [
                    ['key' => 'semana', 'label' => 'Semana'],
                    ['key' => 'cht', 'label' => 'CHT'],
                    ['key' => 'chp', 'label' => 'CHP'],
                    ['key' => 'total', 'label' => 'Total'],
                ],
                'filas' => $filas,
            ],
            'datos' => [
                'totales' => $tot,
                'semanas' => $semanas,
            ],
        ];
    }

    /**
     * Insumos con saldo total ≤ umbral (default 0) en depósitos autorizados.
     *
     * @param  array<string,mixed>  $params
     * @return array<string,mixed>
     */
    public static function stockCritico(array $params): array
    {
        if (! self::puedeOMcp($params, can('listar-articulos', false) || can('editar-articulos', false))) {
            return self::fallo(AiConsultaOperativaSupport::INTENT_STOCK_CRITICO, 'Sin permiso para artículos.');
        }

        $umbral = isset($params['umbral']) && is_numeric($params['umbral'])
            ? (float) $params['umbral']
            : 0.0;
        $max = isset($params['max_lineas']) ? max(1, min(80, (int) $params['max_lineas'])) : 40;
        $usoId = ArticuloUsoInsumoSupport::idUsoInsumo();
        if ($usoId === null) {
            return self::fallo(
                AiConsultaOperativaSupport::INTENT_STOCK_CRITICO,
                'No está configurado el uso INSUMO GASTRONOMIA.'
            );
        }

        $q = DB::table('articulo_saldo_deposito as asd')
            ->join('articulo as a', 'a.id', '=', 'asd.articulo_id')
            ->leftJoin('depmae as d', 'd.id', '=', 'asd.deposito_id')
            ->where('a.usoarticulo_id', $usoId)
            ->where('a.estado', 'ACTIVO');

        // Restringir a depósitos autorizados del usuario cuando aplique
        $depIds = UsuarioDepositoAutorizado::idsRestringidos();
        if (is_array($depIds) && $depIds !== []) {
            $q->whereIn('asd.deposito_id', $depIds);
        }

        if (! empty($params['deposito_id'])) {
            $q->where('asd.deposito_id', (int) $params['deposito_id']);
        } elseif (! empty($params['deposito_codigo'])) {
            $q->where('d.codigo', (string) $params['deposito_codigo']);
        }
        if (! empty($params['empresa_id'])) {
            $q->where('d.empresa_id', (int) $params['empresa_id']);
        }

        $filasRaw = $q
            ->selectRaw('a.id as articulo_id, a.sku, a.descripcion, SUM(asd.cantidad) as saldo_total')
            ->groupBy('a.id', 'a.sku', 'a.descripcion')
            ->havingRaw('SUM(asd.cantidad) <= ?', [$umbral])
            ->orderByRaw('SUM(asd.cantidad) ASC')
            ->limit($max)
            ->get();

        $filas = [];
        foreach ($filasRaw as $r) {
            $filas[] = [
                'sku' => (string) ($r->sku ?? ''),
                'descripcion' => mb_substr((string) ($r->descripcion ?? ''), 0, 60),
                'saldo' => number_format((float) ($r->saldo_total ?? 0), 3, ',', '.'),
            ];
        }

        $parrafos = [
            'Stock crítico de insumos (saldo ≤ '.number_format($umbral, 3, ',', '.').')',
            'Insumos listados: '.count($filas).(count($filas) >= $max ? ' (tope '.$max.')' : ''),
        ];
        if ($filas === []) {
            $parrafos[] = 'No hay insumos bajo el umbral en los depósitos consultados.';
        } else {
            $parrafos[] = 'Priorice pedido por consumo o revisión de kardex del SKU.';
        }

        $links = [];
        if (can('listar-articulos', false)) {
            $links[] = [
                'etiqueta' => 'Listado de artículos',
                'url' => route('articulo'),
            ];
        }

        $pasos = [];
        foreach (array_slice($filasRaw->all(), 0, 5) as $r) {
            $sku = (string) ($r->sku ?? '');
            if ($sku === '') {
                continue;
            }
            $pasos[] = [
                'etiqueta' => 'Saldo / kardex '.$sku,
                'frase' => 'saldo del insumo '.$sku,
                'url' => can('editar-articulos', false) || can('listar-articulos', false)
                    ? route('editar_articulo', [
                        'id' => (int) $r->articulo_id,
                        'origen' => 'modal_consulta',
                        'vista' => 'consulta',
                    ])
                    : null,
            ];
        }
        $pasos[] = [
            'etiqueta' => 'Planear pedido por consumo',
            'frase' => 'pedido consumo CC … depósito … últimos 60 días',
        ];

        return [
            'ok' => true,
            'intent' => AiConsultaOperativaSupport::INTENT_STOCK_CRITICO,
            'score' => 0.87,
            'parrafos' => $parrafos,
            'links' => $links,
            'tabla' => [
                'columnas' => [
                    ['key' => 'sku', 'label' => 'SKU'],
                    ['key' => 'descripcion', 'label' => 'Descripción'],
                    ['key' => 'saldo', 'label' => 'Saldo'],
                ],
                'filas' => $filas,
            ],
            'datos' => [
                'umbral' => $umbral,
                'cantidad' => count($filas),
                'pasos' => $pasos,
            ],
        ];
    }

    /**
     * Brief de reunión: arma / muestra / limpia el resumen del día en sesión.
     *
     * @param  array<string,mixed>  $params
     * @return array<string,mixed>
     */
    public static function briefDia(array $params): array
    {
        $mcp = self::esMcpBridge($params);
        $accion = strtolower(trim((string) ($params['accion'] ?? $params['valor'] ?? 'armar')));
        if (in_array($accion, ['mostrar', 'ver', 'listar'], true)) {
            if ($mcp) {
                return self::fallo(
                    AiConsultaOperativaSupport::INTENT_BRIEF_DIA,
                    'MCP solo soporta accion=armar (sin sesión).'
                );
            }

            return AiConsultaOperativaBriefSupport::mostrar();
        }
        if (in_array($accion, ['limpiar', 'borrar', 'reset'], true)) {
            if ($mcp) {
                return self::fallo(
                    AiConsultaOperativaSupport::INTENT_BRIEF_DIA,
                    'MCP no limpia el brief de sesión.'
                );
            }
            AiConsultaOperativaBriefSupport::limpiar();

            return [
                'ok' => true,
                'intent' => AiConsultaOperativaSupport::INTENT_BRIEF_DIA,
                'score' => 1.0,
                'parrafos' => ['Brief del día vaciado.'],
                'links' => [],
                'datos' => ['accion' => 'limpiar'],
            ];
        }

        // armar / generar (filtrado por asistente_rol)
        $rol = AiConsultaOperativaAsistenteRolSupport::normalizar(
            (string) ($params['asistente_rol'] ?? AiConsultaOperativaPreferenciasSupport::leer()['asistente_rol'] ?? '')
        );
        $push = static function (array &$items, array $item) use ($rol): void {
            if (AiConsultaOperativaAsistenteRolSupport::briefIncluye($rol, $item)) {
                $items[] = $item;
            }
        };
        $puede = static fn (string ...$perms): bool => $mcp
            || (bool) array_filter($perms, static fn (string $p): bool => can($p, false));

        $items = [];
        if ($puede('listar-ordencompra', 'listar-kpi-compras')) {
            try {
                $kpi = ComprasKpisOperativosSupport::resumen($params);
                $push($items, [
                    'titulo' => 'Compras · resumen operativo',
                    'lineas' => self::lineasDesdeKpi($kpi),
                    'frase' => 'resumen operativo de compras',
                ]);
                $pend = ComprasKpisOperativosSupport::ocPendientesFirma($params);
                $push($items, [
                    'titulo' => 'OC pendientes de firma',
                    'lineas' => self::lineasDesdeKpi($pend),
                    'frase' => 'OC pendientes de firma',
                    'url' => route('consultar_ordencompra'),
                ]);
            } catch (\Throwable) {
                // seguir con otros bloques
            }
        }

        if ($puede('listar-cheque')) {
            $aging = self::chequeAging($params);
            if ($aging['ok'] ?? false) {
                $push($items, [
                    'titulo' => 'Tesorería · aging cheques',
                    'lineas' => array_slice($aging['parrafos'] ?? [], 0, 4),
                    'frase' => 'aging de cheques',
                    'url' => route('aging_cheque_cartera', ['para_depositar' => 1]),
                ]);
            }
        }

        if ($puede('listar-articulos')) {
            $stock = self::stockCritico(array_merge($params, ['max_lineas' => 10]));
            if ($stock['ok'] ?? false) {
                $push($items, [
                    'titulo' => 'Stock crítico (insumos)',
                    'lineas' => array_slice($stock['parrafos'] ?? [], 0, 3),
                    'frase' => 'stock crítico de insumos',
                ]);
            }
        }

        if ($puede('listar-propuesta-pago')) {
            $pp = self::propuestaPagoResumen($params);
            if ($pp['ok'] ?? false) {
                $push($items, [
                    'titulo' => 'Cuentas a pagar · propuestas',
                    'lineas' => array_slice($pp['parrafos'] ?? [], 0, 3),
                    'frase' => 'propuestas de pago',
                    'url' => route('propuesta_pago'),
                ]);
            }
        }

        if ($mcp || self::puedeCashPosition()) {
            $cash = self::cashPosition($params);
            if ($cash['ok'] ?? false) {
                $push($items, [
                    'titulo' => 'Cash position',
                    'lineas' => array_slice($cash['parrafos'] ?? [], 0, 4),
                    'frase' => 'cash position',
                    'url' => route('cash_position'),
                ]);
            }
            $ap = self::apAging(array_merge($params, ['max_lineas' => 5]));
            if ($ap['ok'] ?? false) {
                $push($items, [
                    'titulo' => 'Aging proveedores (vencido)',
                    'lineas' => array_slice($ap['parrafos'] ?? [], 0, 3),
                    'frase' => 'aging de proveedores',
                    'url' => route('cash_position'),
                ]);
            }
        }

        if ($puede('listar-cuentacorriente-cliente', 'listar-clientes')) {
            $ar = AiConsultaCoberturaModulosSupport::arAging(array_merge($params, ['max_lineas' => 5]));
            if ($ar['ok'] ?? false) {
                $push($items, [
                    'titulo' => 'Aging clientes (AR)',
                    'lineas' => array_slice($ar['parrafos'] ?? [], 0, 3),
                    'frase' => 'aging de clientes',
                    'url' => route('cliente_cuentacorriente_reporte'),
                ]);
            }
        }

        if ($puede('listar-ticket', 'listar-administracion-ticket')) {
            $tk = AiConsultaCoberturaModulosSupport::ticketsKpi($params);
            if ($tk['ok'] ?? false) {
                $push($items, [
                    'titulo' => 'Tickets por estado',
                    'lineas' => array_slice($tk['parrafos'] ?? [], 0, 3),
                    'frase' => 'resumen de tickets',
                    'url' => route('consulta_administracion_ticket'),
                ]);
            }
            $ab = AiConsultaCoberturaModulosSupport::ticketsAbiertos(array_merge($params, ['max_lineas' => 5]));
            if ($ab['ok'] ?? false) {
                $push($items, [
                    'titulo' => 'Tickets abiertos (antigüedad)',
                    'lineas' => array_slice($ab['parrafos'] ?? [], 0, 3),
                    'frase' => 'tickets abiertos',
                    'url' => route('consulta_administracion_ticket'),
                ]);
            }
        }

        if ($puede('listar-liquidacion-sueldos')) {
            $liq = AiConsultaCoberturaModulosSupport::liquidacionesPeriodo(array_merge($params, [
                'periodo' => date('Ym'),
                'max_lineas' => 8,
            ]));
            if ($liq['ok'] ?? false) {
                $push($items, [
                    'titulo' => 'Liquidaciones del mes',
                    'lineas' => array_slice($liq['parrafos'] ?? [], 0, 3),
                    'frase' => 'liquidaciones este mes',
                    'url' => route('consultar_liquidacion_sueldos'),
                ]);
            }
            $pend = AiConsultaCoberturaModulosSupport::liquidacionesPendientesCierre(array_merge($params, [
                'periodo' => date('Ym'),
                'max_lineas' => 8,
            ]));
            if (($pend['ok'] ?? false) && (int) ($pend['datos']['cantidad'] ?? 0) > 0) {
                $push($items, [
                    'titulo' => 'Liquidaciones pendientes de cierre',
                    'lineas' => array_slice($pend['parrafos'] ?? [], 0, 3),
                    'frase' => 'liquidaciones pendientes de cierre',
                    'url' => route('consultar_liquidacion_sueldos'),
                ]);
            }
        }

        if ($puede('listar-ai-decisiones')) {
            $cola = self::colaHitl(['estado' => 'abiertos', 'max_lineas' => 5]);
            if ($cola['ok'] ?? false) {
                $push($items, [
                    'titulo' => 'Cola HITL agentes',
                    'lineas' => array_slice($cola['parrafos'] ?? [], 0, 3),
                    'frase' => 'cola HITL',
                    'url' => route('ai_agente_evento'),
                ]);
            }
        }

        if ($items === []) {
            return self::fallo(
                AiConsultaOperativaSupport::INTENT_BRIEF_DIA,
                'No hay módulos habilitados para armar el brief con el asistente «'
                .AiConsultaOperativaAsistenteRolSupport::etiquetas()[$rol]
                .'». Cambiá el rol en preferencias o usá Automático.'
            );
        }

        // MCP: devolver snapshot sin tocar sesión ni campanita/mail.
        if ($mcp) {
            $parrafos = ['Brief del día (MCP · solo lectura)'];
            foreach ($items as $it) {
                $parrafos[] = (string) ($it['titulo'] ?? '');
                foreach (array_slice($it['lineas'] ?? [], 0, 2) as $ln) {
                    $parrafos[] = '  · '.(string) $ln;
                }
            }

            return [
                'ok' => true,
                'intent' => AiConsultaOperativaSupport::INTENT_BRIEF_DIA,
                'score' => 0.9,
                'parrafos' => $parrafos,
                'links' => [],
                'datos' => [
                    'accion' => 'armar',
                    'origen' => 'mcp',
                    'items' => $items,
                ],
            ];
        }

        AiConsultaOperativaPreferenciasSupport::marcarBriefSugerido();
        AiConsultaOperativaBriefSupport::reemplazar($items);
        $mostrar = AiConsultaOperativaBriefSupport::mostrar();
        $lineasAviso = [];
        foreach (array_slice($items, 0, 3) as $it) {
            $lineasAviso[] = (string) ($it['titulo'] ?? '');
        }
        AiConsultaNotificacionSupport::avisarBriefListo($lineasAviso);

        return $mostrar;
    }

    /**
     * @param  array<string,mixed>|mixed  $kpi
     * @return list<string>
     */
    private static function lineasDesdeKpi($kpi): array
    {
        if (! is_array($kpi)) {
            return [];
        }
        if (! empty($kpi['parrafos']) && is_array($kpi['parrafos'])) {
            return array_values(array_map('strval', array_slice($kpi['parrafos'], 0, 6)));
        }
        $out = [];
        foreach (['resumen', 'mensaje', 'total', 'cantidad'] as $k) {
            if (isset($kpi[$k]) && is_scalar($kpi[$k])) {
                $out[] = $k.': '.$kpi[$k];
            }
        }
        if ($out === [] && isset($kpi['kpis']) && is_array($kpi['kpis'])) {
            foreach (array_slice($kpi['kpis'], 0, 6, true) as $nombre => $val) {
                if (is_scalar($val)) {
                    $out[] = $nombre.': '.$val;
                } elseif (is_array($val) && isset($val['valor'])) {
                    $out[] = $nombre.': '.$val['valor'];
                }
            }
        }

        return $out;
    }

    /**
     * @return array<string,mixed>
     */
    private static function fallo(string $intent, string $mensaje): array
    {
        return [
            'ok' => false,
            'intent' => $intent,
            'score' => 0.0,
            'parrafos' => [],
            'links' => [],
            'datos' => [],
            'error' => $mensaje,
        ];
    }
}
