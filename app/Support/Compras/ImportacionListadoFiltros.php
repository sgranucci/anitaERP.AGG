<?php

declare(strict_types=1);

namespace App\Support\Compras;

use App\Support\Listado\CoincidenciaFlexibleTexto;
use App\Support\Listado\FiltrosListadoRequest;
use App\Support\Listado\ListadoAgrupacionSupport;
use App\Support\Listado\ListadoOrdenamientoSupport;
use App\Support\Listado\ListadoQbeFormulaSupport;
use App\Support\Listado\ListadoQbeSupport;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * Filtros del listado de importaciones (workbench).
 *
 * Chips externos (estado, empresa) van siempre en AND, por encima de la consulta
 * avanzada (QBE). La búsqueda rápida y el QBE son excluyentes entre sí.
 */
final class ImportacionListadoFiltros
{
    public const MODO_TODOS = 'todos';

    public const MODO_QBE = 'qbe';

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

    /** @var array<string, string> sin campos booleanos hoy; el panel QBE compartido lo espera. */
    public const OPERADORES_BOOLEANO = [
        'igual' => 'Es',
        'vacio' => 'Sin dato',
    ];

    /** @var array<string, string> */
    public const OPERADORES_FECHA = ListadoQbeSupport::OPERADORES_FECHA;

    /** @var array<string, string> */
    public const OPERADORES_DECIMAL = ListadoQbeSupport::OPERADORES_DECIMAL;

    /** @var list<string> */
    private const COLUMNAS_COINCIDENCIA_FLEXIBLE = [
        'importacion.codigo',
        'importacion.observacion',
        'proveedor.nombre',
    ];

    /**
     * @return array<string, array{column: string, type: string, label: string}>
     */
    public static function campos(): array
    {
        return ImportacionListadoColumnas::camposFiltrables();
    }

    /**
     * @return array<string, array{label: string, type: string, column: string}>
     */
    public static function camposOrdenables(): array
    {
        return ImportacionListadoColumnas::camposOrdenables();
    }

    /**
     * @return array<string, array{column: string, type: string, label: string}>
     */
    public static function camposQbeDisponibles(): array
    {
        return self::campos();
    }

    /**
     * @return array{
     *   modo: string,
     *   valor: string,
     *   valor_hasta: string,
     *   busqueda: string,
     *   busqueda_rapida: bool,
     *   qbe: array{entre_grupos: string, grupos: list},
     *   orden: list<array{campo: string, dir: string}>,
     *   agrupar: list<string>,
     *   empresa_id: ?int,
     *   empresa_todas: bool,
     *   filtro_estado: string
     * }
     */
    public static function resolverDesdeRequest(Request $request, ?string $busquedaRuta = null): array
    {
        $chips = [
            'empresa_id' => $request->filled('empresa_id') ? (int) $request->input('empresa_id') : null,
            'empresa_todas' => (bool) $request->boolean('empresa_todas'),
            'filtro_estado' => (string) ($request->input('filtro_estado') ?: ''),
        ];

        if (FiltrosListadoRequest::solicitudLimpiaFiltros($request)) {
            return array_merge(self::filtrosVacios(), $chips);
        }

        $valor = (string) FiltrosListadoRequest::valorBusqueda($request, $busquedaRuta);
        $busquedaRapida = (bool) $request->boolean('filtro_busqueda_rapida');
        $qbe = self::resolverQbeDesdeRequest($request);

        $modo = (string) $request->input('filtro_modo', self::MODO_TODOS);
        if (! in_array($modo, [self::MODO_TODOS, self::MODO_QBE], true)) {
            $modo = self::MODO_TODOS;
        }
        if (ListadoQbeSupport::tieneCriterios($qbe)) {
            $modo = self::MODO_QBE;
        }
        if ($busquedaRapida) {
            $modo = self::MODO_TODOS;
            $qbe = ListadoQbeSupport::vacio();
        }
        if ($modo === self::MODO_QBE) {
            $valor = '';
        }

        return array_merge($chips, [
            'modo' => $modo,
            'valor' => $valor,
            'valor_hasta' => trim((string) $request->input('filtro_valor_hasta', '')),
            'busqueda' => $valor,
            'busqueda_rapida' => $busquedaRapida,
            'qbe' => $qbe,
            'orden' => ListadoOrdenamientoSupport::resolverDesdeRequest($request, self::camposOrdenables()),
            'agrupar' => ListadoAgrupacionSupport::resolverDesdeRequest($request, self::camposOrdenables()),
        ]);
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
     * @return array<string, mixed>
     */
    public static function filtrosVacios(): array
    {
        return [
            'modo' => self::MODO_TODOS,
            'valor' => '',
            'valor_hasta' => '',
            'busqueda' => '',
            'busqueda_rapida' => false,
            'qbe' => ListadoQbeSupport::vacio(),
            'orden' => [],
            'agrupar' => [],
            'empresa_id' => null,
            'empresa_todas' => false,
            'filtro_estado' => '',
        ];
    }

    /** @param array<string, mixed> $filtros */
    public static function tieneCriteriosTexto(array $filtros): bool
    {
        if (ListadoQbeSupport::tieneCriterios((array) ($filtros['qbe'] ?? []))) {
            return true;
        }

        return trim((string) ($filtros['valor'] ?? '')) !== '';
    }

    /** @param array<string, mixed> $filtros */
    public static function tieneChipsAplicados(array $filtros): bool
    {
        if (! empty($filtros['filtro_estado'])) {
            return true;
        }

        return ! empty($filtros['empresa_id']) && empty($filtros['empresa_todas']);
    }

    /** @param array<string, mixed> $filtros */
    public static function tieneCriteriosAplicados(array $filtros): bool
    {
        return self::tieneCriteriosTexto($filtros) || self::tieneChipsAplicados($filtros);
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return array<string, mixed>
     */
    public static function paraQueryString(array $filtros): array
    {
        $q = [];
        if (! empty($filtros['filtro_estado'])) {
            $q['filtro_estado'] = $filtros['filtro_estado'];
        }
        if (! empty($filtros['empresa_todas'])) {
            $q['empresa_todas'] = 1;
        } elseif (! empty($filtros['empresa_id'])) {
            $q['empresa_id'] = $filtros['empresa_id'];
        }

        $campos = self::camposOrdenables();
        $modo = $filtros['modo'] ?? self::MODO_TODOS;
        if ($modo === self::MODO_QBE) {
            $q['filtro_modo'] = self::MODO_QBE;
            $q = array_merge($q, ListadoQbeSupport::paraQueryString(
                ListadoQbeSupport::normalizar(
                    $filtros['qbe'] ?? [],
                    self::campos(),
                    static fn (string $op, string $campo): string => self::normalizarOperador($op, $campo)
                )
            ));
        } elseif (trim((string) ($filtros['valor'] ?? '')) !== '') {
            $q['filtro_valor'] = trim((string) $filtros['valor']);
        }

        return array_merge(
            $q,
            ListadoOrdenamientoSupport::paraQueryString(
                ListadoOrdenamientoSupport::normalizar($filtros['orden'] ?? [], $campos)
            ),
            ListadoAgrupacionSupport::paraQueryString(
                ListadoAgrupacionSupport::normalizar($filtros['agrupar'] ?? [], $campos)
            )
        );
    }

    /**
     * Completa QBE/orden/agrupar con lo guardado en la vista, salvo que el request ya traiga criterios.
     *
     * @param  array<string, mixed>  $base
     * @param  array<string, mixed>  $desdeVista
     * @return array<string, mixed>
     */
    public static function fusionarDesdeVista(array $base, array $desdeVista): array
    {
        $campos = self::camposOrdenables();
        $ordenVista = ListadoOrdenamientoSupport::normalizar($desdeVista['orden'] ?? [], $campos);
        $ordenBase = ListadoOrdenamientoSupport::normalizar($base['orden'] ?? [], $campos);
        $agruparVista = ListadoAgrupacionSupport::normalizar($desdeVista['agrupar'] ?? $desdeVista['group'] ?? [], $campos);
        $agruparBase = ListadoAgrupacionSupport::normalizar($base['agrupar'] ?? [], $campos);

        $qbeExplicito = ! empty($base['_qbe_explicito']);
        unset($base['_qbe_explicito']);

        if (self::tieneCriteriosTexto($base) || $qbeExplicito) {
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
        $modo = ListadoQbeSupport::tieneCriterios($qbe) ? self::MODO_QBE : self::MODO_TODOS;
        $valor = $modo === self::MODO_QBE ? '' : (string) ($desdeVista['valor'] ?? '');

        return array_merge($base, [
            'modo' => $modo,
            'valor' => $valor,
            'valor_hasta' => (string) ($desdeVista['valor_hasta'] ?? ''),
            'busqueda' => $valor,
            'qbe' => $qbe,
            'orden' => $ordenBase !== [] ? $ordenBase : $ordenVista,
            'agrupar' => $agruparBase !== [] ? $agruparBase : $agruparVista,
        ]);
    }

    /**
     * ORDER BY: columnas de agrupación primero, luego orden multi-criterio (o id DESC).
     *
     * @param  Builder<\App\Models\Compras\Importacion>  $query
     * @param  array<string, mixed>  $filtros
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
     * Chips externos (AND) + búsqueda rápida o QBE. Requiere los joins del repository
     * (empresa, proveedor, moneda).
     *
     * @param  Builder<\App\Models\Compras\Importacion>  $query
     * @param  array<string, mixed>|string|null  $filtros
     */
    public static function aplicar(Builder $query, $filtros): void
    {
        if (is_string($filtros)) {
            $filtros = array_merge(self::filtrosVacios(), [
                'valor' => trim($filtros),
                'busqueda' => trim($filtros),
            ]);
        }
        if (! is_array($filtros)) {
            return;
        }

        if (! empty($filtros['empresa_id']) && empty($filtros['empresa_todas'])) {
            $query->where('importacion.empresa_id', (int) $filtros['empresa_id']);
        }

        $estado = trim((string) ($filtros['filtro_estado'] ?? ''));
        if ($estado !== '') {
            $query->where('importacion.estado', $estado);
        }

        if (($filtros['modo'] ?? self::MODO_TODOS) === self::MODO_QBE) {
            self::aplicarQbe($query, (array) ($filtros['qbe'] ?? []));

            return;
        }

        $valor = trim((string) ($filtros['valor'] ?? ''));
        if ($valor !== '') {
            self::aplicarBusquedaGlobal($query, $valor);
        }
    }

    /**
     * @param  Builder<\App\Models\Compras\Importacion>  $query
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
     * @param  Builder<\App\Models\Compras\Importacion>  $query
     */
    private static function aplicarBusquedaGlobal(Builder $query, string $valor): void
    {
        $like = '%'.self::escapeLike($valor).'%';
        $id = filter_var($valor, FILTER_VALIDATE_INT);

        $query->where(function (Builder $q) use ($valor, $like, $id) {
            if ($id !== false) {
                $q->orWhere('importacion.id', (int) $id);
            }
            foreach (['importacion.codigo', 'importacion.observacion', 'importacion.estado', 'proveedor.nombre', 'proveedor.codigo', 'empresa.nombre'] as $col) {
                $q->orWhere($col, 'like', $like);
            }
            CoincidenciaFlexibleTexto::aplicar($q, 'importacion.codigo', $valor);
            CoincidenciaFlexibleTexto::aplicar($q, 'proveedor.nombre', $valor);
        });
    }

    /**
     * @param  Builder<\App\Models\Compras\Importacion>  $query
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
        if ($def === null) {
            return;
        }
        $column = (string) $def['column'];

        match ($def['type']) {
            'entero' => self::aplicarEntero($query, $column, $operador, $valor, $valorHasta),
            'decimal' => ListadoQbeSupport::aplicarDecimal($query, $column, $operador, $valor, $valorHasta),
            'fecha' => ListadoQbeSupport::aplicarFecha($query, $column, $operador, $valor, $valorHasta),
            default => self::aplicarTexto($query, $column, $operador, $valor, $valorHasta),
        };
    }

    /**
     * @param  Builder<\App\Models\Compras\Importacion>  $query
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
                    $q->whereNull($column)
                        ->orWhere($column, '')
                        ->orWhere($column, 'not like', '%'.self::escapeLike($valor).'%');
                });
                break;
            case 'contiene':
            default:
                $query->where(function ($q) use ($column, $valor) {
                    $q->where($column, 'like', '%'.self::escapeLike($valor).'%');
                    if (in_array($column, self::COLUMNAS_COINCIDENCIA_FLEXIBLE, true)) {
                        CoincidenciaFlexibleTexto::aplicar(
                            $q,
                            $column,
                            $valor,
                            true,
                            CoincidenciaFlexibleTexto::LONGITUD_MINIMA_ARTICULO
                        );
                    }
                });
                break;
        }
    }

    /**
     * @param  Builder<\App\Models\Compras\Importacion>  $query
     */
    private static function aplicarEntero(
        Builder $query,
        string $column,
        string $operador,
        string $valor,
        string $valorHasta = ''
    ): void {
        if ($operador === 'vacio') {
            $query->whereNull($column);

            return;
        }
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

        $n = filter_var($valor, FILTER_VALIDATE_INT);
        if ($n === false) {
            return;
        }
        $n = (int) $n;
        match ($operador) {
            'mayor' => $query->where($column, '>', $n),
            'mayor_igual' => $query->where($column, '>=', $n),
            'menor' => $query->where($column, '<', $n),
            'menor_igual' => $query->where($column, '<=', $n),
            default => $query->where($column, '=', $n),
        };
    }

    private static function escapeLike(string $value): string
    {
        return addcslashes($value, '%_\\');
    }

    private static function normalizarOperador(string $operador, string $campoKey): string
    {
        $permitidos = array_keys(self::operadoresParaCampo($campoKey));
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
            'decimal' => self::OPERADORES_DECIMAL,
            'fecha' => self::OPERADORES_FECHA,
            default => self::OPERADORES_TEXTO,
        };
    }
}
