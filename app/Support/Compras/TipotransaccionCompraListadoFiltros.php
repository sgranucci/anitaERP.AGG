<?php

namespace App\Support\Compras;

use App\Models\Compras\Tipotransaccion_Compra;
use App\Support\Listado\CoincidenciaFlexibleTexto;
use App\Support\Listado\FiltrosListadoRequest;
use App\Support\Listado\ListadoOrdenamientoSupport;
use App\Support\Listado\ListadoQbeFormulaSupport;
use App\Support\Listado\ListadoQbeSupport;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * Filtros del listado de tipos de comprobante de compras.
 */
class TipotransaccionCompraListadoFiltros
{
    public const MODO_TODOS = 'todos';

    public const MODO_CAMPO = 'campo';

    /** @var array<string, array{column: string, type: string, label: string}> */
    public const CAMPOS = [
        'id' => ['column' => 'tipotransaccion_compra.id', 'type' => 'entero', 'label' => 'ID'],
        'nombre' => ['column' => 'tipotransaccion_compra.nombre', 'type' => 'texto', 'label' => 'Nombre'],
        'abreviatura' => ['column' => 'tipotransaccion_compra.abreviatura', 'type' => 'texto', 'label' => 'Abreviatura'],
        'codigoafip' => ['column' => 'tipotransaccion_compra.codigoafip', 'type' => 'texto', 'label' => 'Tipo AFIP'],
        'operacion' => ['column' => 'tipotransaccion_compra.operacion', 'type' => 'enum', 'label' => 'Operación'],
        'signo' => ['column' => 'tipotransaccion_compra.signo', 'type' => 'enum', 'label' => 'Signo'],
        'subdiario' => ['column' => 'tipotransaccion_compra.subdiario', 'type' => 'enum', 'label' => 'Subdiario IVA'],
        'asientocontable' => ['column' => 'tipotransaccion_compra.asientocontable', 'type' => 'enum', 'label' => 'Asiento contable'],
        'estado' => ['column' => 'tipotransaccion_compra.estado', 'type' => 'enum', 'label' => 'Estado'],
        'retieneiva' => ['column' => 'tipotransaccion_compra.retieneiva', 'type' => 'enum', 'label' => 'Retiene IVA'],
        'retieneganancia' => ['column' => 'tipotransaccion_compra.retieneganancia', 'type' => 'enum', 'label' => 'Retiene ganancias'],
        'retieneIIBB' => ['column' => 'tipotransaccion_compra.retieneIIBB', 'type' => 'enum', 'label' => 'Retiene IIBB'],
    ];

    /** @var array<string, string> */
    public const OPERADORES_TEXTO = [
        'contiene' => 'Contiene (en cualquier parte)',
        'empieza' => 'Empieza con',
        'termina' => 'Termina con',
        'igual' => 'Igual a',
        'distinto' => 'Distinto de',
        'vacio' => 'Vacío',
    ];

    /** @var array<string, string> */
    public const OPERADORES_ENTERO = [
        'igual' => 'Igual a',
        'mayor' => 'Mayor que',
        'menor' => 'Menor que',
    ];

    /** Operadores del QBE (consulta avanzada). El panel simple sigue con OPERADORES_TEXTO. */
    /** @var array<string, string> */
    public const OPERADORES_QBE_TEXTO = [
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
    public const OPERADORES_QBE_ENTERO = [
        'igual' => 'Es igual a',
        'distinto' => 'Distinto de',
        'mayor' => 'Mayor que',
        'mayor_igual' => 'Mayor o igual',
        'menor' => 'Menor que',
        'menor_igual' => 'Menor o igual',
        'entre' => 'Entre',
        'vacio' => 'Está vacío',
    ];

    /**
     * Orden visual de la grilla (clave de CAMPOS => título).
     *
     * @var array<string, string>
     */
    public const COLUMNAS_GRILLA = [
        'id' => 'ID',
        'nombre' => 'Nombre',
        'operacion' => 'Operación',
        'abreviatura' => 'Abreviatura',
        'codigoafip' => 'Tipo AFIP',
        'signo' => 'Signo',
        'subdiario' => 'Subdiario IVA',
        'asientocontable' => 'Asiento',
        'estado' => 'Estado',
        'retieneiva' => 'Retiene IVA',
        'retieneganancia' => 'Retiene ganancias',
        'retieneIIBB' => 'Retiene IIBB',
    ];

    public static function resolverDesdeRequest(Request $request, ?string $busquedaRuta = null): array
    {
        if (FiltrosListadoRequest::solicitudLimpiaFiltros($request)) {
            return self::filtrosVacios();
        }

        $valor = FiltrosListadoRequest::valorBusqueda($request, $busquedaRuta);
        $busquedaRapida = $request->boolean('filtro_busqueda_rapida');

        $modo = (string) $request->input('filtro_modo', self::MODO_TODOS);
        if (! in_array($modo, [self::MODO_TODOS, self::MODO_CAMPO], true)) {
            $modo = self::MODO_TODOS;
        }

        $campo = (string) $request->input('filtro_campo', 'nombre');
        if (! isset(self::CAMPOS[$campo])) {
            $campo = 'nombre';
        }

        $operador = (string) $request->input('filtro_operador', 'contiene');
        if ($busquedaRapida) {
            $modo = self::MODO_TODOS;
            $operador = 'contiene';
        }

        $operador = self::normalizarOperador($operador, $modo === self::MODO_CAMPO ? $campo : 'nombre');

        return [
            'modo' => $modo,
            'campo' => $campo,
            'operador' => $operador,
            'valor' => $valor,
            'valor_hasta' => '',
            'busqueda' => $valor,
            'busqueda_rapida' => $busquedaRapida,
            'qbe' => ListadoQbeSupport::resolverDesdeRequest(
                $request,
                self::CAMPOS,
                [self::class, 'normalizarOperadorQbe']
            ),
            'sort' => ListadoOrdenamientoSupport::resolverDesdeRequest($request, self::camposOrdenables()),
        ];
    }

    /**
     * @return array<string, array{column: string, type: string, label: string}>
     */
    public static function camposOrdenables(): array
    {
        return self::CAMPOS;
    }

    public static function tieneCriteriosAplicados(array $filtros): bool
    {
        if (($filtros['operador'] ?? '') === 'vacio') {
            return true;
        }

        if (trim((string) ($filtros['valor'] ?? '')) !== '') {
            return true;
        }

        if (($filtros['modo'] ?? self::MODO_TODOS) === self::MODO_CAMPO) {
            return true;
        }

        if (($filtros['operador'] ?? 'contiene') !== 'contiene') {
            return true;
        }

        $qbe = $filtros['qbe'] ?? [];

        return is_array($qbe) && ListadoQbeSupport::tieneCriterios($qbe);
    }

    /**
     * @return array{modo: string, campo: string, operador: string, valor: string, valor_hasta: string, busqueda: string, busqueda_rapida: bool, qbe: array, sort: list}
     */
    public static function filtrosVacios(): array
    {
        return [
            'modo' => self::MODO_TODOS,
            'campo' => 'nombre',
            'operador' => 'contiene',
            'valor' => '',
            'valor_hasta' => '',
            'busqueda' => '',
            'busqueda_rapida' => false,
            'qbe' => ListadoQbeSupport::vacio(),
            'sort' => [],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function paraQueryString(array $filtros): array
    {
        $params = [];
        if (($filtros['modo'] ?? self::MODO_TODOS) !== self::MODO_TODOS) {
            $params['filtro_modo'] = $filtros['modo'];
        }
        if (($filtros['modo'] ?? '') === self::MODO_CAMPO) {
            $params['filtro_campo'] = $filtros['campo'] ?? 'nombre';
            $params['filtro_operador'] = $filtros['operador'] ?? 'contiene';
        } elseif (($filtros['operador'] ?? 'contiene') !== 'contiene') {
            $params['filtro_operador'] = $filtros['operador'];
        }
        if (! empty($filtros['valor'])) {
            $params['filtro_valor'] = $filtros['valor'];
        }

        return array_merge(
            $params,
            ListadoQbeSupport::paraQueryString(is_array($filtros['qbe'] ?? null) ? $filtros['qbe'] : []),
            ListadoOrdenamientoSupport::paraQueryString(
                ListadoOrdenamientoSupport::normalizar($filtros['sort'] ?? [], self::camposOrdenables())
            )
        );
    }

    /**
     * @param  Builder<Tipotransaccion_Compra>  $query
     */
    public static function aplicar(Builder $query, array $filtros): void
    {
        self::aplicarFiltroTexto($query, $filtros);
        self::aplicarQbe($query, $filtros['qbe'] ?? []);
    }

    /**
     * @param  Builder<Tipotransaccion_Compra>  $query
     * @param  array<string, mixed>  $filtros
     */
    public static function aplicarOrden(Builder $query, array $filtros): void
    {
        $orden = ListadoOrdenamientoSupport::normalizar($filtros['sort'] ?? [], self::camposOrdenables());
        if ($orden === []) {
            $query->orderBy('tipotransaccion_compra.nombre');
            $query->orderBy('tipotransaccion_compra.id');

            return;
        }

        $ordenaPorId = false;
        foreach ($orden as $criterio) {
            $campo = (string) ($criterio['campo'] ?? '');
            if ($campo === 'id') {
                $ordenaPorId = true;
            }
            self::orderByCampo($query, $campo, (string) ($criterio['dir'] ?? ListadoOrdenamientoSupport::DIR_ASC));
        }
        if (! $ordenaPorId) {
            $query->orderBy('tipotransaccion_compra.id');
        }
    }

    /**
     * @param  Builder<Tipotransaccion_Compra>  $query
     */
    private static function aplicarFiltroTexto(Builder $query, array $filtros): void
    {
        $valor = trim((string) ($filtros['valor'] ?? ''));
        if ($valor === '' && ($filtros['operador'] ?? '') !== 'vacio') {
            return;
        }

        $modo = $filtros['modo'] ?? self::MODO_TODOS;
        $operador = $filtros['operador'] ?? 'contiene';

        if ($modo === self::MODO_CAMPO) {
            self::aplicarEnCampo($query, (string) ($filtros['campo'] ?? 'nombre'), $operador, $valor);

            return;
        }

        self::aplicarBusquedaGlobal($query, $operador, $valor);
    }

    /**
     * @param  Builder<Tipotransaccion_Compra>  $query
     * @param  array<string, mixed>|list<mixed>  $qbe
     */
    private static function aplicarQbe(Builder $query, array $qbe): void
    {
        if (! ListadoQbeSupport::tieneCriterios($qbe)) {
            return;
        }

        ListadoQbeSupport::aplicar($query, $qbe, function (Builder $q, array $criterio, string $boolean): void {
            $formula = trim((string) ($criterio['formula'] ?? ''));
            if ($formula !== '') {
                $compiled = ListadoQbeFormulaSupport::compilar($formula, self::CAMPOS);
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
            $envolver = function (Builder $inner) use ($criterio): void {
                self::aplicarCriterioQbe($inner, $criterio);
            };
            if ($boolean === ListadoQbeSupport::LOGIC_OR) {
                $q->orWhere($envolver);

                return;
            }
            $q->where($envolver);
        });
    }

    /**
     * @param  Builder<Tipotransaccion_Compra>  $query
     * @param  array{campo?: string, op?: string, valor?: string, valor_hasta?: string}  $criterio
     */
    private static function aplicarCriterioQbe(Builder $query, array $criterio): void
    {
        $campo = (string) ($criterio['campo'] ?? '');
        $def = self::CAMPOS[$campo] ?? null;
        if ($def === null) {
            return;
        }
        $operador = (string) ($criterio['op'] ?? 'contiene');
        $valor = (string) ($criterio['valor'] ?? '');
        $hasta = (string) ($criterio['valor_hasta'] ?? '');
        $type = (string) $def['type'];
        $column = (string) $def['column'];

        if ($type === 'entero') {
            self::aplicarEnteroQbe($query, $column, $operador, $valor, $hasta);

            return;
        }
        if ($type === 'enum') {
            self::aplicarEnumQbe($query, $campo, $column, $operador, $valor, $hasta);

            return;
        }
        self::aplicarTextoQbe($query, $column, $operador, $valor, $hasta);
    }

    /**
     * @param  Builder<Tipotransaccion_Compra>  $query
     */
    private static function aplicarBusquedaGlobal(Builder $query, string $operador, string $valor): void
    {
        if ($operador === 'contiene' && TipotransaccionCompraRetencionFiltro::interpretar($valor) !== null) {
            TipotransaccionCompraRetencionFiltro::aplicar($query, $valor);

            return;
        }

        if ($operador === 'vacio') {
            $query->where(function ($q) {
                $q->whereNull('tipotransaccion_compra.nombre')->orWhere('tipotransaccion_compra.nombre', '');
            });

            return;
        }

        if ($valor === '') {
            return;
        }

        $like = self::patronLike($operador, $valor);
        $id = filter_var($valor, FILTER_VALIDATE_INT);

        $query->where(function ($q) use ($valor, $like, $id, $operador) {
            if ($id !== false && $operador === 'contiene') {
                $q->orWhere('tipotransaccion_compra.id', (int) $id);
            }

            foreach (['tipotransaccion_compra.nombre', 'tipotransaccion_compra.abreviatura', 'tipotransaccion_compra.codigoafip'] as $col) {
                $q->orWhere($col, 'like', $like);
            }

            if ($operador === 'contiene') {
                CoincidenciaFlexibleTexto::aplicar(
                    $q,
                    'tipotransaccion_compra.nombre',
                    $valor,
                    true,
                    CoincidenciaFlexibleTexto::LONGITUD_MINIMA_DEFAULT
                );
            }

            foreach (self::codigosEnumQueCoinciden($valor, $operador) as $campo => $codigos) {
                $columna = self::CAMPOS[$campo]['column'];
                foreach ($codigos as $codigo) {
                    $q->orWhere($columna, self::valorColumna($campo, $codigo));
                }
            }
        });
    }

    /**
     * @param  Builder<Tipotransaccion_Compra>  $query
     */
    private static function aplicarEnCampo(Builder $query, string $campoKey, string $operador, string $valor): void
    {
        $def = self::CAMPOS[$campoKey] ?? self::CAMPOS['nombre'];
        $type = $def['type'];
        $column = (string) $def['column'];

        if ($type === 'entero') {
            $id = filter_var($valor, FILTER_VALIDATE_INT);
            if ($id === false) {
                return;
            }
            $id = (int) $id;
            match ($operador) {
                'mayor' => $query->where($column, '>', $id),
                'menor' => $query->where($column, '<', $id),
                default => $query->where($column, '=', $id),
            };

            return;
        }

        if ($type === 'enum') {
            self::aplicarEnum($query, $campoKey, $column, $operador, $valor);

            return;
        }

        if ($operador === 'vacio') {
            $query->where(function ($q) use ($column) {
                $q->whereNull($column)->orWhere($column, '');
            });

            return;
        }

        if ($valor === '') {
            return;
        }

        $query->where(function ($q) use ($column, $operador, $valor) {
            $q->where($column, 'like', self::patronLike($operador, $valor));
            if ($operador === 'contiene' && $column === 'tipotransaccion_compra.nombre') {
                CoincidenciaFlexibleTexto::aplicar(
                    $q,
                    $column,
                    $valor,
                    false,
                    CoincidenciaFlexibleTexto::LONGITUD_MINIMA_DEFAULT
                );
            }
        });
    }

    /**
     * @param  Builder<Tipotransaccion_Compra>  $query
     */
    private static function aplicarEnum(Builder $query, string $campo, string $column, string $operador, string $valor): void
    {
        if ($operador === 'distinto') {
            $codigos = self::codigosDeUnEnum($campo, $valor, 'igual');
            if ($codigos === []) {
                return;
            }
            $query->whereNotIn($column, array_map(
                fn (string $codigo) => self::valorColumna($campo, $codigo),
                $codigos
            ));

            return;
        }

        $codigos = self::codigosDeUnEnum($campo, $valor, $operador);
        if ($codigos === []) {
            $query->whereRaw('1 = 0');

            return;
        }

        $query->whereIn($column, array_map(
            fn (string $codigo) => self::valorColumna($campo, $codigo),
            $codigos
        ));
    }

    /**
     * @return array<string, list<string>>
     */
    private static function codigosEnumQueCoinciden(string $valor, string $operador): array
    {
        $salida = [];
        foreach (array_keys(self::enums()) as $campo) {
            $codigos = self::codigosDeUnEnum($campo, $valor, $operador);
            if ($codigos !== []) {
                $salida[$campo] = $codigos;
            }
        }

        return $salida;
    }

    /**
     * @return list<string>
     */
    private static function codigosDeUnEnum(string $campo, string $valor, string $operador): array
    {
        $enum = self::enums()[$campo] ?? null;
        if ($enum === null || $valor === '') {
            return [];
        }

        $norm = self::normalizar($valor);
        $codigos = [];
        foreach ($enum as $codigo => $etiqueta) {
            $etiq = self::normalizar((string) $etiqueta);
            $codigoNorm = self::normalizar((string) $codigo);
            $coincide = match ($operador) {
                'igual' => $etiq === $norm || $codigoNorm === $norm,
                'empieza' => str_starts_with($etiq, $norm) || str_starts_with($codigoNorm, $norm),
                'termina' => str_ends_with($etiq, $norm) || str_ends_with($codigoNorm, $norm),
                default => str_contains($etiq, $norm) || str_contains($codigoNorm, $norm),
            };
            if ($campo === 'retieneiva' || $campo === 'retieneganancia' || $campo === 'retieneIIBB') {
                if ($norm === 'retiene' || $norm === 'si retiene') {
                    $coincide = (string) $codigo === 'S';
                } elseif (str_contains($norm, 'no retiene')) {
                    $coincide = (string) $codigo === 'N';
                }
            }
            if ($coincide) {
                $codigos[] = (string) $codigo;
            }
        }

        return $codigos;
    }

    /**
     * @return array<string, array<string, string>>
     */
    private static function enums(): array
    {
        return [
            'operacion' => Tipotransaccion_Compra::$enumOperacion,
            'signo' => Tipotransaccion_Compra::$enumSigno,
            'subdiario' => Tipotransaccion_Compra::$enumSubdiario,
            'asientocontable' => Tipotransaccion_Compra::$enumAsientoContable,
            'estado' => Tipotransaccion_Compra::$enumEstado,
            'retieneiva' => Tipotransaccion_Compra::$enumRetiene,
            'retieneganancia' => Tipotransaccion_Compra::$enumRetiene,
            'retieneIIBB' => Tipotransaccion_Compra::$enumRetiene,
        ];
    }

    private static function valorColumna(string $campo, string $codigo): int|string
    {
        if ($campo !== 'signo') {
            return $codigo;
        }

        return match ($codigo) {
            'R' => -1,
            'N' => 0,
            default => 1,
        };
    }

    /**
     * @return array<string, string>
     */
    public static function operadoresParaCampo(string $campoKey): array
    {
        $type = self::CAMPOS[$campoKey]['type'] ?? 'texto';

        return match ($type) {
            'entero' => self::OPERADORES_ENTERO,
            'enum' => [
                'contiene' => 'Contiene',
                'igual' => 'Igual a',
                'distinto' => 'Distinto de',
            ],
            default => self::OPERADORES_TEXTO,
        };
    }

    private static function normalizarOperador(string $operador, string $campoKey): string
    {
        $permitidos = array_keys(self::operadoresParaCampo($campoKey));
        if (in_array($operador, $permitidos, true)) {
            return $operador;
        }

        return $permitidos[0] ?? 'contiene';
    }

    private static function patronLike(string $operador, string $valor): string
    {
        $v = addcslashes($valor, '%_\\');

        return match ($operador) {
            'empieza' => $v.'%',
            'termina' => '%'.$v,
            'igual' => $v,
            default => '%'.$v.'%',
        };
    }

    private static function normalizar(string $texto): string
    {
        $texto = mb_strtolower(trim($texto));
        $texto = strtr($texto, [
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u',
        ]);
        $texto = (string) preg_replace('/[^a-z0-9]+/u', ' ', $texto);

        return trim((string) preg_replace('/\s+/', ' ', $texto));
    }

    public static function normalizarOperadorQbe(string $operador, string $campoKey): string
    {
        $type = self::CAMPOS[$campoKey]['type'] ?? 'texto';
        $permitidos = array_keys($type === 'entero' ? self::OPERADORES_QBE_ENTERO : self::OPERADORES_QBE_TEXTO);
        if (in_array($operador, $permitidos, true)) {
            return $operador;
        }

        return $type === 'entero' ? 'igual' : 'contiene';
    }

    /**
     * @param  Builder<Tipotransaccion_Compra>  $query
     */
    private static function orderByCampo(Builder $query, string $campo, string $dir): void
    {
        $def = self::CAMPOS[$campo] ?? null;
        if ($def === null) {
            return;
        }
        $column = (string) $def['column'];
        if (! ListadoOrdenamientoSupport::esColumnaSqlSegura($column)) {
            return;
        }
        $dir = ListadoOrdenamientoSupport::normalizarDir($dir);
        if (($def['type'] ?? '') !== 'enum') {
            $query->orderBy($column, $dir);

            return;
        }

        $enum = self::enums()[$campo] ?? [];
        if ($enum === []) {
            $query->orderBy($column, $dir);

            return;
        }

        $when = [];
        $bindings = [];
        foreach ($enum as $codigo => $etiqueta) {
            $when[] = 'WHEN ? THEN ?';
            $bindings[] = self::valorColumna($campo, (string) $codigo);
            $bindings[] = (string) $etiqueta;
        }
        $query->orderByRaw(
            'CASE '.$column.' '.implode(' ', $when).' ELSE ? END '.$dir,
            array_merge($bindings, [''])
        );
    }

    /**
     * @param  Builder<Tipotransaccion_Compra>  $query
     */
    private static function aplicarEnteroQbe(Builder $query, string $column, string $operador, string $valor, string $hasta): void
    {
        if (! ListadoOrdenamientoSupport::esColumnaSqlSegura($column)) {
            return;
        }
        if ($operador === 'vacio') {
            $query->whereNull($column);

            return;
        }
        if ($operador === 'entre') {
            if (is_numeric($valor)) {
                $query->where($column, '>=', (int) $valor);
            }
            if (is_numeric($hasta)) {
                $query->where($column, '<=', (int) $hasta);
            }

            return;
        }
        if (! is_numeric($valor)) {
            return;
        }
        $op = match ($operador) {
            'mayor' => '>',
            'mayor_igual' => '>=',
            'menor' => '<',
            'menor_igual' => '<=',
            'distinto' => '!=',
            default => '=',
        };
        $query->where($column, $op, (int) $valor);
    }

    /**
     * @param  Builder<Tipotransaccion_Compra>  $query
     */
    private static function aplicarTextoQbe(Builder $query, string $column, string $operador, string $valor, string $hasta): void
    {
        if (! ListadoOrdenamientoSupport::esColumnaSqlSegura($column)) {
            return;
        }
        if ($operador === 'vacio') {
            $query->where(function (Builder $q) use ($column) {
                $q->whereNull($column)->orWhere($column, '');
            });

            return;
        }
        if ($operador === 'entre') {
            if (trim($valor) !== '') {
                $query->where($column, '>=', $valor);
            }
            if (trim($hasta) !== '') {
                $query->where($column, '<=', $hasta);
            }

            return;
        }
        if ($valor === '') {
            return;
        }
        $like = addcslashes($valor, '%_\\');
        switch ($operador) {
            case 'empieza':
                $query->where($column, 'like', $like.'%');
                break;
            case 'termina':
                $query->where($column, 'like', '%'.$like);
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
                $query->where(function (Builder $q) use ($column, $like) {
                    $q->whereNull($column)->orWhere($column, '')->orWhere($column, 'not like', '%'.$like.'%');
                });
                break;
            case 'contiene':
            default:
                $query->where(function (Builder $q) use ($column, $valor, $like) {
                    $q->where($column, 'like', '%'.$like.'%');
                    if ($column === 'tipotransaccion_compra.nombre') {
                        CoincidenciaFlexibleTexto::aplicar(
                            $q,
                            $column,
                            $valor,
                            false,
                            CoincidenciaFlexibleTexto::LONGITUD_MINIMA_DEFAULT
                        );
                    }
                });
                break;
        }
    }

    /**
     * @param  Builder<Tipotransaccion_Compra>  $query
     */
    private static function aplicarEnumQbe(
        Builder $query,
        string $campo,
        string $column,
        string $operador,
        string $valor,
        string $hasta
    ): void {
        if (! ListadoOrdenamientoSupport::esColumnaSqlSegura($column)) {
            return;
        }
        if ($operador === 'vacio') {
            if ($campo === 'signo') {
                $query->whereNull($column);

                return;
            }
            $query->where(function (Builder $q) use ($column) {
                $q->whereNull($column)->orWhere($column, '');
            });

            return;
        }
        if ($operador === 'distinto') {
            self::aplicarEnum($query, $campo, $column, 'distinto', $valor);

            return;
        }
        if (in_array($operador, ['mayor', 'mayor_igual', 'menor', 'menor_igual', 'entre'], true)) {
            $codigos = self::codigosEnumPorOrden($campo, $operador, $valor, $hasta);
        } elseif ($operador === 'no_contiene') {
            $excluir = self::codigosDeUnEnum($campo, $valor, 'contiene');
            if ($excluir === []) {
                return;
            }
            $query->whereNotIn($column, array_map(
                fn (string $codigo) => self::valorColumna($campo, $codigo),
                $excluir
            ));

            return;
        } else {
            $codigos = self::codigosDeUnEnum($campo, $valor, $operador);
        }

        if ($codigos === []) {
            $query->whereRaw('1 = 0');

            return;
        }
        $query->whereIn($column, array_map(
            fn (string $codigo) => self::valorColumna($campo, $codigo),
            $codigos
        ));
    }

    /**
     * @return list<string>
     */
    private static function codigosEnumPorOrden(string $campo, string $operador, string $valor, string $hasta): array
    {
        $enum = self::enums()[$campo] ?? null;
        if ($enum === null) {
            return [];
        }
        $desde = self::normalizar($valor);
        $hastaNorm = self::normalizar($hasta);
        if ($operador !== 'entre' && $desde === '') {
            return [];
        }
        if ($operador === 'entre' && $desde === '' && $hastaNorm === '') {
            return [];
        }

        $codigos = [];
        foreach ($enum as $codigo => $etiqueta) {
            $etiq = self::normalizar((string) $etiqueta);
            $ok = match ($operador) {
                'mayor' => $etiq > $desde,
                'mayor_igual' => $etiq >= $desde,
                'menor' => $etiq < $desde,
                'menor_igual' => $etiq <= $desde,
                'entre' => ($desde === '' || $etiq >= $desde) && ($hastaNorm === '' || $etiq <= $hastaNorm),
                default => false,
            };
            if ($ok) {
                $codigos[] = (string) $codigo;
            }
        }

        return $codigos;
    }
}
