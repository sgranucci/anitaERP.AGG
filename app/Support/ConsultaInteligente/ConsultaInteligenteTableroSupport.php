<?php

declare(strict_types=1);

namespace App\Support\ConsultaInteligente;

use App\Models\Caja\Cheque;
use App\Models\Direccion\ConsultaInteligenteTableroWidget;
use App\Support\Caja\ChequeListadoFiltros;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Tablero de Consulta Inteligente: widgets personales + compartidos (empresa opcional).
 */
final class ConsultaInteligenteTableroSupport
{
    public const MAX_WIDGETS = 6;

    /**
     * @return list<array{id: string, tipo: string, titulo: string, entidad: string, query: array<string, mixed>, color: string, propio: bool, compartido: bool}>
     */
    public static function widgets(?int $usuarioId = null): array
    {
        $usuarioId = $usuarioId ?? (int) auth()->id();
        if ($usuarioId < 1) {
            return [];
        }

        if (! self::tablaLista()) {
            return array_map(
                static fn (array $w) => $w + ['propio' => true, 'compartido' => false],
                self::widgetsDesdeCache($usuarioId)
            );
        }

        self::migrarCacheSiVacio($usuarioId);

        $propios = ConsultaInteligenteTableroWidget::query()
            ->where('usuario_id', $usuarioId)
            ->orderBy('orden')
            ->orderBy('id')
            ->get();

        $idsPropios = $propios->pluck('id')->all();
        $compartidos = collect();
        if (self::tieneColumnaCompartido()) {
            $empresas = self::empresasUsuarioSesion();
            $qComp = ConsultaInteligenteTableroWidget::query()
                ->where('compartido', true)
                ->where('usuario_id', '!=', $usuarioId)
                ->when($idsPropios !== [], static fn ($q) => $q->whereNotIn('id', $idsPropios))
                ->orderBy('orden')
                ->orderBy('id');
            if ($empresas !== []) {
                $qComp->where(function ($q) use ($empresas) {
                    $q->whereNull('compartido_empresa_id')
                        ->orWhereIn('compartido_empresa_id', $empresas);
                });
            }
            $compartidos = $qComp->get();
        }

        $out = [];
        foreach ($propios->concat($compartidos) as $w) {
            /** @var ConsultaInteligenteTableroWidget $w */
            $entidad = (string) $w->entidad;
            $color = '#2471A3';
            if (EntidadSemanticaCatalogo::existe($entidad)) {
                $color = EntidadSemanticaCatalogo::porClave($entidad)->color();
            }
            $out[] = [
                'id' => (string) $w->id,
                'tipo' => (string) ($w->tipo ?: 'atajo'),
                'titulo' => (string) $w->titulo,
                'entidad' => $entidad,
                'query' => is_array($w->query_json) ? $w->query_json : [],
                'color' => $color,
                'propio' => (int) $w->usuario_id === $usuarioId,
                'compartido' => (bool) ($w->compartido ?? false),
            ];
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $query
     */
    public static function pin(
        string $entidad,
        string $titulo,
        array $query,
        string $tipo = 'atajo',
        ?int $usuarioId = null,
        bool $compartir = false,
        ?int $compartidoEmpresaId = null
    ): bool {
        $usuarioId = $usuarioId ?? (int) auth()->id();
        if ($usuarioId < 1 || ! EntidadSemanticaCatalogo::existe($entidad)) {
            return false;
        }
        $propios = array_values(array_filter(
            self::widgets($usuarioId),
            static fn (array $w) => ! empty($w['propio'])
        ));
        if (count($propios) >= self::MAX_WIDGETS) {
            return false;
        }
        $ent = EntidadSemanticaCatalogo::porClave($entidad);
        $tituloFinal = mb_substr(trim($titulo) !== '' ? trim($titulo) : $ent->nombre(), 0, 80);

        if (! self::tablaLista()) {
            return self::pinEnCache($usuarioId, $entidad, $tituloFinal, $query, $tipo, $ent->color());
        }

        $orden = (int) (ConsultaInteligenteTableroWidget::query()
            ->where('usuario_id', $usuarioId)
            ->max('orden') ?? 0) + 1;

        $payload = [
            'usuario_id' => $usuarioId,
            'entidad' => $entidad,
            'titulo' => $tituloFinal,
            'tipo' => $tipo,
            'query_json' => $query,
            'orden' => $orden,
        ];
        if (self::tieneColumnaCompartido()) {
            $empresaId = $compartidoEmpresaId !== null && $compartidoEmpresaId > 0
                ? $compartidoEmpresaId
                : null;
            if ($empresaId !== null && ! app(\App\Repositories\Configuracion\EmpresaRepositoryInterface::class)->empresaIdPermitida($empresaId)) {
                $empresaId = null;
            }
            $payload['compartido'] = $compartir;
            $payload['compartido_empresa_id'] = $compartir ? $empresaId : null;
        }

        ConsultaInteligenteTableroWidget::query()->create($payload);

        return true;
    }

    public static function unpin(string $widgetId, ?int $usuarioId = null): bool
    {
        $usuarioId = $usuarioId ?? (int) auth()->id();
        if ($usuarioId < 1 || $widgetId === '') {
            return false;
        }

        if (! self::tablaLista()) {
            $lista = array_values(array_filter(
                self::widgetsDesdeCache($usuarioId),
                static fn (array $w) => ($w['id'] ?? '') !== $widgetId
            ));
            Cache::forever(self::claveCache($usuarioId), $lista);

            return true;
        }

        // Solo el dueño puede quitar (propios o compartidos que publicó).
        $widget = ConsultaInteligenteTableroWidget::query()
            ->where('usuario_id', $usuarioId)
            ->where('id', (int) $widgetId)
            ->first();
        if (! $widget) {
            return false;
        }
        $widget->delete();

        return true;
    }

    private static function tieneColumnaCompartido(): bool
    {
        try {
            return Schema::hasColumn('consulta_inteligente_tablero_widget', 'compartido');
        } catch (\Throwable) {
            return false;
        }
    }

    /** @return list<int> */
    private static function empresasUsuarioSesion(): array
    {
        try {
            return array_map(
                'intval',
                app(\App\Repositories\Configuracion\EmpresaRepositoryInterface::class)->traeEmpresasAsignadas()
            );
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * @return array{
     *   widgets: list<array<string, mixed>>,
     *   series: list<array<string, mixed>>,
     *   kpis: list<array{titulo: string, valor: string, url: string, color: string}>
     * }
     */
    public static function armar(?int $usuarioId = null): array
    {
        $widgets = self::widgets($usuarioId);
        $enriquecidos = [];
        foreach ($widgets as $w) {
            $clave = (string) ($w['entidad'] ?? '');
            if ($clave === '' || ! EntidadSemanticaCatalogo::existe($clave)) {
                continue;
            }
            $ent = EntidadSemanticaCatalogo::porClave($clave);
            if (! can($ent->permisoListar(), false)) {
                continue;
            }
            $query = is_array($w['query'] ?? null) ? $w['query'] : [];
            $query['consultar'] = 1;
            $w['url'] = EntidadSemanticaCatalogo::urlExplorar($clave, $query);
            $w['icono'] = $ent->icono();
            $w['modulo'] = $ent->modulo();
            $w['propio'] = (bool) ($w['propio'] ?? true);
            $w['compartido'] = (bool) ($w['compartido'] ?? false);
            $enriquecidos[] = $w;
        }

        $kpis = self::kpisDisponibles();
        $series = self::seriesDisponibles();
        foreach ($enriquecidos as $w) {
            $tipo = (string) ($w['tipo'] ?? 'atajo');
            if ($tipo === 'kpi') {
                $kpi = self::kpiDesdeWidget($w);
                if ($kpi !== null) {
                    $kpi['widget_id'] = ! empty($w['propio']) ? ($w['id'] ?? null) : null;
                    $kpi['compartido'] = ! empty($w['compartido']);
                    $kpi['propio'] = ! empty($w['propio']);
                    $kpis[] = $kpi;
                }
                continue;
            }
            if ($tipo === 'aging') {
                $serie = self::serieAgingDesdeWidget($w);
                if ($serie !== null) {
                    $serie['widget_id'] = ! empty($w['propio']) ? ($w['id'] ?? null) : null;
                    $serie['compartido'] = ! empty($w['compartido']);
                    $serie['propio'] = ! empty($w['propio']);
                    $series[] = $serie;
                }
                continue;
            }
            if (in_array($tipo, ['barras', 'linea', 'torta'], true)) {
                $serie = self::serieDesdeWidget($w);
                if ($serie !== null) {
                    $serie['widget_id'] = ! empty($w['propio']) ? ($w['id'] ?? null) : null;
                    $serie['compartido'] = ! empty($w['compartido']);
                    $serie['propio'] = ! empty($w['propio']);
                    $series[] = $serie;
                }
            }
        }

        return [
            'widgets' => array_values(array_filter(
                $enriquecidos,
                static fn (array $w) => ($w['tipo'] ?? 'atajo') === 'atajo'
            )),
            'series' => $series,
            'kpis' => $kpis,
        ];
    }

    /**
     * @param  array<string, mixed>  $w
     * @return array{titulo: string, valor: string, url: string, color: string, delta?: string, delta_dir?: string}|null
     */
    private static function kpiDesdeWidget(array $w): ?array
    {
        $clave = (string) ($w['entidad'] ?? '');
        if ($clave === '' || ! EntidadSemanticaCatalogo::existe($clave)) {
            return null;
        }
        $ent = EntidadSemanticaCatalogo::porClave($clave);
        if (! can($ent->permisoListar(), false)) {
            return null;
        }
        $query = is_array($w['query'] ?? null) ? $w['query'] : [];
        $query['consultar'] = 1;
        $metrica = (string) ($query['_ci_metrica'] ?? 'conteo');
        try {
            if ($metrica === 'suma' && self::campoMedidaSuma($clave) !== null) {
                $suma = ConsultaInteligenteConsultaMetricasSupport::sumar($clave, $query);
                $total = ConsultaInteligenteConsultaMetricasSupport::contar($clave, $query);
                $delta = ConsultaInteligenteConsultaMetricasSupport::deltaVsMesAnterior($clave, $query, $total);
                $out = [
                    'titulo' => (string) ($w['titulo'] ?? $ent->nombre()),
                    'valor' => $suma !== null
                        ? ConsultaInteligenteConsultaMetricasSupport::fmtImporte($suma)
                        : '0',
                    'url' => EntidadSemanticaCatalogo::urlExplorar($clave, $query),
                    'color' => (string) ($w['color'] ?? $ent->color()),
                ];
            } elseif ($metrica === 'promedio' && self::campoMedidaSuma($clave) !== null) {
                $suma = ConsultaInteligenteConsultaMetricasSupport::sumar($clave, $query);
                $total = ConsultaInteligenteConsultaMetricasSupport::contar($clave, $query);
                $delta = ConsultaInteligenteConsultaMetricasSupport::deltaVsMesAnterior($clave, $query, $total);
                $prom = ($suma !== null && $total > 0) ? ($suma / $total) : 0.0;
                $out = [
                    'titulo' => (string) ($w['titulo'] ?? $ent->nombre()),
                    'valor' => ConsultaInteligenteConsultaMetricasSupport::fmtImporte($prom),
                    'url' => EntidadSemanticaCatalogo::urlExplorar($clave, $query),
                    'color' => (string) ($w['color'] ?? $ent->color()),
                ];
            } else {
                $total = ConsultaInteligenteConsultaMetricasSupport::contar($clave, $query);
                $delta = ConsultaInteligenteConsultaMetricasSupport::deltaVsMesAnterior($clave, $query, $total);
                $out = [
                    'titulo' => (string) ($w['titulo'] ?? $ent->nombre()),
                    'valor' => (string) $total,
                    'url' => EntidadSemanticaCatalogo::urlExplorar($clave, $query),
                    'color' => (string) ($w['color'] ?? $ent->color()),
                ];
            }
        } catch (\Throwable) {
            return null;
        }
        if ($delta !== null) {
            $out['delta'] = $delta['texto'];
            $out['delta_dir'] = $delta['dir'];
        }
        try {
            $conteoNarr = isset($total) ? (int) $total : null;
            $sumaNarr = ($metrica === 'suma' || $metrica === 'promedio') ? ($suma ?? null) : null;
            $out['narrativa'] = ConsultaInteligenteConsultaMetricasSupport::narrativaCorta(
                $clave,
                $query,
                $conteoNarr,
                is_float($sumaNarr) || is_int($sumaNarr) ? (float) $sumaNarr : null
            );
        } catch (\Throwable) {
            $out['narrativa'] = '';
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $w
     * @return array{titulo: string, tipo: string, labels: list<string>, valores: list<float>, color: string, urls?: list<string>}|null
     */
    private static function serieAgingDesdeWidget(array $w): ?array
    {
        $clave = (string) ($w['entidad'] ?? '');
        if (! ConsultaInteligenteConsultaMetricasSupport::soportaAging($clave)) {
            return null;
        }
        if (! EntidadSemanticaCatalogo::existe($clave) || ! can(EntidadSemanticaCatalogo::porClave($clave)->permisoListar(), false)) {
            return null;
        }
        $query = is_array($w['query'] ?? null) ? $w['query'] : [];
        $query['consultar'] = 1;
        try {
            $aging = ConsultaInteligenteConsultaMetricasSupport::aging($clave, $query);
        } catch (\Throwable) {
            return null;
        }
        if ($aging === null || ($aging['total'] ?? 0) < 1) {
            return null;
        }
        $labels = [];
        $valores = [];
        $urls = [];
        foreach ($aging['buckets'] as $b) {
            if (($b['n'] ?? 0) < 1) {
                continue;
            }
            $labels[] = (string) $b['label'];
            $valores[] = (float) $b['n'];
            $urls[] = (string) ($b['url'] ?? EntidadSemanticaCatalogo::urlExplorar($clave, $query));
        }
        if ($labels === []) {
            return null;
        }

        return [
            'titulo' => (string) (($w['titulo'] ?? 'Aging').' · aging'),
            'tipo' => 'torta',
            'labels' => $labels,
            'valores' => $valores,
            'color' => (string) ($w['color'] ?? '#1A5276'),
            'urls' => $urls,
        ];
    }

    /**
     * @param  array<string, mixed>  $w
     * @return array{titulo: string, tipo: string, labels: list<string>, valores: list<float>, color: string}|null
     */
    private static function serieDesdeWidget(array $w): ?array
    {
        $tipo = (string) ($w['tipo'] ?? '');
        $clave = (string) ($w['entidad'] ?? '');
        if (! in_array($tipo, ['barras', 'linea', 'torta'], true) || $clave === '') {
            return null;
        }
        if (! EntidadSemanticaCatalogo::existe($clave)) {
            return null;
        }
        $ent = EntidadSemanticaCatalogo::porClave($clave);
        if (! can($ent->permisoListar(), false)) {
            return null;
        }

        $query = is_array($w['query'] ?? null) ? $w['query'] : [];
        $query['consultar'] = 1;
        $titulo = (string) ($w['titulo'] ?? $ent->nombre());
        $color = (string) ($w['color'] ?? $ent->color());

        if ($tipo === 'torta') {
            return self::tortaDesdeFiltros($ent, $clave, $titulo, $color, $query);
        }

        $campoFecha = self::campoFechaQbe($clave);
        if ($campoFecha === null) {
            return null;
        }

        try {
            $request = \Illuminate\Http\Request::create('/', 'GET', $query);
            $filtrosBase = $ent->resolverFiltros($request);
        } catch (\Throwable) {
            return null;
        }

        $medida = (string) ($query['_ci_medida'] ?? 'conteo');
        $campoSuma = self::campoMedidaSuma($clave);
        $usarSuma = $medida === 'suma' && $campoSuma !== null;
        if ($usarSuma) {
            $titulo = $titulo.' · suma '.$campoSuma;
        }

        $labels = [];
        $valores = [];
        $urls = [];
        for ($i = 5; $i >= 0; $i--) {
            $m = Carbon::now()->subMonthsNoOverflow($i);
            $labels[] = $m->format('m/Y');
            $desde = $m->copy()->startOfMonth()->toDateString();
            $hasta = $m->copy()->endOfMonth()->toDateString();
            $filtrosMes = self::conRangoFecha(
                $filtrosBase,
                $campoFecha,
                $desde,
                $hasta
            );
            try {
                if ($usarSuma) {
                    $filas = $ent->consultar($filtrosMes, false);
                    $suma = 0.0;
                    foreach ($filas as $row) {
                        $suma += (float) ($row->{$campoSuma} ?? 0);
                    }
                    $valores[] = $suma;
                } else {
                    $filas = $ent->consultar($filtrosMes, true);
                    $valores[] = (float) ($filas instanceof \Illuminate\Contracts\Pagination\LengthAwarePaginator
                        ? $filas->total()
                        : (is_countable($filas) ? count($filas) : 0));
                }
            } catch (\Throwable) {
                $valores[] = 0.0;
            }
            $urls[] = self::urlDrillMes($clave, $campoFecha, $desde, $hasta, $query);
        }

        return [
            'titulo' => $titulo,
            'tipo' => $tipo,
            'labels' => $labels,
            'valores' => $valores,
            'urls' => $urls,
            'color' => $color,
            'medida' => $usarSuma ? 'suma' : 'conteo',
        ];
    }

    /**
     * @param  array<string, mixed>  $queryBase
     */
    private static function urlDrillMes(
        string $clave,
        string $campoFecha,
        string $desde,
        string $hasta,
        array $queryBase = []
    ): string {
        $query = $queryBase;
        $query['consultar'] = 1;
        $query['filtro_modo'] = 'qbe';
        $query['qbe'] = [
            'entre_grupos' => 'and',
            'grupos' => [[
                'logic' => 'and',
                'not' => 0,
                'criterios' => [[
                    'campo' => $campoFecha,
                    'op' => 'entre',
                    'valor' => $desde,
                    'valor_hasta' => $hasta,
                ]],
            ]],
        ];

        return EntidadSemanticaCatalogo::urlExplorar($clave, $query);
    }

    /**
     * @param  array<string, mixed>  $query
     * @return array{titulo: string, tipo: string, labels: list<string>, valores: list<float>, color: string}|null
     */
    private static function tortaDesdeFiltros(
        EntidadSemantica $ent,
        string $clave,
        string $titulo,
        string $color,
        array $query
    ): ?array {
        try {
            $request = \Illuminate\Http\Request::create('/', 'GET', $query);
            $filtros = $ent->resolverFiltros($request);
            $filas = ConsultaInteligenteDatasetSupport::consultar($ent, $filtros, false);
        } catch (\Throwable) {
            return null;
        }

        if ($clave === 'cheque') {
            $conteo = [];
            foreach ($filas as $row) {
                $estado = (string) ($row->estado ?? 'Sin estado');
                $conteo[$estado] = ($conteo[$estado] ?? 0) + 1;
            }
            arsort($conteo);
            $conteo = array_slice($conteo, 0, 8, true);
            if ($conteo === []) {
                return null;
            }

            $labels = array_map('strval', array_keys($conteo));
            $urls = [];
            foreach ($labels as $estado) {
                $urls[] = EntidadSemanticaCatalogo::urlExplorar('cheque', [
                    'consultar' => 1,
                    'filtro_modo' => 'qbe',
                    'qbe' => [
                        'entre_grupos' => 'and',
                        'grupos' => [[
                            'logic' => 'and',
                            'not' => 0,
                            'criterios' => [[
                                'campo' => 'estado',
                                'op' => 'igual',
                                'valor' => $estado,
                                'valor_hasta' => '',
                            ]],
                        ]],
                    ],
                ]);
            }

            return [
                'titulo' => $titulo,
                'tipo' => 'torta',
                'labels' => $labels,
                'valores' => array_map(static fn ($c) => (float) $c, array_values($conteo)),
                'urls' => $urls,
                'color' => $color,
            ];
        }

        $campoFecha = self::campoFechaQbe($clave);
        if ($campoFecha === null) {
            return null;
        }
        $conteo = [];
        foreach ($filas as $row) {
            $raw = $row->{$campoFecha} ?? null;
            if ($raw === null || $raw === '') {
                continue;
            }
            try {
                $label = Carbon::parse((string) $raw)->format('m/Y');
            } catch (\Throwable) {
                continue;
            }
            $conteo[$label] = ($conteo[$label] ?? 0) + 1;
        }
        ksort($conteo);
        $conteo = array_slice($conteo, -6, 6, true);
        if ($conteo === []) {
            return null;
        }

        return [
            'titulo' => $titulo,
            'tipo' => 'torta',
            'labels' => array_map('strval', array_keys($conteo)),
            'valores' => array_map(static fn ($c) => (float) $c, array_values($conteo)),
            'color' => $color,
        ];
    }

    /** True si barras/línea/torta pueden armarse por mes sobre esta entidad. */
    public static function soportaGraficoTemporal(string $clave): bool
    {
        return self::campoFechaQbe($clave) !== null;
    }

    /**
     * Atributo de fila para medida «suma» en gráficos (null = solo conteo).
     */
    public static function campoMedidaSuma(string $clave): ?string
    {
        return match ($clave) {
            'cheque', 'cobranza', 'pagoproveedor', 'ingresoegreso' => 'monto',
            'factura', 'comprobante_proveedor' => 'total',
            'solicitudpago' => 'importe',
            default => null,
        };
    }

    /**
     * Campo fecha del QBE / atributo de fila por entidad.
     */
    private static function campoFechaQbe(string $clave): ?string
    {
        return match ($clave) {
            'cheque' => 'fechapago',
            'comprobante_proveedor' => 'fechacomprobante',
            'pagoproveedor', 'ingresoegreso', 'cobranza', 'ordencompra', 'requisicion', 'solicitudpago', 'ticket', 'recepcion_proveedor', 'asiento', 'remito', 'factura', 'pedido', 'movimientostock', 'transferencia_mercaderia', 'capex' => 'fecha',
            'importacion' => 'fecha_apertura',
            'empleado_sueldos' => 'fecha_ingreso',
            default => null,
        };
    }

    /**
     * Agrega un grupo QBE AND con rango de fechas (sin pisar criterios del pin).
     *
     * @param  array<string, mixed>  $filtros
     * @return array<string, mixed>
     */
    private static function conRangoFecha(array $filtros, string $campo, string $desde, string $hasta): array
    {
        $qbe = is_array($filtros['qbe'] ?? null) ? $filtros['qbe'] : [];
        $grupos = is_array($qbe['grupos'] ?? null) ? $qbe['grupos'] : [];
        $grupos[] = [
            'logic' => 'and',
            'not' => 0,
            'criterios' => [[
                'campo' => $campo,
                'op' => 'entre',
                'valor' => $desde,
                'valor_hasta' => $hasta,
            ]],
        ];
        $qbe['grupos'] = $grupos;
        $qbe['entre_grupos'] = $qbe['entre_grupos'] ?? 'and';
        $filtros['qbe'] = $qbe;
        $filtros['filtro_modo'] = 'qbe';

        return $filtros;
    }

    private static function tablaLista(): bool
    {
        try {
            return Schema::hasTable('consulta_inteligente_tablero_widget');
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Si la BD está vacía y había widgets en cache (Fase 4), los importa una vez.
     */
    private static function migrarCacheSiVacio(int $usuarioId): void
    {
        $hay = ConsultaInteligenteTableroWidget::query()
            ->where('usuario_id', $usuarioId)
            ->exists();
        if ($hay) {
            return;
        }
        $cache = self::widgetsDesdeCache($usuarioId);
        if ($cache === []) {
            return;
        }
        $orden = 0;
        foreach ($cache as $w) {
            $orden++;
            ConsultaInteligenteTableroWidget::query()->create([
                'usuario_id' => $usuarioId,
                'entidad' => (string) ($w['entidad'] ?? ''),
                'titulo' => mb_substr((string) ($w['titulo'] ?? 'Consulta'), 0, 80),
                'tipo' => (string) ($w['tipo'] ?? 'atajo'),
                'query_json' => is_array($w['query'] ?? null) ? $w['query'] : [],
                'orden' => $orden,
            ]);
        }
        Cache::forget(self::claveCache($usuarioId));
    }

    /**
     * @return list<array{id: string, tipo: string, titulo: string, entidad: string, query: array<string, mixed>, color: string}>
     */
    private static function widgetsDesdeCache(int $usuarioId): array
    {
        $raw = Cache::get(self::claveCache($usuarioId), []);

        return is_array($raw) ? array_values($raw) : [];
    }

    /**
     * @param  array<string, mixed>  $query
     */
    private static function pinEnCache(
        int $usuarioId,
        string $entidad,
        string $titulo,
        array $query,
        string $tipo,
        string $color
    ): bool {
        $lista = self::widgetsDesdeCache($usuarioId);
        if (count($lista) >= self::MAX_WIDGETS) {
            return false;
        }
        $lista[] = [
            'id' => uniqid('w', true),
            'tipo' => $tipo,
            'titulo' => $titulo,
            'entidad' => $entidad,
            'query' => $query,
            'color' => $color,
        ];
        Cache::forever(self::claveCache($usuarioId), $lista);

        return true;
    }

    /**
     * @return list<array{titulo: string, valor: string, url: string, color: string}>
     */
    private static function kpisDisponibles(): array
    {
        $out = [];
        if (can('listar-cheque', false)) {
            $q = Cheque::query();
            ChequeListadoFiltros::aplicar($q, [
                'para_depositar' => true,
                'para_depositar_hasta' => Carbon::today()->toDateString(),
            ]);
            $n = (int) $q->count();
            $queryCheque = ['para_depositar' => 1, 'consultar' => 1];
            $out[] = [
                'titulo' => 'Cheques para depositar',
                'valor' => (string) $n,
                'url' => EntidadSemanticaCatalogo::urlExplorar('cheque', $queryCheque),
                'color' => '#1A5276',
                'narrativa' => ConsultaInteligenteConsultaMetricasSupport::narrativaCorta('cheque', $queryCheque, $n),
            ];
        }
        if (can('listar-comprobante-proveedor', false)) {
            $inicio = Carbon::now()->startOfMonth()->toDateString();
            $fin = Carbon::now()->endOfMonth()->toDateString();
            $n = (int) DB::table('comprobante_proveedor')
                ->whereDate('fechacomprobante', '>=', $inicio)
                ->whereDate('fechacomprobante', '<=', $fin)
                ->count();
            $inicioAnt = Carbon::now()->subMonthNoOverflow()->startOfMonth()->toDateString();
            $finAnt = Carbon::now()->subMonthNoOverflow()->endOfMonth()->toDateString();
            $ant = (int) DB::table('comprobante_proveedor')
                ->whereDate('fechacomprobante', '>=', $inicioAnt)
                ->whereDate('fechacomprobante', '<=', $finAnt)
                ->count();
            $queryFac = [
                'consultar' => 1,
                'filtro_modo' => 'qbe',
                'qbe' => [
                    'entre_grupos' => 'and',
                    'grupos' => [[
                        'logic' => 'and',
                        'not' => 0,
                        'criterios' => [[
                            'campo' => 'fechacomprobante',
                            'op' => 'este_mes',
                            'valor' => '',
                            'valor_hasta' => '',
                        ]],
                    ]],
                ],
            ];
            $kpi = [
                'titulo' => 'Facturas este mes',
                'valor' => (string) $n,
                'url' => EntidadSemanticaCatalogo::urlExplorar('comprobante_proveedor', $queryFac),
                'color' => '#1A365D',
                'narrativa' => ConsultaInteligenteConsultaMetricasSupport::narrativaCorta('comprobante_proveedor', $queryFac, $n),
            ];
            if ($ant > 0) {
                $var = (($n - $ant) / $ant) * 100;
                $kpi['delta'] = sprintf('%s%.0f%% vs mes ant.', $var > 0 ? '+' : '', $var);
                $kpi['delta_dir'] = $var > 0.5 ? 'up' : ($var < -0.5 ? 'down' : 'flat');
            }
            $out[] = $kpi;
        }
        if (can('listar-pagoproveedor', false)) {
            $inicio = Carbon::now()->startOfMonth()->toDateString();
            $fin = Carbon::now()->endOfMonth()->toDateString();
            $n = (int) DB::table('pagoproveedor')
                ->whereDate('fecha', '>=', $inicio)
                ->whereDate('fecha', '<=', $fin)
                ->count();
            $inicioAnt = Carbon::now()->subMonthNoOverflow()->startOfMonth()->toDateString();
            $finAnt = Carbon::now()->subMonthNoOverflow()->endOfMonth()->toDateString();
            $ant = (int) DB::table('pagoproveedor')
                ->whereDate('fecha', '>=', $inicioAnt)
                ->whereDate('fecha', '<=', $finAnt)
                ->count();
            $queryPagos = [
                'consultar' => 1,
                'filtro_modo' => 'qbe',
                'qbe' => [
                    'entre_grupos' => 'and',
                    'grupos' => [[
                        'logic' => 'and',
                        'not' => 0,
                        'criterios' => [[
                            'campo' => 'fecha',
                            'op' => 'este_mes',
                            'valor' => '',
                            'valor_hasta' => '',
                        ]],
                    ]],
                ],
            ];
            $kpi = [
                'titulo' => 'Pagos este mes',
                'valor' => (string) $n,
                'url' => EntidadSemanticaCatalogo::urlExplorar('pagoproveedor', $queryPagos),
                'color' => '#117A65',
                'narrativa' => ConsultaInteligenteConsultaMetricasSupport::narrativaCorta('pagoproveedor', $queryPagos, $n),
            ];
            if ($ant > 0) {
                $var = (($n - $ant) / $ant) * 100;
                $kpi['delta'] = sprintf('%s%.0f%% vs mes ant.', $var > 0 ? '+' : '', $var);
                $kpi['delta_dir'] = $var > 0.5 ? 'up' : ($var < -0.5 ? 'down' : 'flat');
            }
            $out[] = $kpi;
        }

        return $out;
    }

    /**
     * @return list<array{titulo: string, tipo: string, labels: list<string>, valores: list<float>, color: string}>
     */
    private static function seriesDisponibles(): array
    {
        $out = [];
        if (can('listar-comprobante-proveedor', false)) {
            $labels = [];
            $valores = [];
            $urls = [];
            for ($i = 5; $i >= 0; $i--) {
                $m = Carbon::now()->subMonthsNoOverflow($i);
                $labels[] = $m->format('m/Y');
                $desde = $m->copy()->startOfMonth()->toDateString();
                $hasta = $m->copy()->endOfMonth()->toDateString();
                $valores[] = (float) DB::table('comprobante_proveedor')
                    ->whereDate('fechacomprobante', '>=', $desde)
                    ->whereDate('fechacomprobante', '<=', $hasta)
                    ->count();
                $urls[] = self::urlDrillMes('comprobante_proveedor', 'fechacomprobante', $desde, $hasta);
            }
            $out[] = [
                'titulo' => 'Facturas por mes',
                'tipo' => 'barras',
                'labels' => $labels,
                'valores' => $valores,
                'urls' => $urls,
                'color' => '#2471A3',
            ];
        }
        if (can('listar-pagoproveedor', false)) {
            $labels = [];
            $valores = [];
            $urls = [];
            for ($i = 5; $i >= 0; $i--) {
                $m = Carbon::now()->subMonthsNoOverflow($i);
                $labels[] = $m->format('m/Y');
                $desde = $m->copy()->startOfMonth()->toDateString();
                $hasta = $m->copy()->endOfMonth()->toDateString();
                $valores[] = (float) DB::table('pagoproveedor')
                    ->whereDate('fecha', '>=', $desde)
                    ->whereDate('fecha', '<=', $hasta)
                    ->count();
                $urls[] = self::urlDrillMes('pagoproveedor', 'fecha', $desde, $hasta);
            }
            $out[] = [
                'titulo' => 'Pagos por mes',
                'tipo' => 'linea',
                'labels' => $labels,
                'valores' => $valores,
                'urls' => $urls,
                'color' => '#117A65',
            ];
        }
        if (can('listar-cheque', false)) {
            $rows = Cheque::query()
                ->selectRaw('estado, COUNT(*) as cant')
                ->groupBy('estado')
                ->orderByDesc('cant')
                ->limit(8)
                ->get();
            if ($rows->isNotEmpty()) {
                $labels = $rows->pluck('estado')->map(static fn ($e) => (string) $e)->all();
                $urls = [];
                foreach ($labels as $estado) {
                    $urls[] = EntidadSemanticaCatalogo::urlExplorar('cheque', [
                        'consultar' => 1,
                        'filtro_modo' => 'qbe',
                        'qbe' => [
                            'entre_grupos' => 'and',
                            'grupos' => [[
                                'logic' => 'and',
                                'not' => 0,
                                'criterios' => [[
                                    'campo' => 'estado',
                                    'op' => 'igual',
                                    'valor' => $estado,
                                    'valor_hasta' => '',
                                ]],
                            ]],
                        ],
                    ]);
                }
                $out[] = [
                    'titulo' => 'Cheques por estado',
                    'tipo' => 'torta',
                    'labels' => $labels,
                    'valores' => $rows->pluck('cant')->map(static fn ($c) => (float) $c)->all(),
                    'urls' => $urls,
                    'color' => '#1A5276',
                ];
            }
        }

        return $out;
    }

    private static function claveCache(int $usuarioId): string
    {
        return generaKey('ci-tablero-widgets-u'.$usuarioId);
    }
}
