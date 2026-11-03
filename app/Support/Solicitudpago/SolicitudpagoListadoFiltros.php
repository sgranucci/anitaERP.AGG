<?php

namespace App\Support\Solicitudpago;

use App\Support\Listado\CoincidenciaFlexibleTexto;
use App\Support\Listado\FiltrosListadoRequest;
use App\Support\Listado\ListadoAgrupacionSupport;
use App\Support\Listado\ListadoOrdenamientoSupport;
use App\Support\Listado\ListadoQbeFormulaSupport;
use App\Support\Listado\ListadoQbeSupport;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

final class SolicitudpagoListadoFiltros
{
    public const MODO_TODOS = 'todos';

    public const MODO_CAMPO = 'campo';

    public const MODO_QBE = 'qbe';

    /** @var array<string, string> */
    public const QBE_OPERADORES_TEXTO = [
        'contiene' => 'Contiene',
        'no_contiene' => 'No contiene',
        'empieza' => 'Empieza con',
        'termina' => 'Termina con',
        'igual' => 'Es igual a',
        'distinto' => 'Distinto de',
        'entre' => 'Entre',
        'vacio' => 'Está vacío',
    ];

    /** @var array<string, string> */
    public const QBE_OPERADORES_ENTERO = [
        'igual' => 'Es igual a',
        'mayor' => 'Mayor que',
        'mayor_igual' => 'Mayor o igual',
        'menor' => 'Menor que',
        'menor_igual' => 'Menor o igual',
        'entre' => 'Entre',
        'vacio' => 'Está vacío',
    ];

    /** @var array<string, string> */
    public const OPERADORES_FECHA = ListadoQbeSupport::OPERADORES_FECHA;

    /** @var array<string, string> */
    public const OPERADORES_DECIMAL = ListadoQbeSupport::OPERADORES_DECIMAL;

    /**
     * Estado y tratamiento van por selects dedicados del panel (no como campo de texto).
     */
    public const CAMPOS = [
        'codigo' => ['etiqueta' => 'Código', 'tipo' => 'entero', 'columna' => 'solicitudpago.codigo'],
        'detalle' => ['etiqueta' => 'Detalle', 'tipo' => 'texto', 'columna' => 'solicitudpago.detalle'],
        'beneficiario' => ['etiqueta' => 'Beneficiario', 'tipo' => 'texto', 'columna' => 'solicitudpago.beneficiario'],
        'proveedor_codigo' => ['etiqueta' => 'N° proveedor', 'tipo' => 'entero', 'relacion' => 'proveedores', 'columna' => 'codigo'],
        'sector' => ['etiqueta' => 'Sector', 'tipo' => 'texto', 'relacion' => 'sectores', 'columna' => 'nombre'],
        'forma_pago' => ['etiqueta' => 'Forma de pago', 'tipo' => 'texto', 'relacion' => 'formapagosol', 'columna' => 'nombre'],
        'fecha' => ['etiqueta' => 'Fecha', 'tipo' => 'texto', 'columna' => 'solicitudpago.fecha'],
    ];

    public const OPERADORES_TEXTO = [
        'contiene' => 'Contiene (en cualquier parte)',
        'igual' => 'Igual a',
        'empieza' => 'Empieza con',
        'termina' => 'Termina con',
    ];

    public const OPERADORES_ENTERO = [
        'igual' => 'Igual a',
        'mayor' => 'Mayor que',
        'menor' => 'Menor que',
        'mayor_igual' => 'Mayor o igual',
        'menor_igual' => 'Menor o igual',
    ];

    public const COLUMNAS_COINCIDENCIA_FLEXIBLE = [
        'solicitudpago.detalle',
        'solicitudpago.beneficiario',
        'solicitudpago.observacion',
    ];

    /**
     * @return array{
     *     campo: ?string,
     *     operador: string,
     *     valor: string,
     *     busqueda_rapida: bool,
     *     madre_hija: string,
     *     estado: string,
     *     tratamiento: string,
     *     fecha_desde: string,
     *     fecha_hasta: string,
     *     alcance: string,
     *     empresa_id: ?int,
     *     empresa_scope: string
     * }
     */
    public static function filtrosVacios(): array
    {
        return [
            'campo' => null,
            'operador' => 'contiene',
            'valor' => '',
            'busqueda_rapida' => false,
            'madre_hija' => '',
            'estado' => '',
            'tratamiento' => '',
            'fecha_desde' => '',
            'fecha_hasta' => '',
            'alcance' => SolicitudpagoVisibilidadSupport::ALCANCE_TODAS,
            'empresa_id' => null,
            'empresa_scope' => 'una',
            'modo' => self::MODO_TODOS,
            'valor_hasta' => '',
            'busqueda' => '',
            'qbe' => ListadoQbeSupport::vacio(),
            'orden' => [],
            'agrupar' => [],
            'calculadas' => [],
        ];
    }

    public static function resolverDesdeRequest(Request $request, ?string $busquedaRuta = null, ?int $empresaDefault = null): array
    {
        [$empresaId, $empresaScope] = self::resolverEmpresaExterna($request, $empresaDefault);

        $filtros = self::filtrosVacios();
        $filtros['valor'] = (string) FiltrosListadoRequest::valorBusqueda($request, $busquedaRuta);
        $filtros['campo'] = $request->input('filtro_campo') ?: null;
        $filtros['operador'] = (string) ($request->input('filtro_operador') ?: 'contiene');
        $filtros['busqueda_rapida'] = (string) $request->input('filtro_busqueda_rapida', '') === '1';
        $filtros['madre_hija'] = (string) $request->input('madre_hija', '');
        $filtros['estado'] = (string) $request->input('estado', '');
        $filtros['tratamiento'] = (string) $request->input('tratamiento', '');
        $filtros['fecha_desde'] = (string) $request->input('fecha_desde', '');
        $filtros['fecha_hasta'] = (string) $request->input('fecha_hasta', '');
        $filtros['alcance'] = self::normalizarAlcance($request->input('alcance'));
        $filtros['empresa_id'] = $empresaId;
        $filtros['empresa_scope'] = $empresaScope;

        $qbe = self::resolverQbeDesdeRequest($request);
        $modo = (string) $request->input('filtro_modo', self::MODO_TODOS);
        if (! in_array($modo, [self::MODO_TODOS, self::MODO_CAMPO, self::MODO_QBE], true)) {
            $modo = self::MODO_TODOS;
        }
        if (ListadoQbeSupport::tieneCriterios($qbe)) {
            $modo = self::MODO_QBE;
        }
        if ($filtros['busqueda_rapida']) {
            $modo = self::MODO_TODOS;
            $qbe = ListadoQbeSupport::vacio();
        }
        $filtros['modo'] = $modo;
        $filtros['qbe'] = $qbe;
        $filtros['valor_hasta'] = trim((string) $request->input('filtro_valor_hasta', ''));
        $filtros['busqueda'] = $filtros['valor'];
        $filtros['orden'] = ListadoOrdenamientoSupport::resolverDesdeRequest($request, self::camposOrdenables());
        $filtros['agrupar'] = ListadoAgrupacionSupport::resolverDesdeRequest($request, self::camposOrdenables());
        $filtros['calculadas'] = self::normalizarCalculadas($request->input('calculadas'));

        return $filtros;
    }

    /**
     * Filtro externo del index: empresa (default primera asignada) o todas (`empresa_todas=1`).
     *
     * @return array{0:?int,1:string}  [empresa_id, empresa_scope]
     */
    private static function resolverEmpresaExterna(Request $request, ?int $empresaDefault): array
    {
        if ($request->boolean('empresa_todas') || $request->input('empresa_scope') === 'todas') {
            return [null, 'todas'];
        }
        if ($request->filled('empresa_id')) {
            return [(int) $request->input('empresa_id'), 'una'];
        }
        if ($empresaDefault !== null && $empresaDefault > 0) {
            return [$empresaDefault, 'una'];
        }

        return [null, 'todas'];
    }

    public static function normalizarAlcance(mixed $valor): string
    {
        $alcance = (string) ($valor ?? SolicitudpagoVisibilidadSupport::ALCANCE_TODAS);

        return $alcance === SolicitudpagoVisibilidadSupport::ALCANCE_MI_CC
            ? SolicitudpagoVisibilidadSupport::ALCANCE_MI_CC
            : SolicitudpagoVisibilidadSupport::ALCANCE_TODAS;
    }

    public static function tieneAlcanceMiCentrocosto(array $filtros): bool
    {
        return self::normalizarAlcance($filtros['alcance'] ?? null) === SolicitudpagoVisibilidadSupport::ALCANCE_MI_CC;
    }

    public static function tieneCriteriosAplicados(array $filtros): bool
    {
        if (trim((string) ($filtros['valor'] ?? '')) !== '') {
            return true;
        }
        foreach (['madre_hija', 'estado', 'tratamiento', 'fecha_desde', 'fecha_hasta'] as $k) {
            if (trim((string) ($filtros[$k] ?? '')) !== '') {
                return true;
            }
        }
        if (ListadoQbeSupport::tieneCriterios((array) ($filtros['qbe'] ?? []))) {
            return true;
        }

        return false;
    }

    public static function paraQueryString(array $filtros): array
    {
        $out = self::paraQueryStringEmpresa($filtros);
        if (trim((string) ($filtros['valor'] ?? '')) !== '') {
            $out['filtro_valor'] = $filtros['valor'];
        }
        if (! empty($filtros['campo'])) {
            $out['filtro_campo'] = $filtros['campo'];
        }
        if (! empty($filtros['operador']) && $filtros['operador'] !== 'contiene') {
            $out['filtro_operador'] = $filtros['operador'];
        }
        if (! empty($filtros['busqueda_rapida'])) {
            $out['filtro_busqueda_rapida'] = 1;
        }
        foreach (['madre_hija', 'estado', 'tratamiento', 'fecha_desde', 'fecha_hasta'] as $k) {
            if (trim((string) ($filtros[$k] ?? '')) !== '') {
                $out[$k] = $filtros[$k];
            }
        }
        if (self::tieneAlcanceMiCentrocosto($filtros)) {
            $out['alcance'] = SolicitudpagoVisibilidadSupport::ALCANCE_MI_CC;
        }

        $modo = (string) ($filtros['modo'] ?? self::MODO_TODOS);
        if ($modo === self::MODO_QBE) {
            unset($out['filtro_valor'], $out['filtro_campo'], $out['filtro_operador'], $out['filtro_busqueda_rapida']);
            $out['filtro_modo'] = self::MODO_QBE;
            $out = array_merge($out, ListadoQbeSupport::paraQueryString(
                ListadoQbeSupport::normalizar(
                    $filtros['qbe'] ?? [],
                    self::campos(),
                    static fn (string $op, string $campo): string => self::normalizarOperador($op, $campo)
                )
            ));
        } elseif ($modo === self::MODO_CAMPO) {
            $out['filtro_modo'] = self::MODO_CAMPO;
        }

        return array_merge(
            $out,
            ListadoOrdenamientoSupport::paraQueryString(
                ListadoOrdenamientoSupport::normalizar($filtros['orden'] ?? [], self::camposOrdenables())
            ),
            ListadoAgrupacionSupport::paraQueryString(
                ListadoAgrupacionSupport::normalizar($filtros['agrupar'] ?? [], self::camposOrdenables())
            ),
            self::calculadasParaQueryString($filtros)
        );
    }

    /**
     * Solo el filtro externo de empresa (para Limpiar texto sin perder empresa).
     *
     * @return array<string, int>
     */
    public static function paraQueryStringEmpresa(array $filtros): array
    {
        if (($filtros['empresa_scope'] ?? 'una') === 'todas') {
            return ['empresa_todas' => 1];
        }
        if (! empty($filtros['empresa_id'])) {
            return ['empresa_id' => (int) $filtros['empresa_id']];
        }

        return [];
    }

    public static function aplicar($query, array $filtros)
    {
        if (! empty($filtros['empresa_id'])) {
            $query->where('solicitudpago.empresa_id', (int) $filtros['empresa_id']);
        }

        if (! self::tieneCriteriosAplicados($filtros)) {
            return $query;
        }

        if (($filtros['estado'] ?? '') !== '') {
            $query->where('solicitudpago.estado', $filtros['estado']);
        }
        if (($filtros['tratamiento'] ?? '') !== '') {
            $query->where('solicitudpago.tratamiento', $filtros['tratamiento']);
        }
        if (($filtros['fecha_desde'] ?? '') !== '') {
            $query->whereDate('solicitudpago.fecha', '>=', $filtros['fecha_desde']);
        }
        if (($filtros['fecha_hasta'] ?? '') !== '') {
            $query->whereDate('solicitudpago.fecha', '<=', $filtros['fecha_hasta']);
        }
        if (($filtros['madre_hija'] ?? '') === 'madres') {
            $query->whereNull('solicitudpago.solicitudpago_madre_id');
        } elseif (($filtros['madre_hija'] ?? '') === 'hijas') {
            $query->whereNotNull('solicitudpago.solicitudpago_madre_id');
        } elseif (($filtros['madre_hija'] ?? '') === 'madres_con_plan') {
            $query->whereNull('solicitudpago.solicitudpago_madre_id')
                ->whereHas('cuotas');
        } else        if (($filtros['madre_hija'] ?? '') === 'familia') {
            $query->where(function ($q) {
                $q->whereNotNull('solicitudpago.solicitudpago_madre_id')
                    ->orWhereHas('cuotas')
                    ->orWhereHas('hijas');
            });
        }

        if (ListadoQbeSupport::tieneCriterios((array) ($filtros['qbe'] ?? []))) {
            self::aplicarQbe($query, (array) $filtros['qbe']);
        }
        if (($filtros['modo'] ?? '') === self::MODO_QBE) {
            return $query;
        }

        $valor = trim((string) ($filtros['valor'] ?? ''));
        if ($valor === '') {
            return $query;
        }

        // Si ya filtró por el select de estado/tratamiento y el texto es solo ese enum,
        // no AND-ear búsqueda de texto (evita 0 resultados al escribir "autorizada").
        $estadoSelect = trim((string) ($filtros['estado'] ?? ''));
        $tratSelect = trim((string) ($filtros['tratamiento'] ?? ''));
        $estadosValor = self::codigosEnumQueCoinciden(SolicitudpagoEstados::opciones(), $valor);
        $tratsValor = self::codigosEnumQueCoinciden(SolicitudpagoTratamientos::opciones(), $valor);
        if ($estadoSelect !== '' && $estadosValor !== [] && $tratsValor === [] && ! self::pareceCodigoOTextoLibre($valor)) {
            return $query;
        }
        if ($tratSelect !== '' && $tratsValor !== [] && $estadosValor === [] && ! self::pareceCodigoOTextoLibre($valor)) {
            return $query;
        }

        if (! empty($filtros['busqueda_rapida']) || empty($filtros['campo'])) {
            $query->where(function ($q) use ($valor, $estadosValor, $tratsValor) {
                $q->where('solicitudpago.codigo', 'like', '%'.$valor.'%')
                    ->orWhere('solicitudpago.detalle', 'like', '%'.$valor.'%')
                    ->orWhere('solicitudpago.beneficiario', 'like', '%'.$valor.'%')
                    ->orWhere('solicitudpago.observacion', 'like', '%'.$valor.'%')
                    ->orWhereHas('proveedores', function ($pq) use ($valor) {
                        $pq->where('codigo', 'like', '%'.$valor.'%')
                            ->orWhere('nombre', 'like', '%'.$valor.'%');
                    })
                    ->orWhereHas('sectores', function ($sq) use ($valor) {
                        $sq->where('nombre', 'like', '%'.$valor.'%')
                            ->orWhere('codigo', 'like', '%'.$valor.'%');
                    })
                    ->orWhereHas('formapagosol', function ($fq) use ($valor) {
                        $fq->where('nombre', 'like', '%'.$valor.'%')
                            ->orWhere('codigo', 'like', '%'.$valor.'%');
                    });
                if ($estadosValor !== []) {
                    $q->orWhereIn('solicitudpago.estado', $estadosValor);
                }
                if ($tratsValor !== []) {
                    $q->orWhereIn('solicitudpago.tratamiento', $tratsValor);
                }
                CoincidenciaFlexibleTexto::aplicar($q, 'solicitudpago.detalle', $valor);
            });

            return $query;
        }

        $campo = self::CAMPOS[$filtros['campo']] ?? null;
        if ($campo === null) {
            // Compat URLs viejas filtro_campo=estado|tratamiento
            if (($filtros['campo'] ?? '') === 'estado' && $estadosValor !== []) {
                return $query->whereIn('solicitudpago.estado', $estadosValor);
            }
            if (($filtros['campo'] ?? '') === 'tratamiento' && $tratsValor !== []) {
                return $query->whereIn('solicitudpago.tratamiento', $tratsValor);
            }

            return $query;
        }

        $op = $filtros['operador'] ?? 'contiene';
        if (! empty($campo['relacion'])) {
            self::aplicarSobreRelacion(
                $query,
                (string) $campo['relacion'],
                (string) $campo['columna'],
                (string) ($campo['tipo'] ?? 'texto'),
                $op,
                $valor
            );

            return $query;
        }

        $col = $campo['columna'];
        if (($campo['tipo'] ?? '') === 'entero') {
            $n = (int) $valor;
            match ($op) {
                'mayor' => $query->where($col, '>', $n),
                'menor' => $query->where($col, '<', $n),
                'mayor_igual' => $query->where($col, '>=', $n),
                'menor_igual' => $query->where($col, '<=', $n),
                default => $query->where($col, $n),
            };

            return $query;
        }

        match ($op) {
            'igual' => $query->where($col, $valor),
            'empieza' => $query->where($col, 'like', $valor.'%'),
            'termina' => $query->where($col, 'like', '%'.$valor),
            default => $query->where(function ($q) use ($col, $valor) {
                $q->where($col, 'like', '%'.$valor.'%');
                if (in_array($col, self::COLUMNAS_COINCIDENCIA_FLEXIBLE, true)) {
                    CoincidenciaFlexibleTexto::aplicar($q, $col, $valor);
                }
            }),
        };

        return $query;
    }

    /**
     * @param  list<array{valor: string, nombre: string}>  $opciones
     * @return list<string>
     */
    private static function codigosEnumQueCoinciden(array $opciones, string $valor): array
    {
        $needle = mb_strtoupper(trim($valor), 'UTF-8');
        if ($needle === '') {
            return [];
        }

        $out = [];
        $len = mb_strlen($needle, 'UTF-8');
        foreach ($opciones as $opt) {
            $codigo = mb_strtoupper((string) $opt['valor'], 'UTF-8');
            $nombre = mb_strtoupper((string) $opt['nombre'], 'UTF-8');
            $igual = $codigo === $needle || $nombre === $needle;
            // Contiene solo con 4+ caracteres para no matchear "A" → casi todos
            $parcial = $len >= 4 && (str_contains($codigo, $needle) || str_contains($nombre, $needle));
            if ($igual || $parcial) {
                $out[] = (string) $opt['valor'];
            }
        }

        return array_values(array_unique($out));
    }

    /** true si el valor parece un código numérico u otro texto libre (no solo etiqueta de enum). */
    private static function pareceCodigoOTextoLibre(string $valor): bool
    {
        $v = trim($valor);

        return $v !== '' && preg_match('/\d/', $v) === 1;
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<\App\Models\Solicitudpago\Solicitudpago>  $query
     */
    private static function aplicarSobreRelacion($query, string $relacion, string $columna, string $tipo, string $op, string $valor): void
    {
        $query->whereHas($relacion, function ($q) use ($columna, $tipo, $op, $valor) {
            if ($tipo === 'entero') {
                $n = (int) $valor;
                match ($op) {
                    'mayor' => $q->where($columna, '>', $n),
                    'menor' => $q->where($columna, '<', $n),
                    'mayor_igual' => $q->where($columna, '>=', $n),
                    'menor_igual' => $q->where($columna, '<=', $n),
                    default => $q->where($columna, $n),
                };

                return;
            }

            match ($op) {
                'igual' => $q->where($columna, $valor),
                'empieza' => $q->where($columna, 'like', $valor.'%'),
                'termina' => $q->where($columna, 'like', '%'.$valor),
                default => $q->where(function ($inner) use ($columna, $valor) {
                    $inner->where($columna, 'like', '%'.$valor.'%');
                    if ($columna === 'nombre') {
                        CoincidenciaFlexibleTexto::aplicar($inner, $columna, $valor);
                    }
                }),
            };
        });
    }

    public static function operadoresParaCampo(string $campo): array
    {
        $meta = self::CAMPOS[$campo] ?? null;
        if (($meta['tipo'] ?? '') === 'entero') {
            return self::OPERADORES_ENTERO;
        }

        return self::OPERADORES_TEXTO;
    }

    /**
     * @return array<string, array{column: string, type: string, label: string}>
     */
    public static function campos(): array
    {
        $out = [];
        foreach (SolicitudpagoListadoColumnas::camposFiltrables() as $key => $meta) {
            $out[$key] = [
                'column' => $meta['column'],
                'type' => $meta['type'],
                'label' => $meta['label'],
            ];
        }

        return $out;
    }

    /**
     * @return array<string, array{label: string, type: string, column: string}>
     */
    public static function camposOrdenables(): array
    {
        return SolicitudpagoListadoColumnas::camposOrdenables();
    }

    /**
     * @return array<string, array{column: string, type: string, label: string}>
     */
    public static function camposQbeDisponibles(): array
    {
        return self::campos();
    }

    /**
     * @return array{entre_grupos: string, grupos: list}
     */
    public static function resolverQbeDesdeRequest(Request $request): array
    {
        return ListadoQbeSupport::resolverDesdeRequest(
            $request,
            self::campos(),
            static fn (string $op, string $campo): string => self::normalizarOperador($op, $campo)
        );
    }

    /**
     * @param  array<string, mixed>  $base
     * @param  array<string, mixed>  $desdeVista
     * @return array<string, mixed>
     */
    public static function fusionarDesdeVista(array $base, array $desdeVista): array
    {
        $ordenVista = ListadoOrdenamientoSupport::normalizar($desdeVista['orden'] ?? $desdeVista['sort'] ?? [], self::camposOrdenables());
        $ordenBase = ListadoOrdenamientoSupport::normalizar($base['orden'] ?? [], self::camposOrdenables());
        $agruparVista = ListadoAgrupacionSupport::normalizar($desdeVista['agrupar'] ?? $desdeVista['group'] ?? [], self::camposOrdenables());
        $agruparBase = ListadoAgrupacionSupport::normalizar($base['agrupar'] ?? [], self::camposOrdenables());
        $qbeExplicito = ! empty($base['_qbe_explicito']);
        unset($base['_qbe_explicito']);

        if (self::tieneCriteriosAplicados($base) || $qbeExplicito) {
            if ($ordenBase === [] && $ordenVista !== []) {
                $base['orden'] = $ordenVista;
            }
            if ($agruparBase === [] && $agruparVista !== []) {
                $base['agrupar'] = $agruparVista;
            }
            if (($base['calculadas'] ?? []) === [] && ! empty($desdeVista['calculadas'])) {
                $base['calculadas'] = self::normalizarCalculadas($desdeVista['calculadas']);
            }

            return $base;
        }

        $qbe = ListadoQbeSupport::normalizar(
            $desdeVista['qbe'] ?? [],
            self::campos(),
            static fn (string $op, string $campo): string => self::normalizarOperador($op, $campo)
        );
        $modo = (string) ($desdeVista['modo'] ?? self::MODO_TODOS);
        if (ListadoQbeSupport::tieneCriterios($qbe)) {
            $modo = self::MODO_QBE;
        }

        $empresaId = $base['empresa_id'] ?? null;
        $empresaScope = $base['empresa_scope'] ?? 'una';
        if ($empresaId === null && ($empresaScope ?? 'una') !== 'todas') {
            if (($desdeVista['empresa_scope'] ?? '') === 'todas') {
                $empresaId = null;
                $empresaScope = 'todas';
            } elseif (! empty($desdeVista['empresa_id'])) {
                $empresaId = (int) $desdeVista['empresa_id'];
                $empresaScope = 'una';
            }
        }

        return array_merge($base, [
            'modo' => $modo,
            'campo' => $desdeVista['campo'] ?? $base['campo'],
            'operador' => $desdeVista['operador'] ?? $base['operador'],
            'valor' => (string) ($desdeVista['valor'] ?? ''),
            'valor_hasta' => (string) ($desdeVista['valor_hasta'] ?? ''),
            'busqueda' => (string) ($desdeVista['valor'] ?? $desdeVista['busqueda'] ?? ''),
            'busqueda_rapida' => false,
            'qbe' => $qbe,
            'orden' => $ordenBase !== [] ? $ordenBase : $ordenVista,
            'agrupar' => $agruparBase !== [] ? $agruparBase : $agruparVista,
            'estado' => (string) ($desdeVista['estado'] ?? $base['estado'] ?? ''),
            'tratamiento' => (string) ($desdeVista['tratamiento'] ?? $base['tratamiento'] ?? ''),
            'madre_hija' => (string) ($desdeVista['madre_hija'] ?? $base['madre_hija'] ?? ''),
            'fecha_desde' => (string) ($desdeVista['fecha_desde'] ?? $base['fecha_desde'] ?? ''),
            'fecha_hasta' => (string) ($desdeVista['fecha_hasta'] ?? $base['fecha_hasta'] ?? ''),
            'alcance' => self::normalizarAlcance($desdeVista['alcance'] ?? $base['alcance'] ?? null),
            'empresa_id' => $empresaId,
            'empresa_scope' => $empresaScope,
            'calculadas' => self::normalizarCalculadas($desdeVista['calculadas'] ?? []),
        ]);
    }

    /**
     * @param  Builder<\App\Models\Solicitudpago\Solicitudpago>  $query
     */
    public static function aplicarOrden(Builder $query, array $filtros): void
    {
        $campos = self::camposOrdenables();
        ListadoAgrupacionSupport::aplicarOrdenPrefijo(
            $query,
            ListadoAgrupacionSupport::normalizar($filtros['agrupar'] ?? [], $campos),
            $campos
        );
        $orden = ListadoOrdenamientoSupport::normalizar($filtros['orden'] ?? [], $campos);
        ListadoOrdenamientoSupport::aplicar($query, $orden, $campos, [
            'campo' => 'fecha',
            'dir' => ListadoOrdenamientoSupport::DIR_DESC,
        ]);
        $query->orderBy('solicitudpago.codigo', 'desc');
    }

    /**
     * @param  Builder<\App\Models\Solicitudpago\Solicitudpago>  $query
     */
    public static function aplicarCalculadas(Builder $query, array $filtros): void
    {
        $campos = self::campos();
        foreach (self::normalizarCalculadas($filtros['calculadas'] ?? []) as $i => $calc) {
            if (! $calc['valida']) {
                continue;
            }
            $compiled = ListadoQbeFormulaSupport::compilar($calc['formula'], $campos);
            if ($compiled === null) {
                continue;
            }
            $query->selectRaw('('.$compiled['sql'].') as calc_'.$i, $compiled['bindings']);
        }
    }

    /**
     * @return list<array{etiqueta: string, formula: string, valida: bool}>
     */
    public static function normalizarCalculadas(mixed $raw): array
    {
        if (! is_array($raw)) {
            return [];
        }
        $campos = self::campos();
        $out = [];
        foreach (array_values($raw) as $fila) {
            if (! is_array($fila) || count($out) >= 2) {
                break;
            }
            $etiqueta = trim((string) ($fila['etiqueta'] ?? ''));
            $formula = trim((string) ($fila['formula'] ?? ''));
            if ($etiqueta === '' || $formula === '') {
                continue;
            }
            $out[] = [
                'etiqueta' => mb_substr($etiqueta, 0, 40),
                'formula' => mb_substr($formula, 0, ListadoQbeFormulaSupport::MAX_LEN),
                'valida' => ListadoQbeFormulaSupport::compilar($formula, $campos) !== null,
            ];
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return array<string, mixed>
     */
    private static function calculadasParaQueryString(array $filtros): array
    {
        $out = [];
        foreach (self::normalizarCalculadas($filtros['calculadas'] ?? []) as $i => $calc) {
            $out['calculadas'][$i] = [
                'etiqueta' => $calc['etiqueta'],
                'formula' => $calc['formula'],
            ];
        }

        return $out;
    }

    /**
     * @param  Builder<\App\Models\Solicitudpago\Solicitudpago>  $query
     * @param  array{entre_grupos?: string, grupos?: list}|list  $qbe
     */
    private static function aplicarQbe(Builder $query, array $qbe): void
    {
        $norm = ListadoQbeSupport::normalizar(
            $qbe,
            self::campos(),
            static fn (string $op, string $campo): string => self::normalizarOperador($op, $campo)
        );
        ListadoQbeSupport::aplicar(
            $query,
            $norm,
            static function (Builder $q, array $criterio, string $boolean): void {
                $formula = trim((string) ($criterio['formula'] ?? ''));
                if ($formula !== '') {
                    $compiled = ListadoQbeFormulaSupport::compilar($formula, self::campos());
                    if ($compiled === null) {
                        return;
                    }
                    ListadoQbeFormulaSupport::aplicarComparacion(
                        $q,
                        $compiled,
                        (string) ($criterio['op'] ?? 'contiene'),
                        trim((string) ($criterio['valor'] ?? '')),
                        trim((string) ($criterio['valor_hasta'] ?? '')),
                        $boolean
                    );

                    return;
                }
                self::aplicarEnCampo(
                    $q,
                    (string) $criterio['campo'],
                    (string) ($criterio['op'] ?? 'contiene'),
                    trim((string) ($criterio['valor'] ?? '')),
                    trim((string) ($criterio['valor_hasta'] ?? '')),
                    $boolean
                );
            }
        );
    }

    /**
     * @param  Builder<\App\Models\Solicitudpago\Solicitudpago>  $query
     */
    private static function aplicarEnCampo(
        Builder $query,
        string $campoKey,
        string $operador,
        string $valor,
        string $valorHasta,
        string $boolean = 'and'
    ): void {
        if ($boolean === ListadoQbeSupport::LOGIC_OR) {
            $query->orWhere(function (Builder $q) use ($campoKey, $operador, $valor, $valorHasta) {
                self::aplicarEnCampo($q, $campoKey, $operador, $valor, $valorHasta, ListadoQbeSupport::LOGIC_AND);
            });

            return;
        }

        $def = self::campos()[$campoKey] ?? null;
        if ($def === null || ! ListadoOrdenamientoSupport::esColumnaSqlSegura((string) $def['column'])) {
            return;
        }
        $column = (string) $def['column'];
        $type = (string) ($def['type'] ?? 'texto');

        if (in_array($campoKey, ['estado', 'tratamiento'], true)) {
            $opciones = $campoKey === 'estado'
                ? SolicitudpagoEstados::opciones()
                : SolicitudpagoTratamientos::opciones();
            $codigos = self::codigosEnumQueCoinciden($opciones, $valor);
            if ($operador === 'vacio') {
                $query->where(function ($q) use ($column) {
                    $q->whereNull($column)->orWhere($column, '');
                });

                return;
            }
            if ($codigos !== [] && in_array($operador, ['igual', 'contiene'], true)) {
                $query->whereIn($column, $codigos);

                return;
            }
        }

        if ($type === 'decimal') {
            ListadoQbeSupport::aplicarDecimal($query, $column, $operador, $valor, $valorHasta);

            return;
        }
        if ($type === 'fecha') {
            ListadoQbeSupport::aplicarFecha($query, $column, $operador, $valor, $valorHasta);

            return;
        }
        if ($type === 'entero') {
            if ($operador === 'vacio') {
                $query->whereNull($column);

                return;
            }
            if ($operador === 'entre') {
                if (is_numeric($valor)) {
                    $query->where($column, '>=', (int) $valor);
                }
                if (is_numeric($valorHasta)) {
                    $query->where($column, '<=', (int) $valorHasta);
                }

                return;
            }
            if (! is_numeric($valor)) {
                return;
            }
            $n = (int) $valor;
            match ($operador) {
                'mayor' => $query->where($column, '>', $n),
                'menor' => $query->where($column, '<', $n),
                'mayor_igual' => $query->where($column, '>=', $n),
                'menor_igual' => $query->where($column, '<=', $n),
                default => $query->where($column, $n),
            };

            return;
        }

        if ($operador === 'vacio') {
            $query->where(function ($q) use ($column) {
                $q->whereNull($column)->orWhere($column, '');
            });

            return;
        }
        if ($valor === '' && $operador !== 'entre') {
            return;
        }
        $like = match ($operador) {
            'empieza' => addcslashes($valor, '%_\\').'%',
            'termina' => '%'.addcslashes($valor, '%_\\'),
            'igual', 'distinto' => addcslashes($valor, '%_\\'),
            default => '%'.addcslashes($valor, '%_\\').'%',
        };
        match ($operador) {
            'igual' => $query->where($column, $valor),
            'distinto' => $query->where($column, '<>', $valor),
            'no_contiene' => $query->where($column, 'not like', $like),
            'entre' => $query->where($column, '>=', $valor)->where($column, '<=', $valorHasta),
            default => $query->where(function ($q) use ($column, $like, $valor) {
                $q->where($column, 'like', $like);
                if (in_array($column, self::COLUMNAS_COINCIDENCIA_FLEXIBLE, true) || str_ends_with($column, '.nombre')) {
                    CoincidenciaFlexibleTexto::aplicar($q, $column, $valor);
                }
            }),
        };
    }

    private static function normalizarOperador(string $operador, string $campoKey): string
    {
        $type = self::campos()[$campoKey]['type'] ?? 'texto';
        $permitidos = match ($type) {
            'entero' => array_keys(self::QBE_OPERADORES_ENTERO),
            'fecha' => array_keys(self::OPERADORES_FECHA),
            'decimal' => array_keys(self::OPERADORES_DECIMAL),
            default => array_keys(self::QBE_OPERADORES_TEXTO),
        };
        if (in_array($operador, $permitidos, true)) {
            return $operador;
        }

        return $permitidos[0] ?? 'contiene';
    }
}
