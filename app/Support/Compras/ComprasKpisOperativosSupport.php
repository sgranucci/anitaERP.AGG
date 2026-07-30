<?php

namespace App\Support\Compras;

use App\Models\Compras\Ordencompra;
use App\Models\Stock\Recepcion_Proveedor;
use App\Support\Ai\AiConsultaOperativaSchemaSupport;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * KPIs operativos de Compras para el panel IA (números desde ERP, no del LLM).
 */
final class ComprasKpisOperativosSupport
{
    /** @var list<string> */
    private const RQ_ESTADOS_ACTIVAS = [
        'PENDIENTE',
        'EN ARBOL APROBACION',
        'APROBADA',
        'EN COMPRAS',
        'PARCIAL',
    ];

    /**
     * @param  array<string,mixed>  $params
     * @return array{ok: bool, parrafos: list<string>, tabla?: array, datos: array<string,mixed>, links?: list<array>, error?: string}
     */
    public static function resumen(array $params): array
    {
        $empresaId = self::empresaId($params);
        $max = self::maxLineas($params, 15);

        $baseOc = Ordencompra::query();
        if ($empresaId) {
            $baseOc->where('empresa_id', $empresaId);
        }

        $ocPendiente = (clone $baseOc)->where('estadoordencompra', OrdencompraEstados::PENDIENTE)->count();
        $ocAprobada = (clone $baseOc)->where('estadoordencompra', OrdencompraEstados::APROBADA)->count();
        $ocVencidas = self::queryOcVencidasSinRecepcion($empresaId)->count();
        $rqSinOc = self::contarRqConLineasPendientesOc($empresaId);
        $lineasRqPend = self::contarLineasPendientesOc($empresaId);

        $desde = (string) ($params['fecha_desde'] ?? date('Y-m-01'));
        $hasta = (string) ($params['fecha_hasta'] ?? date('Y-m-d'));
        $lead = self::leadTimeStats($desde, $hasta, $empresaId);

        $parrafos = [
            'Resumen operativo de Compras (ERP, no estimado por IA).',
            'OC PENDIENTE (en circuito / firma): '.$ocPendiente,
            'OC APROBADA: '.$ocAprobada,
            'OC APROBADA con entrega vencida y sin recepción: '.$ocVencidas,
            'Requisiciones activas con líneas sin OC: '.$rqSinOc.' ('.$lineasRqPend.' líneas).',
        ];
        if ($lead['muestra'] > 0) {
            $parrafos[] = 'Lead time OC→1ª recepción ('.$desde.' → '.$hasta.'): promedio '
                .number_format($lead['promedio_dias'], 1, ',', '.')
                .' días (n='.$lead['muestra'].').';
        } else {
            $parrafos[] = 'Lead time: sin recepciones en el período para medir.';
        }
        if ($empresaId) {
            $parrafos[] = 'Filtro empresa_id='.$empresaId.'.';
        }

        $tabla = [
            'columnas' => [
                ['key' => 'kpi', 'label' => 'KPI'],
                ['key' => 'valor', 'label' => 'Valor'],
            ],
            'filas' => [
                ['kpi' => 'OC pendientes de firma/circuito', 'valor' => (string) $ocPendiente],
                ['kpi' => 'OC aprobadas', 'valor' => (string) $ocAprobada],
                ['kpi' => 'OC vencidas sin recepción', 'valor' => (string) $ocVencidas],
                ['kpi' => 'RQ con líneas sin OC', 'valor' => (string) $rqSinOc],
                ['kpi' => 'Líneas RQ pendientes de OC', 'valor' => (string) $lineasRqPend],
                [
                    'kpi' => 'Lead time promedio (días)',
                    'valor' => $lead['muestra'] > 0
                        ? number_format($lead['promedio_dias'], 1, ',', '.')
                        : '—',
                ],
            ],
        ];

        // Top 5 proveedores del período (compacto en resumen)
        $top = self::topProveedoresFilas($desde, $hasta, $empresaId, min(5, $max));
        if ($top !== []) {
            $parrafos[] = 'Top proveedores por monto de comprobantes en el período: ver tabla inferior / intent «top proveedores».';
        }

        return [
            'ok' => true,
            'parrafos' => $parrafos,
            'tabla' => $tabla,
            'datos' => [
                'empresa_id' => $empresaId,
                'fecha_desde' => $desde,
                'fecha_hasta' => $hasta,
                'oc_pendiente' => $ocPendiente,
                'oc_aprobada' => $ocAprobada,
                'oc_vencidas_sin_recepcion' => $ocVencidas,
                'rq_con_lineas_sin_oc' => $rqSinOc,
                'lineas_rq_pendientes_oc' => $lineasRqPend,
                'lead_time' => $lead,
                'top_proveedores' => $top,
            ],
            'links' => self::linksCompras(),
        ];
    }

    /**
     * @param  array<string,mixed>  $params
     * @return array{ok: bool, parrafos: list<string>, tabla?: array, datos: array<string,mixed>, links?: list<array>, error?: string}
     */
    public static function ocPendientesFirma(array $params): array
    {
        $empresaId = self::empresaId($params);
        $max = self::maxLineas($params, 40);

        $q = Ordencompra::query()
            ->with(['proveedores:id,codigo,nombre', 'empresas:id,nombre'])
            ->where('estadoordencompra', OrdencompraEstados::PENDIENTE)
            ->orderByDesc('id');
        if ($empresaId) {
            $q->where('empresa_id', $empresaId);
        }

        $total = (clone $q)->count();
        $rows = $q->limit($max)->get();

        $filas = [];
        foreach ($rows as $oc) {
            $filas[] = [
                'numero' => (string) ($oc->numeroordencompra ?? $oc->id),
                'fecha' => $oc->fecha ? date('d/m/Y', strtotime((string) $oc->fecha)) : '—',
                'proveedor' => trim(($oc->proveedores->codigo ?? '').' '.($oc->proveedores->nombre ?? '')) ?: '—',
                'empresa' => (string) ($oc->empresas->nombre ?? '—'),
                'entrega' => $oc->fechaentrega ? date('d/m/Y', strtotime((string) $oc->fechaentrega)) : '—',
            ];
        }

        $parrafos = [
            'OC en estado PENDIENTE (circuito de aprobación / firma): '.$total.'.',
            $total > $rows->count()
                ? 'Mostrando las '.$rows->count().' más recientes.'
                : ($total === 0 ? 'No hay OC pendientes.' : 'Listado completo del filtro.'),
        ];

        return [
            'ok' => true,
            'parrafos' => $parrafos,
            'tabla' => [
                'columnas' => [
                    ['key' => 'numero', 'label' => 'OC'],
                    ['key' => 'fecha', 'label' => 'Fecha'],
                    ['key' => 'proveedor', 'label' => 'Proveedor'],
                    ['key' => 'empresa', 'label' => 'Empresa'],
                    ['key' => 'entrega', 'label' => 'Entrega'],
                ],
                'filas' => $filas,
            ],
            'datos' => [
                'empresa_id' => $empresaId,
                'total' => $total,
                'mostradas' => $rows->count(),
            ],
            'links' => self::linksCompras(),
        ];
    }

    /**
     * @param  array<string,mixed>  $params
     * @return array{ok: bool, parrafos: list<string>, tabla?: array, datos: array<string,mixed>, links?: list<array>, error?: string}
     */
    public static function ocVencidasSinRecepcion(array $params): array
    {
        $empresaId = self::empresaId($params);
        $max = self::maxLineas($params, 40);

        $q = self::queryOcVencidasSinRecepcion($empresaId)
            ->with(['proveedores:id,codigo,nombre', 'empresas:id,nombre'])
            ->orderBy('fechaentrega');

        $total = (clone $q)->count();
        $rows = $q->limit($max)->get();
        $hoy = Carbon::today();

        $filas = [];
        foreach ($rows as $oc) {
            $entrega = $oc->fechaentrega ? Carbon::parse((string) $oc->fechaentrega) : null;
            $filas[] = [
                'numero' => (string) ($oc->numeroordencompra ?? $oc->id),
                'entrega' => $entrega ? $entrega->format('d/m/Y') : '—',
                'dias_atraso' => $entrega ? (string) $entrega->diffInDays($hoy) : '—',
                'proveedor' => trim(($oc->proveedores->codigo ?? '').' '.($oc->proveedores->nombre ?? '')) ?: '—',
                'empresa' => (string) ($oc->empresas->nombre ?? '—'),
            ];
        }

        return [
            'ok' => true,
            'parrafos' => [
                'OC APROBADA con fecha de entrega vencida y sin recepción de proveedor: '.$total.'.',
                $total > $rows->count()
                    ? 'Mostrando las '.$rows->count().' con entrega más antigua.'
                    : ($total === 0 ? 'Ninguna OC en esa condición.' : 'Listado completo.'),
            ],
            'tabla' => [
                'columnas' => [
                    ['key' => 'numero', 'label' => 'OC'],
                    ['key' => 'entrega', 'label' => 'Entrega'],
                    ['key' => 'dias_atraso', 'label' => 'Días atraso'],
                    ['key' => 'proveedor', 'label' => 'Proveedor'],
                    ['key' => 'empresa', 'label' => 'Empresa'],
                ],
                'filas' => $filas,
            ],
            'datos' => [
                'empresa_id' => $empresaId,
                'total' => $total,
                'mostradas' => $rows->count(),
            ],
            'links' => self::linksCompras(),
        ];
    }

    /**
     * @param  array<string,mixed>  $params
     * @return array{ok: bool, parrafos: list<string>, tabla?: array, datos: array<string,mixed>, links?: list<array>, error?: string}
     */
    public static function leadTimeOcRecepcion(array $params): array
    {
        $empresaId = self::empresaId($params);
        $desde = (string) ($params['fecha_desde'] ?? date('Y-m-d', strtotime('-89 days')));
        $hasta = (string) ($params['fecha_hasta'] ?? date('Y-m-d'));
        $stats = self::leadTimeStats($desde, $hasta, $empresaId, true);

        $parrafos = [
            'Lead time: días entre fecha de OC y primera recepción de proveedor.',
            'Período (por fecha de 1ª recepción): '.date('d/m/Y', strtotime($desde))
                .' → '.date('d/m/Y', strtotime($hasta)).'.',
        ];
        if ($stats['muestra'] === 0) {
            $parrafos[] = 'Sin pares OC–recepción en el período.';
        } else {
            $parrafos[] = 'Muestra: '.$stats['muestra']
                .' — promedio '.number_format($stats['promedio_dias'], 1, ',', '.')
                .' días — mediana '.number_format($stats['mediana_dias'], 1, ',', '.')
                .' — mín '.$stats['min_dias'].' / máx '.$stats['max_dias'].'.';
        }

        $filas = [];
        foreach ($stats['muestra_filas'] ?? [] as $f) {
            $filas[] = [
                'numero' => (string) $f['numero'],
                'fecha_oc' => $f['fecha_oc'],
                'primera_recepcion' => $f['primera_recepcion'],
                'dias' => (string) $f['dias'],
                'proveedor' => $f['proveedor'],
            ];
        }

        $out = [
            'ok' => true,
            'parrafos' => $parrafos,
            'datos' => [
                'empresa_id' => $empresaId,
                'fecha_desde' => $desde,
                'fecha_hasta' => $hasta,
                'lead_time' => $stats,
            ],
            'links' => self::linksCompras(),
        ];
        if ($filas !== []) {
            $out['tabla'] = [
                'columnas' => [
                    ['key' => 'numero', 'label' => 'OC'],
                    ['key' => 'fecha_oc', 'label' => 'Fecha OC'],
                    ['key' => 'primera_recepcion', 'label' => '1ª recepción'],
                    ['key' => 'dias', 'label' => 'Días'],
                    ['key' => 'proveedor', 'label' => 'Proveedor'],
                ],
                'filas' => $filas,
            ];
        }

        return $out;
    }

    /**
     * @param  array<string,mixed>  $params
     * @return array{ok: bool, parrafos: list<string>, tabla?: array, datos: array<string,mixed>, links?: list<array>, error?: string}
     */
    public static function topProveedoresMonto(array $params): array
    {
        $empresaId = self::empresaId($params);
        $desde = (string) ($params['fecha_desde'] ?? date('Y-m-01'));
        $hasta = (string) ($params['fecha_hasta'] ?? date('Y-m-d'));
        $max = self::maxLineas($params, 15);

        $filasRaw = self::topProveedoresFilas($desde, $hasta, $empresaId, $max);
        $filas = [];
        $totalMonto = 0.0;
        foreach ($filasRaw as $i => $row) {
            $totalMonto += (float) $row['monto'];
            $filas[] = [
                'ranking' => (string) ($i + 1),
                'codigo' => (string) $row['codigo'],
                'proveedor' => (string) $row['nombre'],
                'comprobantes' => (string) $row['docs'],
                'monto' => number_format((float) $row['monto'], 2, ',', '.'),
            ];
        }

        return [
            'ok' => true,
            'parrafos' => [
                'Top proveedores por monto de comprobantes (excluye precarga/anulado).',
                'Período: '.date('d/m/Y', strtotime($desde)).' → '.date('d/m/Y', strtotime($hasta)).'.',
                $filas === []
                    ? 'Sin comprobantes en el período.'
                    : 'Suma top: '.number_format($totalMonto, 2, ',', '.').' ('.$max.' proveedores).',
            ],
            'tabla' => [
                'columnas' => [
                    ['key' => 'ranking', 'label' => '#'],
                    ['key' => 'codigo', 'label' => 'Código'],
                    ['key' => 'proveedor', 'label' => 'Proveedor'],
                    ['key' => 'comprobantes', 'label' => 'Docs'],
                    ['key' => 'monto', 'label' => 'Monto'],
                ],
                'filas' => $filas,
            ],
            'datos' => [
                'empresa_id' => $empresaId,
                'fecha_desde' => $desde,
                'fecha_hasta' => $hasta,
                'top' => $filasRaw,
                'monto_top' => $totalMonto,
            ],
            'links' => self::linksCompras(),
        ];
    }

    /**
     * @param  array<string,mixed>  $params
     * @return array{ok: bool, parrafos: list<string>, tabla?: array, datos: array<string,mixed>, links?: list<array>, error?: string}
     */
    public static function rqSinOc(array $params): array
    {
        $empresaId = self::empresaId($params);
        $max = self::maxLineas($params, 40);
        $etiquetaCerrada = RequisicionLineasOcSupport::etiquetaLineaCerradaSinOc();

        $base = DB::table('requisicion as r')
            ->join('requisicion_articulo as ra', 'ra.requisicion_id', '=', 'r.id')
            ->whereIn('r.estado', self::RQ_ESTADOS_ACTIVAS)
            ->where(function ($q) use ($etiquetaCerrada) {
                $q->whereNull('ra.precio_origen_etiqueta')
                    ->orWhere('ra.precio_origen_etiqueta', '!=', $etiquetaCerrada);
            })
            ->whereNotExists(function ($q) {
                $q->select(DB::raw(1))
                    ->from('ordencompra_articulo as oa')
                    ->whereColumn('oa.requisicion_articulo_id', 'ra.id')
                    ->whereNotNull('oa.requisicion_articulo_id');
            });
        if ($empresaId) {
            $base->where('r.empresa_id', $empresaId);
        }

        $agrupado = (clone $base)
            ->groupBy('r.id', 'r.numerorequisicion', 'r.estado', 'r.fecha')
            ->select([
                'r.id',
                'r.numerorequisicion',
                'r.estado',
                'r.fecha',
                DB::raw('COUNT(ra.id) as lineas_pendientes'),
            ])
            ->orderByDesc('lineas_pendientes')
            ->orderByDesc('r.id');

        $totalRq = (int) DB::query()->fromSub(
            (clone $base)->select('r.id')->groupBy('r.id'),
            'x'
        )->count();

        $rows = $agrupado->limit($max)->get();
        $lineasTotales = self::contarLineasPendientesOc($empresaId);

        $filas = [];
        foreach ($rows as $row) {
            $filas[] = [
                'numero' => (string) ($row->numerorequisicion ?? $row->id),
                'fecha' => $row->fecha ? date('d/m/Y', strtotime((string) $row->fecha)) : '—',
                'estado' => (string) ($row->estado ?? '—'),
                'lineas' => (string) ($row->lineas_pendientes ?? 0),
            ];
        }

        return [
            'ok' => true,
            'parrafos' => [
                'Requisiciones activas con al menos una línea sin OC: '.$totalRq
                    .' ('.$lineasTotales.' líneas pendientes).',
                $totalRq > $rows->count()
                    ? 'Mostrando las '.$rows->count().' con más líneas pendientes.'
                    : ($totalRq === 0 ? 'No hay RQ pendientes de compra.' : 'Listado completo.'),
            ],
            'tabla' => [
                'columnas' => [
                    ['key' => 'numero', 'label' => 'RQ'],
                    ['key' => 'fecha', 'label' => 'Fecha'],
                    ['key' => 'estado', 'label' => 'Estado'],
                    ['key' => 'lineas', 'label' => 'Líneas sin OC'],
                ],
                'filas' => $filas,
            ],
            'datos' => [
                'empresa_id' => $empresaId,
                'total_rq' => $totalRq,
                'lineas_pendientes' => $lineasTotales,
                'mostradas' => $rows->count(),
            ],
            'links' => self::linksCompras(),
        ];
    }

    private static function empresaId(array $params): ?int
    {
        $id = (int) ($params['empresa_id'] ?? 0);

        return $id > 0 ? $id : null;
    }

    private static function maxLineas(array $params, int $default): int
    {
        $modoExport = ! empty($params['modo_export']);
        $tope = $modoExport
            ? AiConsultaOperativaSchemaSupport::MAX_LINEAS_EXPORT
            : AiConsultaOperativaSchemaSupport::MAX_LINEAS;
        $max = (int) ($params['max_lineas'] ?? $default);

        return max(1, min($tope, $max > 0 ? $max : $default));
    }

    /** @return \Illuminate\Database\Eloquent\Builder<\App\Models\Compras\Ordencompra> */
    private static function queryOcVencidasSinRecepcion(?int $empresaId)
    {
        $q = Ordencompra::query()
            ->where('estadoordencompra', OrdencompraEstados::APROBADA)
            ->whereNotNull('fechaentrega')
            ->whereDate('fechaentrega', '<', Carbon::today()->toDateString())
            ->whereNotExists(function ($sub) {
                $sub->select(DB::raw(1))
                    ->from((new Recepcion_Proveedor)->getTable())
                    ->whereColumn('recepcion_proveedor.ordencompra_id', 'ordencompra.id');
            });
        if ($empresaId) {
            $q->where('empresa_id', $empresaId);
        }

        return $q;
    }

    private static function contarRqConLineasPendientesOc(?int $empresaId): int
    {
        $etiquetaCerrada = RequisicionLineasOcSupport::etiquetaLineaCerradaSinOc();
        $base = DB::table('requisicion as r')
            ->join('requisicion_articulo as ra', 'ra.requisicion_id', '=', 'r.id')
            ->whereIn('r.estado', self::RQ_ESTADOS_ACTIVAS)
            ->where(function ($q) use ($etiquetaCerrada) {
                $q->whereNull('ra.precio_origen_etiqueta')
                    ->orWhere('ra.precio_origen_etiqueta', '!=', $etiquetaCerrada);
            })
            ->whereNotExists(function ($q) {
                $q->select(DB::raw(1))
                    ->from('ordencompra_articulo as oa')
                    ->whereColumn('oa.requisicion_articulo_id', 'ra.id')
                    ->whereNotNull('oa.requisicion_articulo_id');
            });
        if ($empresaId) {
            $base->where('r.empresa_id', $empresaId);
        }

        return (int) DB::query()->fromSub(
            $base->select('r.id')->groupBy('r.id'),
            'x'
        )->count();
    }

    private static function contarLineasPendientesOc(?int $empresaId): int
    {
        $etiquetaCerrada = RequisicionLineasOcSupport::etiquetaLineaCerradaSinOc();
        $q = DB::table('requisicion as r')
            ->join('requisicion_articulo as ra', 'ra.requisicion_id', '=', 'r.id')
            ->whereIn('r.estado', self::RQ_ESTADOS_ACTIVAS)
            ->where(function ($w) use ($etiquetaCerrada) {
                $w->whereNull('ra.precio_origen_etiqueta')
                    ->orWhere('ra.precio_origen_etiqueta', '!=', $etiquetaCerrada);
            })
            ->whereNotExists(function ($sub) {
                $sub->select(DB::raw(1))
                    ->from('ordencompra_articulo as oa')
                    ->whereColumn('oa.requisicion_articulo_id', 'ra.id')
                    ->whereNotNull('oa.requisicion_articulo_id');
            });
        if ($empresaId) {
            $q->where('r.empresa_id', $empresaId);
        }

        return (int) $q->count('ra.id');
    }

    /**
     * @return array{
     *   muestra: int,
     *   promedio_dias: float,
     *   mediana_dias: float,
     *   min_dias: int,
     *   max_dias: int,
     *   muestra_filas?: list<array<string,mixed>>
     * }
     */
    private static function leadTimeStats(string $desde, string $hasta, ?int $empresaId, bool $conMuestra = false): array
    {
        $primera = DB::table('recepcion_proveedor')
            ->select('ordencompra_id', DB::raw('MIN(fecha) as primera'))
            ->whereNotNull('ordencompra_id')
            ->whereNotNull('fecha')
            ->groupBy('ordencompra_id');

        $q = DB::table('ordencompra as o')
            ->joinSub($primera, 'rp', 'rp.ordencompra_id', '=', 'o.id')
            ->leftJoin('proveedor as p', 'p.id', '=', 'o.proveedor_id')
            ->whereBetween('rp.primera', [$desde, $hasta])
            ->whereNotNull('o.fecha');
        if ($empresaId) {
            $q->where('o.empresa_id', $empresaId);
        }

        $rows = $q->select([
            'o.id',
            'o.numeroordencompra',
            'o.fecha',
            'rp.primera',
            'p.codigo as proveedor_codigo',
            'p.nombre as proveedor_nombre',
        ])
            ->orderByDesc('rp.primera')
            ->limit(5000)
            ->get();

        $diasList = [];
        $muestraFilas = [];
        foreach ($rows as $row) {
            $oc = Carbon::parse((string) $row->fecha)->startOfDay();
            $rp = Carbon::parse((string) $row->primera)->startOfDay();
            $dias = (int) $oc->diffInDays($rp, false);
            if ($dias < 0) {
                continue;
            }
            $diasList[] = $dias;
            if ($conMuestra && count($muestraFilas) < 20) {
                $muestraFilas[] = [
                    'numero' => $row->numeroordencompra ?? $row->id,
                    'fecha_oc' => $oc->format('d/m/Y'),
                    'primera_recepcion' => $rp->format('d/m/Y'),
                    'dias' => $dias,
                    'proveedor' => trim(($row->proveedor_codigo ?? '').' '.($row->proveedor_nombre ?? '')) ?: '—',
                ];
            }
        }

        $n = count($diasList);
        if ($n === 0) {
            return [
                'muestra' => 0,
                'promedio_dias' => 0.0,
                'mediana_dias' => 0.0,
                'min_dias' => 0,
                'max_dias' => 0,
                'muestra_filas' => [],
            ];
        }

        sort($diasList);
        $mid = intdiv($n, 2);
        $mediana = $n % 2 === 1
            ? (float) $diasList[$mid]
            : (((float) $diasList[$mid - 1] + (float) $diasList[$mid]) / 2.0);

        $out = [
            'muestra' => $n,
            'promedio_dias' => array_sum($diasList) / $n,
            'mediana_dias' => $mediana,
            'min_dias' => (int) $diasList[0],
            'max_dias' => (int) $diasList[$n - 1],
        ];
        if ($conMuestra) {
            $out['muestra_filas'] = $muestraFilas;
        }

        return $out;
    }

    /**
     * @return list<array{proveedor_id: int, codigo: string, nombre: string, docs: int, monto: float}>
     */
    private static function topProveedoresFilas(string $desde, string $hasta, ?int $empresaId, int $limit): array
    {
        $q = DB::table('comprobante_proveedor as cp')
            ->join('proveedor as p', 'p.id', '=', 'cp.proveedor_id')
            ->whereBetween('cp.fechacomprobante', [$desde, $hasta])
            ->whereNotIn('cp.estado', [
                ComprobanteProveedorEstados::ANULADO,
                ComprobanteProveedorEstados::PRECARGA,
            ])
            ->whereNotNull('cp.proveedor_id')
            ->where('cp.total', '>', 0)
            ->groupBy('cp.proveedor_id', 'p.codigo', 'p.nombre')
            ->orderByDesc(DB::raw('SUM(cp.total)'))
            ->limit($limit)
            ->select([
                'cp.proveedor_id',
                'p.codigo',
                'p.nombre',
                DB::raw('COUNT(*) as docs'),
                DB::raw('COALESCE(SUM(cp.total), 0) as monto'),
            ]);
        if ($empresaId) {
            $q->where('cp.empresa_id', $empresaId);
        }

        $out = [];
        foreach ($q->get() as $row) {
            $out[] = [
                'proveedor_id' => (int) $row->proveedor_id,
                'codigo' => (string) ($row->codigo ?? ''),
                'nombre' => (string) ($row->nombre ?? ''),
                'docs' => (int) ($row->docs ?? 0),
                'monto' => (float) ($row->monto ?? 0),
            ];
        }

        return $out;
    }

    /** @return list<array{etiqueta: string, url: string}> */
    private static function linksCompras(): array
    {
        $links = [];
        try {
            if (can('listar-ordencompra', false) && \Illuminate\Support\Facades\Route::has('consultar_ordencompra')) {
                $links[] = ['etiqueta' => 'Órdenes de compra', 'url' => route('consultar_ordencompra')];
            }
        } catch (\Throwable) {
        }
        try {
            if (can('listar-requisicion', false) && \Illuminate\Support\Facades\Route::has('consultar_requisicion')) {
                $links[] = ['etiqueta' => 'Requisiciones', 'url' => route('consultar_requisicion')];
            }
        } catch (\Throwable) {
        }

        return $links;
    }
}
