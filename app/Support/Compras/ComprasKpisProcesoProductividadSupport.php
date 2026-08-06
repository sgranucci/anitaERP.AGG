<?php

namespace App\Support\Compras;

use App\Models\Compras\Ordencompra;
use App\Models\Stock\Recepcion_Proveedor;
use App\Support\Ai\AiConsultaOperativaSchemaSupport;
use App\Support\Ai\AiResolucionMaestrosSupport;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

/**
 * KPIs de proceso y productividad de Compras (tablero + panel IA).
 *
 * Definiciones:
 * - Ciclo de compra: RQ APROBADA → OC emitida (1ª APROBADA en historia OC, o fecha OC).
 * - Gestión de OC: carga (1ª PENDIENTE / created_at) → emisión (1ª APROBADA).
 * - Circuito hasta COM: RQ APROBADA → 1ª recepción de proveedor (COM).
 * - % OC abiertas: OC con saldo pendiente de recepción / total no suspendidas.
 * - Productividad: volumen de OC y ahorro ($ / %) por comprador
 *   (solo usuarios con roles Enc-compras / Op-Compras; configurable).
 */
final class ComprasKpisProcesoProductividadSupport
{
    public const PERMISO = 'listar-kpi-compras';

    public const META_CICLO_DIAS = 2.0;

    public const META_GESTION_OC_DIAS = 2.0;

    public const META_PCT_OC_ABIERTAS = 10.0;

    /** @var list<string> */
    public const ROLES_COMPRADOR_DEFAULT = ['Enc-compras', 'Op-Compras'];

    /** ID sintético para OC importadas desde Anita (no es un usuario real). */
    public const USUARIO_ID_ANITA = 0;

    public const NOMBRE_COMPRADOR_ANITA = 'ANITA';

    /**
     * @param  array<string,mixed>  $params
     * @return array{ok: bool, parrafos: list<string>, tabla?: array, datos: array<string,mixed>, links?: list<array>, error?: string}
     */
    public static function proceso(array $params): array
    {
        $empresaId = self::empresaId($params);
        $desde = (string) ($params['fecha_desde'] ?? date('Y-m-d', strtotime('-89 days')));
        $hasta = (string) ($params['fecha_hasta'] ?? date('Y-m-d'));

        $ciclo = self::statsCicloRqOc($desde, $hasta, $empresaId);
        $gestion = self::statsGestionOc($desde, $hasta, $empresaId);
        $circuito = self::statsCircuitoHastaCom($desde, $hasta, $empresaId);
        $abiertas = self::statsOcAbiertas($empresaId);

        $metaCiclo = (float) config('compras.kpis.meta_ciclo_dias', self::META_CICLO_DIAS);
        $metaGestion = (float) config('compras.kpis.meta_gestion_oc_dias', self::META_GESTION_OC_DIAS);
        $metaPct = (float) config('compras.kpis.meta_pct_oc_abiertas', self::META_PCT_OC_ABIERTAS);

        $parrafos = [
            'KPIs de proceso de Compras (ERP). Período: '
                .date('d/m/Y', strtotime($desde)).' → '.date('d/m/Y', strtotime($hasta)).'.',
            self::textoLead(
                'Ciclo de compra (RQ aprobada → OC emitida)',
                $ciclo,
                $metaCiclo
            ),
            self::textoLead(
                'Gestión de OC (carga → emisión)',
                $gestion,
                $metaGestion
            ),
            self::textoLead(
                'Circuito de compras hasta COM (RQ aprobada → 1ª recepción)',
                $circuito,
                null
            ),
            'OC abiertas (saldo pendiente de recepción): '
                .number_format($abiertas['abiertas'], 0, ',', '.')
                .' de '.number_format($abiertas['total'], 0, ',', '.')
                .' ('.self::fmtNum($abiertas['porcentaje'], 1).'%). Meta: < '
                .self::fmtNum($metaPct, 0).'%. '
                .self::etiquetaMeta($abiertas['porcentaje'], $metaPct, true).'.',
        ];
        if ($empresaId) {
            $parrafos[] = 'Filtro empresa_id='.$empresaId.'.';
        }

        $tabla = [
            'columnas' => [
                ['key' => 'kpi', 'label' => 'KPI'],
                ['key' => 'valor', 'label' => 'Valor'],
                ['key' => 'muestra', 'label' => 'Muestra'],
                ['key' => 'meta', 'label' => 'Meta'],
                ['key' => 'resultado', 'label' => 'Resultado'],
            ],
            'filas' => [
                [
                    'kpi' => 'Ciclo RQ aprobada → OC emitida (días)',
                    'valor' => $ciclo['muestra'] > 0 ? self::fmtNum($ciclo['promedio_dias'], 1) : '—',
                    'muestra' => (string) $ciclo['muestra'],
                    'meta' => '< '.self::fmtNum($metaCiclo, 0).' días',
                    'resultado' => $ciclo['muestra'] > 0
                        ? self::etiquetaMeta($ciclo['promedio_dias'], $metaCiclo, true)
                        : 'Sin datos',
                ],
                [
                    'kpi' => 'Gestión OC carga → emisión (días)',
                    'valor' => $gestion['muestra'] > 0 ? self::fmtNum($gestion['promedio_dias'], 1) : '—',
                    'muestra' => (string) $gestion['muestra'],
                    'meta' => '< '.self::fmtNum($metaGestion, 0).' días',
                    'resultado' => $gestion['muestra'] > 0
                        ? self::etiquetaMeta($gestion['promedio_dias'], $metaGestion, true)
                        : 'Sin datos',
                ],
                [
                    'kpi' => 'Circuito hasta COM (días)',
                    'valor' => $circuito['muestra'] > 0 ? self::fmtNum($circuito['promedio_dias'], 1) : '—',
                    'muestra' => (string) $circuito['muestra'],
                    'meta' => '—',
                    'resultado' => $circuito['muestra'] > 0 ? 'Medido' : 'Sin datos',
                ],
                [
                    'kpi' => '% OC abiertas',
                    'valor' => self::fmtNum($abiertas['porcentaje'], 1).'%',
                    'muestra' => $abiertas['abiertas'].' / '.$abiertas['total'],
                    'meta' => '< '.self::fmtNum($metaPct, 0).'%',
                    'resultado' => self::etiquetaMeta($abiertas['porcentaje'], $metaPct, true),
                ],
            ],
        ];

        return [
            'ok' => true,
            'parrafos' => $parrafos,
            'tabla' => $tabla,
            'datos' => [
                'empresa_id' => $empresaId,
                'fecha_desde' => $desde,
                'fecha_hasta' => $hasta,
                'metas' => [
                    'ciclo_dias' => $metaCiclo,
                    'gestion_oc_dias' => $metaGestion,
                    'pct_oc_abiertas' => $metaPct,
                ],
                'ciclo_rq_oc' => $ciclo,
                'gestion_oc' => $gestion,
                'circuito_com' => $circuito,
                'oc_abiertas' => $abiertas,
            ],
            'links' => self::links(),
        ];
    }

    /**
     * @param  array<string,mixed>  $params
     * @return array{ok: bool, parrafos: list<string>, tabla?: array, datos: array<string,mixed>, links?: list<array>, error?: string}
     */
    public static function productividad(array $params): array
    {
        $empresaId = self::empresaId($params);
        $desde = (string) ($params['fecha_desde'] ?? date('Y-m-d', strtotime('-89 days')));
        $hasta = (string) ($params['fecha_hasta'] ?? date('Y-m-d'));
        $max = self::maxLineas($params, 40);

        $porComprador = self::ocPorComprador($desde, $hasta, $empresaId);
        $ahorro = self::ahorroPorComprador($desde, $hasta, $empresaId);
        $rolesEtiqueta = implode(', ', self::nombresRolesComprador());

        $totalOc = array_sum(array_column($porComprador, 'oc'));
        $ocAnita = 0;
        $ocReales = 0;
        $compradoresReales = 0;
        foreach ($porComprador as $row) {
            if ((int) $row['usuario_id'] === self::USUARIO_ID_ANITA) {
                $ocAnita = (int) $row['oc'];
            } else {
                $ocReales += (int) $row['oc'];
                $compradoresReales++;
            }
        }
        $promedioArea = $compradoresReales > 0 ? $ocReales / $compradoresReales : 0.0;

        $ahorroTotal = array_sum(array_column($ahorro, 'ahorro'));
        $baseTotal = array_sum(array_column($ahorro, 'base'));
        $pctAhorroArea = $baseTotal > 0.000001 ? ($ahorroTotal / $baseTotal) * 100.0 : 0.0;

        $parrafos = [
            'KPIs de productividad de Compras (ERP). Período: '
                .date('d/m/Y', strtotime($desde)).' → '.date('d/m/Y', strtotime($hasta)).'.',
            'Compradores: usuarios con rol '.$rolesEtiqueta
                .'. Las OC importadas desde Anita se agrupan en el comprador «'.self::NOMBRE_COMPRADOR_ANITA.'».',
            'OC gestionadas en el período: '.number_format($totalOc, 0, ',', '.')
                .' (ERP: '.number_format($ocReales, 0, ',', '.')
                .' + Anita: '.number_format($ocAnita, 0, ',', '.').')'
                .' — '.$compradoresReales.' comprador(es) ERP.',
            'Productividad del área (OC ERP / comprador ERP): '.self::fmtNum($promedioArea, 1)
                .' (no incluye el bucket Anita en el promedio).',
            'Ahorro generado (precio original − precio × cantidad): $ '
                .number_format($ahorroTotal, 2, ',', '.')
                .' ('.self::fmtNum($pctAhorroArea, 1).'% sobre base con precio original).',
        ];
        if ($empresaId) {
            $parrafos[] = 'Filtro empresa_id='.$empresaId.'.';
        }

        $filas = [];
        $ahorroMap = [];
        foreach ($ahorro as $row) {
            $ahorroMap[(int) $row['usuario_id']] = $row;
        }

        $mostrados = array_slice($porComprador, 0, $max);
        foreach ($mostrados as $row) {
            $uid = (int) $row['usuario_id'];
            $ah = $ahorroMap[$uid] ?? null;
            $filas[] = [
                'comprador' => (string) $row['nombre'],
                'oc' => (string) $row['oc'],
                'ahorro' => $ah
                    ? number_format((float) $ah['ahorro'], 2, ',', '.')
                    : '—',
                'pct_ahorro' => $ah && (float) $ah['base'] > 0
                    ? self::fmtNum(((float) $ah['ahorro'] / (float) $ah['base']) * 100.0, 1).'%'
                    : '—',
            ];
        }

        // Compradores con ahorro pero sin OC en el período (p. ej. ahorro en RQ)
        foreach ($ahorro as $row) {
            $uid = (int) $row['usuario_id'];
            $ya = false;
            foreach ($mostrados as $ocRow) {
                if ((int) $ocRow['usuario_id'] === $uid) {
                    $ya = true;
                    break;
                }
            }
            if ($ya) {
                continue;
            }
            if (count($filas) >= $max) {
                break;
            }
            $filas[] = [
                'comprador' => (string) $row['nombre'],
                'oc' => '0',
                'ahorro' => number_format((float) $row['ahorro'], 2, ',', '.'),
                'pct_ahorro' => (float) $row['base'] > 0
                    ? self::fmtNum(((float) $row['ahorro'] / (float) $row['base']) * 100.0, 1).'%'
                    : '—',
            ];
        }

        return [
            'ok' => true,
            'parrafos' => $parrafos,
            'tabla' => [
                'columnas' => [
                    ['key' => 'comprador', 'label' => 'Comprador'],
                    ['key' => 'oc', 'label' => 'OC gestionadas'],
                    ['key' => 'ahorro', 'label' => 'Ahorro $'],
                    ['key' => 'pct_ahorro', 'label' => 'Ahorro %'],
                ],
                'filas' => $filas,
            ],
            'datos' => [
                'empresa_id' => $empresaId,
                'fecha_desde' => $desde,
                'fecha_hasta' => $hasta,
                'total_oc' => $totalOc,
                'oc_erp' => $ocReales,
                'oc_anita' => $ocAnita,
                'compradores' => $compradoresReales,
                'productividad_area_oc_por_comprador' => $promedioArea,
                'ahorro_total' => $ahorroTotal,
                'ahorro_pct_area' => $pctAhorroArea,
                'roles_comprador' => self::nombresRolesComprador(),
                'por_comprador' => $porComprador,
                'ahorro_por_comprador' => $ahorro,
            ],
            'links' => self::links(),
        ];
    }

    /**
     * Payload unificado para el tablero HTML.
     *
     * @return array<string,mixed>
     */
    public static function tablero(?int $empresaId, string $desde, string $hasta): array
    {
        $params = [
            'fecha_desde' => $desde,
            'fecha_hasta' => $hasta,
        ];
        if ($empresaId) {
            $params['empresa_id'] = $empresaId;
        }

        $proceso = self::proceso($params);
        $productividad = self::productividad($params);

        return [
            'fecha_desde' => $desde,
            'fecha_hasta' => $hasta,
            'empresa_id' => $empresaId,
            'proceso' => $proceso['datos'] ?? [],
            'proceso_tabla' => $proceso['tabla']['filas'] ?? [],
            'productividad' => $productividad['datos'] ?? [],
            'productividad_tabla' => $productividad['tabla']['filas'] ?? [],
            'metas' => $proceso['datos']['metas'] ?? [
                'ciclo_dias' => self::META_CICLO_DIAS,
                'gestion_oc_dias' => self::META_GESTION_OC_DIAS,
                'pct_oc_abiertas' => self::META_PCT_OC_ABIERTAS,
            ],
        ];
    }

    /**
     * @return array{muestra: int, promedio_dias: float, mediana_dias: float, min_dias: int, max_dias: int, cumple_meta?: bool}
     */
    private static function statsCicloRqOc(string $desde, string $hasta, ?int $empresaId): array
    {
        $rqAprob = DB::table('requisicion_estado')
            ->select('requisicion_id', DB::raw('MIN(fecha) as fecha_aprobacion'))
            ->where('estado', 'APROBADA')
            ->groupBy('requisicion_id');

        $ocEmit = DB::table('ordencompra_estado')
            ->select('ordencompra_id', DB::raw('MIN(fecha) as fecha_emision'))
            ->where('estado', OrdencompraEstados::APROBADA)
            ->groupBy('ordencompra_id');

        $q = DB::table('ordencompra as o')
            ->joinSub($rqAprob, 'ra', 'ra.requisicion_id', '=', 'o.requisicion_id')
            ->leftJoinSub($ocEmit, 'oe', 'oe.ordencompra_id', '=', 'o.id')
            ->whereNotNull('o.requisicion_id')
            ->whereBetween('o.fecha', [$desde, $hasta])
            ->select(['o.id', 'o.fecha', 'ra.fecha_aprobacion', 'oe.fecha_emision']);
        if ($empresaId) {
            $q->where('o.empresa_id', $empresaId);
        }

        $diasList = [];
        foreach ($q->limit(8000)->get() as $row) {
            $ini = self::parseDia($row->fecha_aprobacion ?? null);
            $fin = self::parseDia($row->fecha_emision ?? $row->fecha ?? null);
            if (! $ini || ! $fin) {
                continue;
            }
            $dias = (int) $ini->diffInDays($fin, false);
            if ($dias < 0) {
                continue;
            }
            $diasList[] = $dias;
        }

        $meta = (float) config('compras.kpis.meta_ciclo_dias', self::META_CICLO_DIAS);
        $stats = self::agregarDias($diasList);
        if ($stats['muestra'] > 0) {
            $stats['cumple_meta'] = $stats['promedio_dias'] < $meta;
        }

        return $stats;
    }

    /**
     * @return array{muestra: int, promedio_dias: float, mediana_dias: float, min_dias: int, max_dias: int, cumple_meta?: bool}
     */
    private static function statsGestionOc(string $desde, string $hasta, ?int $empresaId): array
    {
        $ocEmit = DB::table('ordencompra_estado')
            ->select('ordencompra_id', DB::raw('MIN(fecha) as fecha_emision'))
            ->where('estado', OrdencompraEstados::APROBADA)
            ->groupBy('ordencompra_id');

        $ocPend = DB::table('ordencompra_estado')
            ->select('ordencompra_id', DB::raw('MIN(fecha) as fecha_carga'))
            ->where('estado', OrdencompraEstados::PENDIENTE)
            ->groupBy('ordencompra_id');

        $q = DB::table('ordencompra as o')
            ->joinSub($ocEmit, 'oe', 'oe.ordencompra_id', '=', 'o.id')
            ->leftJoinSub($ocPend, 'op', 'op.ordencompra_id', '=', 'o.id')
            ->whereBetween('oe.fecha_emision', [$desde.' 00:00:00', $hasta.' 23:59:59'])
            ->select([
                'o.id',
                'o.fecha',
                'o.created_at',
                'op.fecha_carga',
                'oe.fecha_emision',
            ]);
        if ($empresaId) {
            $q->where('o.empresa_id', $empresaId);
        }

        $diasList = [];
        foreach ($q->limit(8000)->get() as $row) {
            $iniRaw = $row->fecha_carga ?? $row->created_at ?? $row->fecha;
            $ini = self::parseDia($iniRaw);
            $fin = self::parseDia($row->fecha_emision ?? null);
            if (! $ini || ! $fin) {
                continue;
            }
            $dias = (int) $ini->diffInDays($fin, false);
            if ($dias < 0) {
                continue;
            }
            $diasList[] = $dias;
        }

        $meta = (float) config('compras.kpis.meta_gestion_oc_dias', self::META_GESTION_OC_DIAS);
        $stats = self::agregarDias($diasList);
        if ($stats['muestra'] > 0) {
            $stats['cumple_meta'] = $stats['promedio_dias'] < $meta;
        }

        return $stats;
    }

    /**
     * @return array{muestra: int, promedio_dias: float, mediana_dias: float, min_dias: int, max_dias: int}
     */
    private static function statsCircuitoHastaCom(string $desde, string $hasta, ?int $empresaId): array
    {
        $rqAprob = DB::table('requisicion_estado')
            ->select('requisicion_id', DB::raw('MIN(fecha) as fecha_aprobacion'))
            ->where('estado', 'APROBADA')
            ->groupBy('requisicion_id');

        $primera = DB::table('recepcion_proveedor')
            ->select('ordencompra_id', DB::raw('MIN(fecha) as primera'))
            ->whereNotNull('ordencompra_id')
            ->whereNotNull('fecha')
            ->groupBy('ordencompra_id');

        $q = DB::table('ordencompra as o')
            ->joinSub($rqAprob, 'ra', 'ra.requisicion_id', '=', 'o.requisicion_id')
            ->joinSub($primera, 'rp', 'rp.ordencompra_id', '=', 'o.id')
            ->whereNotNull('o.requisicion_id')
            ->whereBetween('rp.primera', [$desde, $hasta])
            ->select(['o.id', 'ra.fecha_aprobacion', 'rp.primera']);
        if ($empresaId) {
            $q->where('o.empresa_id', $empresaId);
        }

        $diasList = [];
        foreach ($q->limit(8000)->get() as $row) {
            $ini = self::parseDia($row->fecha_aprobacion ?? null);
            $fin = self::parseDia($row->primera ?? null);
            if (! $ini || ! $fin) {
                continue;
            }
            $dias = (int) $ini->diffInDays($fin, false);
            if ($dias < 0) {
                continue;
            }
            $diasList[] = $dias;
        }

        return self::agregarDias($diasList);
    }

    /**
     * @return array{abiertas: int, total: int, porcentaje: float, cumple_meta: bool}
     */
    private static function statsOcAbiertas(?int $empresaId): array
    {
        $recibidoSub = DB::table('recepcion_proveedor as rp')
            ->join('recepcion_proveedor_articulo as rpa', 'rpa.recepcion_proveedor_id', '=', 'rp.id')
            ->whereNotNull('rpa.ordencompra_articulo_id')
            ->groupBy('rpa.ordencompra_articulo_id')
            ->select('rpa.ordencompra_articulo_id as linea_id')
            ->selectRaw(
                'SUM(CASE '
                .'WHEN rp.tipo = ? THEN rpa.cantidad + COALESCE(rpa.cantidad_rechazada, 0) '
                .'WHEN rp.tipo = ? THEN -(rpa.cantidad + COALESCE(rpa.cantidad_rechazada, 0)) '
                .'ELSE 0 END) as cantidad_recibida',
                [
                    Recepcion_Proveedor::TIPO_RECEPCION,
                    Recepcion_Proveedor::TIPO_DEVOLUCION,
                ]
            );

        $abiertasQ = DB::table('ordencompra as oc')
            ->join('ordencompra_articulo as oa', 'oa.ordencompra_id', '=', 'oc.id')
            ->where(function ($q) {
                $q->whereNull('oa.estado_linea_oc')
                    ->orWhere('oa.estado_linea_oc', '!=', OrdencompraLineaEstados::CERRADA);
            })
            ->leftJoinSub($recibidoSub, 'rec', 'rec.linea_id', '=', 'oa.id')
            ->whereIn('oc.estadoordencompra', [
                OrdencompraEstados::APROBADA,
                OrdencompraEstados::CUMPLIDA,
            ])
            ->when($empresaId !== null && $empresaId > 0, fn ($q) => $q->where('oc.empresa_id', $empresaId))
            ->groupBy('oc.id')
            ->havingRaw('SUM(oa.cantidad) > COALESCE(SUM(rec.cantidad_recibida), 0) + 0.000001')
            ->select('oc.id');

        $abiertas = (int) DB::query()->fromSub($abiertasQ, 'x')->count();

        $totalQ = Ordencompra::query()
            ->where('estadoordencompra', '!=', OrdencompraEstados::SUSPENDIDA);
        if ($empresaId) {
            $totalQ->where('empresa_id', $empresaId);
        }
        $total = (int) $totalQ->count();

        $pct = $total > 0 ? ($abiertas / $total) * 100.0 : 0.0;
        $meta = (float) config('compras.kpis.meta_pct_oc_abiertas', self::META_PCT_OC_ABIERTAS);

        return [
            'abiertas' => $abiertas,
            'total' => $total,
            'porcentaje' => $pct,
            'cumple_meta' => $pct < $meta,
        ];
    }

    /**
     * OC de compradores ERP (roles Enc/Op-Compras, sin import Anita)
     * + bucket sintético «ANITA» para OC importadas desde Anita.
     *
     * @return list<array{usuario_id: int, nombre: string, oc: int}>
     */
    private static function ocPorComprador(string $desde, string $hasta, ?int $empresaId): array
    {
        $usuarioIds = self::idsUsuariosComprador();
        $out = [];

        if ($usuarioIds !== []) {
            $q = DB::table('ordencompra as o')
                ->leftJoin('usuario as u', 'u.id', '=', 'o.creousuario_id')
                ->whereBetween('o.fecha', [$desde, $hasta])
                ->whereIn('o.creousuario_id', $usuarioIds)
                ->where(function ($w) {
                    self::aplicarWhereNoImportadaAnita($w, 'o.comentario');
                })
                ->groupBy('o.creousuario_id', 'u.nombre')
                ->orderByDesc(DB::raw('COUNT(*)'))
                ->select([
                    'o.creousuario_id as usuario_id',
                    'u.nombre',
                    DB::raw('COUNT(*) as oc'),
                ]);
            if ($empresaId) {
                $q->where('o.empresa_id', $empresaId);
            }

            foreach ($q->get() as $row) {
                $uid = (int) $row->usuario_id;
                $nombre = trim((string) ($row->nombre ?? ''));
                $out[] = [
                    'usuario_id' => $uid,
                    'nombre' => $nombre !== '' ? $nombre : ('Usuario #'.$uid),
                    'oc' => (int) ($row->oc ?? 0),
                ];
            }
        }

        $qAnita = DB::table('ordencompra as o')
            ->whereBetween('o.fecha', [$desde, $hasta])
            ->where(function ($w) {
                self::aplicarWhereImportadaAnita($w, 'o.comentario');
            });
        if ($empresaId) {
            $qAnita->where('o.empresa_id', $empresaId);
        }
        $ocAnita = (int) $qAnita->count();
        if ($ocAnita > 0) {
            // Bucket Anita al inicio para que se vea en el tablero
            array_unshift($out, [
                'usuario_id' => self::USUARIO_ID_ANITA,
                'nombre' => self::NOMBRE_COMPRADOR_ANITA,
                'oc' => $ocAnita,
            ]);
        }

        return $out;
    }

    /** OC importada desde Anita (comentario armado por CabeceraFieldMapper). */
    private static function aplicarWhereImportadaAnita($query, string $columnaComentario): void
    {
        $query->where(function ($w) use ($columnaComentario) {
            $w->where($columnaComentario, 'like', '%Fecha ingreso Anita%')
                ->orWhere($columnaComentario, 'like', '%Importada desde Anita%')
                ->orWhere($columnaComentario, 'like', '%Usuario ini:%');
        });
    }

    private static function aplicarWhereNoImportadaAnita($query, string $columnaComentario): void
    {
        $query->where(function ($w) use ($columnaComentario) {
            $w->whereNull($columnaComentario)
                ->orWhere(function ($w2) use ($columnaComentario) {
                    $w2->where($columnaComentario, 'not like', '%Fecha ingreso Anita%')
                        ->where($columnaComentario, 'not like', '%Importada desde Anita%')
                        ->where($columnaComentario, 'not like', '%Usuario ini:%');
                });
        });
    }

    /**
     * Ahorro en líneas de RQ, atribuido al creador de la OC vinculada (requisicion_id)
     * si es rol de compras; si no hay OC, al creador de la RQ solo si también es comprador.
     *
     * @return list<array{usuario_id: int, nombre: string, ahorro: float, base: float, lineas: int}>
     */
    private static function ahorroPorComprador(string $desde, string $hasta, ?int $empresaId): array
    {
        $usuarioIds = self::idsUsuariosComprador();
        if ($usuarioIds === []) {
            return [];
        }

        // Una OC representativa por RQ (la de menor id) para no duplicar líneas
        $ocPorRq = DB::table('ordencompra')
            ->whereNotNull('requisicion_id')
            ->whereNotNull('creousuario_id')
            ->groupBy('requisicion_id')
            ->select([
                'requisicion_id',
                DB::raw('MIN(id) as ordencompra_id'),
            ]);

        $q = DB::table('requisicion_articulo as ra')
            ->join('requisicion as r', 'r.id', '=', 'ra.requisicion_id')
            ->leftJoinSub($ocPorRq, 'ocr', 'ocr.requisicion_id', '=', 'r.id')
            ->leftJoin('ordencompra as o', 'o.id', '=', 'ocr.ordencompra_id')
            ->leftJoin('usuario as u_oc', 'u_oc.id', '=', 'o.creousuario_id')
            ->leftJoin('usuario as u_rq', 'u_rq.id', '=', 'r.creousuario_id')
            ->whereBetween('r.fecha', [$desde, $hasta])
            ->where('ra.preciooriginal', '>', 0)
            ->whereColumn('ra.preciooriginal', '>', 'ra.precio')
            ->where(function ($w) use ($usuarioIds) {
                $w->whereIn('o.creousuario_id', $usuarioIds)
                    ->orWhere(function ($w2) use ($usuarioIds) {
                        $w2->whereNull('o.id')
                            ->whereIn('r.creousuario_id', $usuarioIds);
                    });
            })
            ->select([
                DB::raw('CASE WHEN o.creousuario_id IS NOT NULL THEN o.creousuario_id ELSE r.creousuario_id END as usuario_id'),
                DB::raw('CASE WHEN o.creousuario_id IS NOT NULL THEN u_oc.nombre ELSE u_rq.nombre END as nombre'),
                DB::raw('SUM((ra.preciooriginal - ra.precio) * ra.cantidad) as ahorro'),
                DB::raw('SUM(ra.preciooriginal * ra.cantidad) as base'),
                DB::raw('COUNT(ra.id) as lineas'),
            ])
            ->groupBy(
                DB::raw('CASE WHEN o.creousuario_id IS NOT NULL THEN o.creousuario_id ELSE r.creousuario_id END'),
                DB::raw('CASE WHEN o.creousuario_id IS NOT NULL THEN u_oc.nombre ELSE u_rq.nombre END')
            )
            ->orderByDesc(DB::raw('SUM((ra.preciooriginal - ra.precio) * ra.cantidad)'));
        if ($empresaId) {
            $q->where('r.empresa_id', $empresaId);
        }

        $out = [];
        foreach ($q->get() as $row) {
            $uid = (int) ($row->usuario_id ?? 0);
            if ($uid <= 0 || ! in_array($uid, $usuarioIds, true)) {
                continue;
            }
            $nombre = trim((string) ($row->nombre ?? ''));
            $out[] = [
                'usuario_id' => $uid,
                'nombre' => $nombre !== '' ? $nombre : ('Usuario #'.$uid),
                'ahorro' => (float) ($row->ahorro ?? 0),
                'base' => (float) ($row->base ?? 0),
                'lineas' => (int) ($row->lineas ?? 0),
            ];
        }

        return $out;
    }

    /** @return list<string> */
    public static function nombresRolesComprador(): array
    {
        $cfg = config('compras.kpis.roles_comprador', self::ROLES_COMPRADOR_DEFAULT);
        if (! is_array($cfg) || $cfg === []) {
            return self::ROLES_COMPRADOR_DEFAULT;
        }

        $out = [];
        foreach ($cfg as $nombre) {
            $nombre = trim((string) $nombre);
            if ($nombre !== '') {
                $out[] = $nombre;
            }
        }

        return $out !== [] ? array_values(array_unique($out)) : self::ROLES_COMPRADOR_DEFAULT;
    }

    /** @return list<int> */
    private static function idsUsuariosComprador(): array
    {
        $nombres = self::nombresRolesComprador();
        $rolIds = DB::table('rol')->whereIn('nombre', $nombres)->pluck('id')->map(fn ($id) => (int) $id)->all();
        if ($rolIds === []) {
            return [];
        }

        return DB::table('usuario_rol')
            ->whereIn('rol_id', $rolIds)
            ->distinct()
            ->pluck('usuario_id')
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->values()
            ->all();
    }

    /**
     * @param  list<int>  $diasList
     * @return array{muestra: int, promedio_dias: float, mediana_dias: float, min_dias: int, max_dias: int}
     */
    private static function agregarDias(array $diasList): array
    {
        $n = count($diasList);
        if ($n === 0) {
            return [
                'muestra' => 0,
                'promedio_dias' => 0.0,
                'mediana_dias' => 0.0,
                'min_dias' => 0,
                'max_dias' => 0,
            ];
        }

        sort($diasList);
        $mid = intdiv($n, 2);
        $mediana = $n % 2 === 1
            ? (float) $diasList[$mid]
            : (((float) $diasList[$mid - 1] + (float) $diasList[$mid]) / 2.0);

        return [
            'muestra' => $n,
            'promedio_dias' => array_sum($diasList) / $n,
            'mediana_dias' => $mediana,
            'min_dias' => (int) $diasList[0],
            'max_dias' => (int) $diasList[$n - 1],
        ];
    }

    private static function parseDia(mixed $valor): ?Carbon
    {
        if ($valor === null || $valor === '') {
            return null;
        }
        try {
            return Carbon::parse((string) $valor)->startOfDay();
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @param  array{muestra: int, promedio_dias: float, mediana_dias?: float}  $stats
     */
    private static function textoLead(string $titulo, array $stats, ?float $meta): string
    {
        if ($stats['muestra'] === 0) {
            return $titulo.': sin muestra en el período.';
        }
        $txt = $titulo.': promedio '.self::fmtNum($stats['promedio_dias'], 1)
            .' días (mediana '.self::fmtNum((float) ($stats['mediana_dias'] ?? 0), 1)
            .', n='.$stats['muestra'].')';
        if ($meta !== null) {
            $txt .= '. Meta: < '.self::fmtNum($meta, 0).' días. '
                .self::etiquetaMeta($stats['promedio_dias'], $meta, true).'.';
        }

        return $txt;
    }

    private static function etiquetaMeta(float $valor, float $meta, bool $menorEsMejor): string
    {
        $ok = $menorEsMejor ? ($valor < $meta) : ($valor >= $meta);

        return $ok ? 'Cumple meta' : 'Fuera de meta';
    }

    private static function fmtNum(float $n, int $dec = 1): string
    {
        return number_format($n, $dec, ',', '.');
    }

    private static function empresaId(array $params): ?int
    {
        if (isset($params['empresa_id']) && (int) $params['empresa_id'] > 0) {
            return (int) $params['empresa_id'];
        }
        $res = AiResolucionMaestrosSupport::resolverEmpresa($params);
        if (! ($res['ok'] ?? false)) {
            return null;
        }
        $id = $res['empresa_id'] ?? null;

        return $id !== null && (int) $id > 0 ? (int) $id : null;
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

    /** @return list<array{etiqueta: string, url: string}> */
    private static function links(): array
    {
        $links = [];
        try {
            if (can(self::PERMISO, false) && Route::has('consultar_kpi_compras')) {
                $links[] = ['etiqueta' => 'Tablero KPIs Compras', 'url' => route('consultar_kpi_compras')];
            }
        } catch (\Throwable) {
        }
        try {
            if (can('listar-ordencompra', false) && Route::has('consultar_ordencompra')) {
                $links[] = ['etiqueta' => 'Órdenes de compra', 'url' => route('consultar_ordencompra')];
            }
        } catch (\Throwable) {
        }
        try {
            if (can('listar-requisicion', false) && Route::has('consultar_requisicion')) {
                $links[] = ['etiqueta' => 'Requisiciones', 'url' => route('consultar_requisicion')];
            }
        } catch (\Throwable) {
        }

        return $links;
    }
}
