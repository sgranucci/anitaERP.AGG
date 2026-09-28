<?php

namespace App\Support\Ventas;

use App\Support\Listado\CoincidenciaFlexibleTexto;
use App\Support\Listado\FiltrosListadoRequest;
use App\Support\Listado\ListadoAgrupacionSupport;
use App\Support\Listado\ListadoOrdenamientoSupport;
use App\Support\Listado\ListadoQbeFormulaSupport;
use App\Support\Listado\ListadoQbeSupport;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * Filtros del listado de clientes Ventas (index / exportaciones).
 */
class ClienteListadoFiltros
{
    public const MODO_TODOS = 'todos';

    public const MODO_CAMPO = 'campo';

    public const MODO_QBE = 'qbe';

    /**
     * @deprecated Usar campos() — se mantiene alias para código legacy.
     * @var array<string, array{column: string, type: string, label: string}>
     */
    public const CAMPOS = [];

    /**
     * Columnas con coincidencia flexible (tolera errores de tipeo).
     *
     * @var list<string>
     */
    private const COLUMNAS_COINCIDENCIA_FLEXIBLE = [
        'cliente.nombre',
        'cliente.fantasia',
        'cliente.domicilio',
        'cliente.numerodocumento',
        'cliente.contacto',
        'cliente.email',
        'cliente.telefono',
        'cliente.leyenda',
        'localidad.nombre',
        'provincia.nombre',
        'pais.nombre',
        'transporte.nombre',
        'vendedor.nombre',
        'cobrador.nombre',
        'zonavta.nombre',
        'subzonavta.nombre',
        'condicionventa.nombre',
        'listaprecio.nombre',
        'tipoempresa_cliente.nombre',
        'condicioniva.nombre',
    ];

    /** @var array<string, string> */
    public const OPERADORES_TEXTO = [
        'contiene' => 'Contiene',
        'no_contiene' => 'No contiene',
        'empieza' => 'Empieza con',
        'termina' => 'Termina con',
        'igual' => 'Es igual a',
        'distinto' => 'Distinto de',
        'mayor' => 'Mayor (A–Z)',
        'mayor_igual' => 'Mayor o igual (A–Z)',
        'menor' => 'Menor (A–Z)',
        'menor_igual' => 'Menor o igual (A–Z)',
        'entre' => 'Entre (A–Z)',
        'vacio' => 'Está vacío',
    ];

    /** @var array<string, string> */
    public const OPERADORES_ENTERO = [
        'igual' => 'Es igual a',
        'mayor' => 'Mayor que',
        'mayor_igual' => 'Mayor o igual',
        'menor' => 'Menor que',
        'menor_igual' => 'Menor o igual',
        'entre' => 'Entre',
        'vacio' => 'Está vacío',
    ];

    /** @var array<string, string> */
    public const OPERADORES_BOOLEANO = [
        'igual' => 'Es',
        'vacio' => 'Sin dato',
    ];

    /** @var array<string, string> */
    public const OPERADORES_FECHA = ListadoQbeSupport::OPERADORES_FECHA;

    /**
     * Catálogo de campos filtrables (sincronizado con columnas del workbench).
     *
     * @return array<string, array{column: string, type: string, label: string}>
     */
    public static function campos(): array
    {
        $out = [];
        foreach (ClienteListadoColumnas::camposFiltrables() as $key => $meta) {
            $out[$key] = [
                'column' => $meta['column'],
                'type' => $meta['type'],
                'label' => $meta['label'],
            ];
        }

        return $out;
    }

    public static function resolverDesdeRequest(Request $request, ?string $busquedaRuta = null): array
    {
        if (FiltrosListadoRequest::solicitudLimpiaFiltros($request)) {
            return self::filtrosVacios();
        }

        $valor = FiltrosListadoRequest::valorBusqueda($request, $busquedaRuta);
        $busquedaRapida = $request->boolean('filtro_busqueda_rapida');

        $qbe = self::resolverQbeDesdeRequest($request);

        $modo = (string) $request->input('filtro_modo', self::MODO_TODOS);
        if (! in_array($modo, [self::MODO_TODOS, self::MODO_CAMPO, self::MODO_QBE], true)) {
            $modo = self::MODO_TODOS;
        }

        if (ListadoQbeSupport::tieneCriterios($qbe)) {
            $modo = self::MODO_QBE;
        }

        $campo = (string) $request->input('filtro_campo', 'nombre');
        $campos = self::campos();
        if (! isset($campos[$campo])) {
            $campo = 'nombre';
        }

        $operador = (string) $request->input('filtro_operador', 'contiene');

        if ($busquedaRapida) {
            $modo = self::MODO_TODOS;
            $operador = 'contiene';
            $qbe = ListadoQbeSupport::vacio();
        }

        if ($modo !== self::MODO_QBE) {
            $operador = self::normalizarOperador($operador, $modo === self::MODO_CAMPO ? $campo : 'nombre');
        } else {
            $operador = 'contiene';
        }

        return [
            'modo' => $modo,
            'campo' => $campo,
            'operador' => $operador,
            'valor' => $valor,
            'valor_hasta' => trim((string) $request->input('filtro_valor_hasta', '')),
            'codigo' => trim((string) $request->input('filtro_codigo', '')),
            'busqueda' => $valor,
            'busqueda_rapida' => $busquedaRapida,
            'qbe' => $qbe,
            'orden' => ListadoOrdenamientoSupport::resolverDesdeRequest($request, self::camposOrdenables()),
            'agrupar' => ListadoAgrupacionSupport::resolverDesdeRequest($request, self::camposOrdenables()),
        ];
    }

    /**
     * @return array<string, array{label: string, type: string, column: string}>
     */
    public static function camposOrdenables(): array
    {
        return ClienteListadoColumnas::camposOrdenables();
    }

    /**
     * Criterios Advanced Find: forma canónica con grupos AND/OR/NOT.
     * Acepta también lista plana legacy qbe[i][campo] y qbe[campo]=valor.
     *
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
     * @return array<string, array{column: string, type: string, label: string}>
     */
    public static function camposQbeDisponibles(): array
    {
        return self::campos();
    }

    public static function tieneCriteriosTexto(array $filtros): bool
    {
        if (ListadoQbeSupport::tieneCriterios((array) ($filtros['qbe'] ?? []))) {
            return true;
        }

        if (($filtros['modo'] ?? '') === self::MODO_QBE && ListadoQbeSupport::tieneCriterios((array) ($filtros['qbe'] ?? []))) {
            return true;
        }

        if (($filtros['operador'] ?? '') === 'vacio') {
            return true;
        }

        if (trim((string) ($filtros['valor'] ?? '')) !== '') {
            return true;
        }

        if (trim((string) ($filtros['valor_hasta'] ?? '')) !== '') {
            return true;
        }

        if (($filtros['modo'] ?? self::MODO_TODOS) === self::MODO_CAMPO) {
            return true;
        }

        if (($filtros['operador'] ?? 'contiene') !== 'contiene') {
            return true;
        }

        return false;
    }

    public static function tieneCriteriosAplicados(array $filtros): bool
    {
        if (trim((string) ($filtros['codigo'] ?? '')) !== '') {
            return true;
        }

        return self::tieneCriteriosTexto($filtros);
    }

    /**
     * @return array{modo: string, campo: string, operador: string, valor: string, valor_hasta: string, codigo: string, busqueda: string, qbe: array{entre_grupos: string, grupos: list}, orden: list<array{campo: string, dir: string}>}
     */
    public static function filtrosVacios(): array
    {
        return [
            'modo' => self::MODO_TODOS,
            'campo' => 'nombre',
            'operador' => 'contiene',
            'valor' => '',
            'valor_hasta' => '',
            'codigo' => '',
            'busqueda' => '',
            'qbe' => ListadoQbeSupport::vacio(),
            'orden' => [],
            'agrupar' => [],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function paraQueryString(array $filtros): array
    {
        $params = [];
        $modo = $filtros['modo'] ?? self::MODO_TODOS;

        if (trim((string) ($filtros['codigo'] ?? '')) !== '') {
            $params['filtro_codigo'] = trim((string) $filtros['codigo']);
        }

        if ($modo === self::MODO_QBE) {
            $params['filtro_modo'] = self::MODO_QBE;
            $params = array_merge($params, ListadoQbeSupport::paraQueryString(
                ListadoQbeSupport::normalizar(
                    $filtros['qbe'] ?? [],
                    self::campos(),
                    static fn (string $op, string $campo): string => self::normalizarOperador($op, $campo)
                )
            ));

            return array_merge($params, ListadoOrdenamientoSupport::paraQueryString(
                ListadoOrdenamientoSupport::normalizar($filtros['orden'] ?? [], self::camposOrdenables())
            ), ListadoAgrupacionSupport::paraQueryString(
                ListadoAgrupacionSupport::normalizar($filtros['agrupar'] ?? [], self::camposOrdenables())
            ));
        }

        if ($modo !== self::MODO_TODOS) {
            $params['filtro_modo'] = $modo;
        }
        if ($modo === self::MODO_CAMPO) {
            $params['filtro_campo'] = $filtros['campo'] ?? 'nombre';
            $params['filtro_operador'] = $filtros['operador'] ?? 'contiene';
        } elseif (($filtros['operador'] ?? 'contiene') !== 'contiene') {
            $params['filtro_operador'] = $filtros['operador'];
        }
        if (! empty($filtros['valor'])) {
            $params['filtro_valor'] = $filtros['valor'];
        }
        if (! empty($filtros['valor_hasta'])) {
            $params['filtro_valor_hasta'] = $filtros['valor_hasta'];
        }

        return array_merge($params, ListadoOrdenamientoSupport::paraQueryString(
            ListadoOrdenamientoSupport::normalizar($filtros['orden'] ?? [], self::camposOrdenables())
        ), ListadoAgrupacionSupport::paraQueryString(
            ListadoAgrupacionSupport::normalizar($filtros['agrupar'] ?? [], self::camposOrdenables())
        ));
    }

    /**
     * @param  array<string, mixed>  $base
     * @param  array<string, mixed>  $desdeVista
     * @return array<string, mixed>
     */
    public static function fusionarDesdeVista(array $base, array $desdeVista): array
    {
        $ordenVista = ListadoOrdenamientoSupport::normalizar(
            $desdeVista['orden'] ?? [],
            self::camposOrdenables()
        );
        $ordenBase = ListadoOrdenamientoSupport::normalizar(
            $base['orden'] ?? [],
            self::camposOrdenables()
        );
        $agruparVista = ListadoAgrupacionSupport::normalizar(
            $desdeVista['agrupar'] ?? $desdeVista['group'] ?? [],
            self::camposOrdenables()
        );
        $agruparBase = ListadoAgrupacionSupport::normalizar(
            $base['agrupar'] ?? [],
            self::camposOrdenables()
        );

        $qbeExplicito = ! empty($base['_qbe_explicito']);
        unset($base['_qbe_explicito']);

        if (self::tieneCriteriosAplicados($base) || $qbeExplicito) {
            if ($ordenBase === [] && $ordenVista !== []) {
                $base['orden'] = $ordenVista;
            }
            if ($agruparBase === [] && $agruparVista !== []) {
                $base['agrupar'] = $agruparVista;
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

        return array_merge($base, [
            'modo' => $modo,
            'campo' => (string) ($desdeVista['campo'] ?? $base['campo']),
            'operador' => (string) ($desdeVista['operador'] ?? $base['operador']),
            'valor' => (string) ($desdeVista['valor'] ?? ''),
            'valor_hasta' => (string) ($desdeVista['valor_hasta'] ?? ''),
            'busqueda' => (string) ($desdeVista['valor'] ?? $desdeVista['busqueda'] ?? ''),
            'codigo' => (string) ($desdeVista['codigo'] ?? $base['codigo'] ?? ''),
            'qbe' => $qbe,
            'orden' => $ordenBase !== [] ? $ordenBase : $ordenVista,
            'agrupar' => $agruparBase !== [] ? $agruparBase : $agruparVista,
        ]);
    }

    /**
     * Aplica ORDER BY: columnas de agrupación + multi-criterio (o id DESC).
     *
     * @param  Builder<\App\Models\Ventas\Cliente>  $query
     */
    public static function aplicarOrden(Builder $query, array $filtros): void
    {
        $campos = self::camposOrdenables();
        ListadoAgrupacionSupport::aplicarOrdenPrefijo(
            $query,
            ListadoAgrupacionSupport::normalizar($filtros['agrupar'] ?? [], $campos),
            $campos
        );
        ListadoOrdenamientoSupport::aplicar(
            $query,
            ListadoOrdenamientoSupport::normalizar($filtros['orden'] ?? [], $campos),
            $campos,
            ['campo' => 'id', 'dir' => ListadoOrdenamientoSupport::DIR_DESC]
        );
    }

    /**
     * @param  Builder<\App\Models\Ventas\Cliente>  $query
     */
    public static function aplicar(Builder $query, array $filtros): void
    {
        self::aplicarFiltroCodigo($query, $filtros);

        if (! self::tieneCriteriosTexto($filtros)) {
            return;
        }

        $modo = $filtros['modo'] ?? self::MODO_TODOS;

        if ($modo === self::MODO_QBE) {
            self::aplicarQbe($query, (array) ($filtros['qbe'] ?? []));

            return;
        }

        $valor = trim((string) ($filtros['valor'] ?? ''));
        $operador = $filtros['operador'] ?? 'contiene';

        if ($modo === self::MODO_CAMPO) {
            self::aplicarEnCampo($query, $filtros['campo'] ?? 'nombre', $operador, $valor, $filtros['valor_hasta'] ?? '');

            return;
        }

        self::aplicarBusquedaGlobal($query, $operador, $valor);
    }

    /**
     * Filtro dedicado de código (barra superior El Bierzo):
     * acepta el código con o sin ceros a la izquierda.
     *
     * @param  Builder<\App\Models\Ventas\Cliente>  $query
     */
    private static function aplicarFiltroCodigo(Builder $query, array $filtros): void
    {
        $codigo = trim((string) ($filtros['codigo'] ?? ''));
        if ($codigo === '') {
            return;
        }

        $variantes = self::variantesCodigo($codigo);
        if ($variantes === []) {
            return;
        }

        $query->whereIn('cliente.codigo', $variantes);
    }

    /**
     * @return list<string>
     */
    private static function variantesCodigo(string $codigo): array
    {
        $codigo = trim($codigo);
        if ($codigo === '') {
            return [];
        }
        if (! ctype_digit($codigo)) {
            return [$codigo];
        }

        $norm = ltrim($codigo, '0');
        if ($norm === '') {
            $norm = '0';
        }

        return array_values(array_unique([$codigo, $norm, str_pad($norm, 6, '0', STR_PAD_LEFT)]));
    }

    /**
     * Advanced Find: grupos AND/OR/NOT (Dynamics / NetSuite / SAP P13n).
     *
     * @param  Builder<\App\Models\Ventas\Cliente>  $query
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
     * @param  Builder<\App\Models\Ventas\Cliente>  $query
     */
    private static function aplicarBusquedaGlobal(Builder $query, string $operador, string $valor): void
    {
        if ($operador === 'vacio') {
            $query->where(function ($q) {
                foreach (['cliente.nombre', 'cliente.numerodocumento', 'cliente.domicilio', 'cliente.codigo'] as $col) {
                    $q->where(function ($w) use ($col) {
                        $w->whereNull($col)->orWhere($col, '');
                    });
                }
            });

            return;
        }

        if ($valor === '') {
            return;
        }

        $id = filter_var($valor, FILTER_VALIDATE_INT);
        $like = self::patronLike($operador, $valor);

        $query->where(function ($q) use ($valor, $like, $id, $operador) {
            if ($id !== false) {
                $q->orWhere('cliente.id', (int) $id);
            }
            $textCols = [
                'cliente.nombre',
                'cliente.fantasia',
                'cliente.numerodocumento',
                'cliente.domicilio',
                'cliente.codigo',
                'cliente.estado',
                'localidad.nombre',
                'provincia.nombre',
                'transporte.codigo',
                'transporte.nombre',
                'vendedor.codigo',
                'vendedor.nombre',
            ];
            foreach ($textCols as $col) {
                $q->orWhere($col, 'like', $like);
                if ($operador === 'contiene' && self::usaCoincidenciaFlexibleEnColumna($col)) {
                    CoincidenciaFlexibleTexto::aplicar(
                        $q,
                        $col,
                        $valor,
                        true,
                        CoincidenciaFlexibleTexto::LONGITUD_MINIMA_DEFAULT
                    );
                }
            }
            self::aplicarCuitSinSeparadores($q, $valor);
        });
    }

    /**
     * Igual que el modal: el CUIT se busca también sin guiones, puntos ni espacios.
     *
     * @param  Builder<\App\Models\Ventas\Cliente>  $q
     */
    private static function aplicarCuitSinSeparadores(Builder $q, string $valor): void
    {
        $soloDigitos = preg_replace('/\D+/', '', $valor);
        if ($soloDigitos === '' || mb_strlen($soloDigitos) < 2) {
            return;
        }

        $q->orWhereRaw(
            "REPLACE(REPLACE(REPLACE(cliente.numerodocumento, '-', ''), '.', ''), ' ', '') LIKE ?",
            ['%'.$soloDigitos.'%']
        );
    }

    private static function usaCoincidenciaFlexibleEnColumna(string $column): bool
    {
        return in_array($column, self::COLUMNAS_COINCIDENCIA_FLEXIBLE, true);
    }

    /**
     * @param  Builder<\App\Models\Ventas\Cliente>  $query
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

        $campos = self::campos();
        $def = $campos[$campoKey] ?? $campos['nombre'] ?? null;
        if ($def === null) {
            return;
        }

        $type = $def['type'];

        if ($type === 'booleano') {
            self::aplicarBooleano($query, (string) $def['column'], $operador, $valor);

            return;
        }

        if ($type === 'entero') {
            if ($operador === 'vacio') {
                $query->where(function ($q) use ($def) {
                    $q->whereNull($def['column']);
                });

                return;
            }
            self::aplicarEntero($query, (string) $def['column'], $operador, $valor, $valorHasta);

            return;
        }

        if ($type === 'fecha') {
            ListadoQbeSupport::aplicarFecha($query, (string) $def['column'], $operador, $valor, $valorHasta);

            return;
        }

        self::aplicarTexto($query, (string) $def['column'], $operador, $valor, $valorHasta);
    }

    /**
     * @param  Builder<\App\Models\Ventas\Cliente>  $query
     */
    private static function aplicarBooleano(Builder $query, string $column, string $operador, string $valor): void
    {
        if ($operador === 'vacio') {
            $query->where(function ($q) use ($column) {
                $q->whereNull($column)->orWhere($column, 0)->orWhere($column, false);
            });

            return;
        }

        $truthy = in_array(strtolower($valor), ['1', 'si', 'sí', 'true', 's', 'yes'], true);
        $query->where($column, $truthy ? 1 : 0);
    }

    /**
     * @param  Builder<\App\Models\Ventas\Cliente>  $query
     */
    private static function aplicarTexto(
        Builder $query,
        string $column,
        string $operador,
        string $valor,
        string $valorHasta = ''
    ): void {
        if ($operador === 'vacio') {
            $query->where(function ($q) use ($column) {
                $q->whereNull($column)->orWhere($column, '');
            });

            return;
        }
        if ($operador === 'entre') {
            $desde = trim($valor);
            $hasta = trim($valorHasta);
            if ($desde === '' && $hasta === '') {
                return;
            }
            if ($desde !== '') {
                $query->where($column, '>=', $desde);
            }
            if ($hasta !== '') {
                $query->where($column, '<=', $hasta);
            }

            return;
        }
        if ($valor === '') {
            return;
        }
        switch ($operador) {
            case 'empieza':
                $query->where($column, 'like', self::escapeLike($valor).'%');
                break;
            case 'termina':
                $query->where($column, 'like', '%'.self::escapeLike($valor));
                break;
            case 'igual':
                $query->where($column, '=', $valor);
                break;
            case 'distinto':
                $query->where($column, '!=', $valor);
                break;
            case 'mayor':
                $query->where($column, '>', $valor);
                break;
            case 'mayor_igual':
                $query->where($column, '>=', $valor);
                break;
            case 'menor':
                $query->where($column, '<', $valor);
                break;
            case 'menor_igual':
                $query->where($column, '<=', $valor);
                break;
            case 'no_contiene':
                $query->where(function ($q) use ($column, $valor) {
                    $like = '%'.self::escapeLike($valor).'%';
                    $q->whereNull($column)
                        ->orWhere($column, '')
                        ->orWhere($column, 'not like', $like);
                });
                break;
            case 'contiene':
            default:
                $query->where(function ($q) use ($column, $valor) {
                    $like = '%'.self::escapeLike($valor).'%';
                    $q->where($column, 'like', $like);
                    if (self::usaCoincidenciaFlexibleEnColumna($column)) {
                        CoincidenciaFlexibleTexto::aplicar(
                            $q,
                            $column,
                            $valor,
                            true,
                            CoincidenciaFlexibleTexto::LONGITUD_MINIMA_DEFAULT
                        );
                    }
                    if ($column === 'cliente.numerodocumento') {
                        self::aplicarCuitSinSeparadores($q, $valor);
                    }
                });
                break;
        }
    }

    /**
     * @param  Builder<\App\Models\Ventas\Cliente>  $query
     */
    private static function aplicarEntero(
        Builder $query,
        string $column,
        string $operador,
        string $valor,
        string $valorHasta = ''
    ): void {
        if ($operador === 'entre') {
            $desde = filter_var($valor, FILTER_VALIDATE_INT);
            $hasta = filter_var($valorHasta, FILTER_VALIDATE_INT);
            if ($desde !== false) {
                $query->where($column, '>=', (int) $desde);
            }
            if ($hasta !== false) {
                $query->where($column, '<=', (int) $hasta);
            }

            return;
        }

        $id = filter_var($valor, FILTER_VALIDATE_INT);
        if ($id === false) {
            return;
        }
        $id = (int) $id;
        switch ($operador) {
            case 'mayor':
                $query->where($column, '>', $id);
                break;
            case 'mayor_igual':
                $query->where($column, '>=', $id);
                break;
            case 'menor':
                $query->where($column, '<', $id);
                break;
            case 'menor_igual':
                $query->where($column, '<=', $id);
                break;
            case 'igual':
            default:
                $query->where($column, '=', $id);
                break;
        }
    }

    private static function patronLike(string $operador, string $valor): string
    {
        $v = self::escapeLike($valor);

        return match ($operador) {
            'empieza' => $v.'%',
            'termina' => '%'.$v,
            'igual' => $v,
            default => '%'.$v.'%',
        };
    }

    private static function escapeLike(string $value): string
    {
        return addcslashes($value, '%_\\');
    }

    private static function normalizarOperador(string $operador, string $campoKey): string
    {
        $type = self::campos()[$campoKey]['type'] ?? 'texto';
        $permitidos = match ($type) {
            'entero' => array_keys(self::OPERADORES_ENTERO),
            'booleano' => array_keys(self::OPERADORES_BOOLEANO),
            'fecha' => array_keys(self::OPERADORES_FECHA),
            default => array_keys(self::OPERADORES_TEXTO),
        };

        if (in_array($operador, $permitidos, true)) {
            return $operador;
        }

        return $permitidos[0] ?? 'contiene';
    }

    /**
     * @return array<string, string>
     */
    public static function operadoresParaCampo(string $campoKey): array
    {
        $type = self::campos()[$campoKey]['type'] ?? 'texto';

        return match ($type) {
            'entero' => self::OPERADORES_ENTERO,
            'booleano' => self::OPERADORES_BOOLEANO,
            'fecha' => self::OPERADORES_FECHA,
            default => self::OPERADORES_TEXTO,
        };
    }
}
