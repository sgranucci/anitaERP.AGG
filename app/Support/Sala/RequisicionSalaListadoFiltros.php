<?php

namespace App\Support\Sala;

use App\Support\Listado\CoincidenciaFlexibleTexto;
use App\Support\Listado\FiltrosListadoRequest;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class RequisicionSalaListadoFiltros
{
    public const SESSION_FILTROS = 'requisicion_sala_listado_filtros';

    public const MODO_TODOS = 'todos';

    public const MODO_CAMPO = 'campo';

    /** Filtro externo de estado de ítem (AND con la búsqueda de texto). */
    public const ESTADO_LINEA_TODOS = '';

    public const ESTADO_LINEA_PENDIENTE = 'pendiente';

    public const ESTADO_LINEA_PARCIAL = 'parcial';

    public const ESTADO_LINEA_CUMPLIDA = 'cumplida';

    /** @var array<string, string> */
    public const ESTADOS_LINEA_EXTERNOS = [
        self::ESTADO_LINEA_PENDIENTE => 'Pendiente',
        self::ESTADO_LINEA_PARCIAL => 'Parcial',
        self::ESTADO_LINEA_CUMPLIDA => 'Cumplida',
    ];

    public const CAMPOS = [
        'id' => ['column' => 'requisicion_sala.id', 'type' => 'entero', 'label' => 'ID'],
        'numerorequisicion' => ['column' => 'requisicion_sala.numerorequisicion', 'type' => 'entero', 'label' => 'Número'],
        'nombreusuario' => ['column' => 'usuario.nombre', 'type' => 'texto', 'label' => 'Solicitante'],
        'fecha' => ['column' => 'requisicion_sala.fecha', 'type' => 'fecha', 'label' => 'Fecha'],
        'fecha_entrega' => ['column' => 'requisicion_sala.fecha_entrega', 'type' => 'fecha', 'label' => 'Fecha entrega'],
        'nombreempresa' => ['column' => 'empresa.nombre', 'type' => 'texto', 'label' => 'Empresa'],
        'nombrecentrocosto' => ['column' => 'centrocosto.nombre', 'type' => 'texto', 'label' => 'Centro costo'],
        'nombredeposito' => ['column' => 'depmae.nombre', 'type' => 'texto', 'label' => 'Depósito'],
        'nombrezona' => ['column' => 'zona_sala.nombre', 'type' => 'texto', 'label' => 'Zona sala'],
        'nombreprioridad' => ['column' => 'prioridad_sala.nombre', 'type' => 'texto', 'label' => 'Prioridad'],
        'estado' => ['column' => 'requisicion_sala.estado', 'type' => 'texto', 'label' => 'Estado cabecera'],
        'estado_linea' => [
            'column' => 'requisicion_sala_articulo.estado',
            'type' => 'estado_linea',
            'label' => 'Estado ítem (Cumplida/Parcial/Pendiente)',
            'relation' => 'requisicion_sala_articulos',
        ],
        'sku_articulo' => [
            'column' => 'articulo.sku',
            'type' => 'texto',
            'label' => 'SKU artículo',
            'relation' => 'requisicion_sala_articulos.articulos',
        ],
        'descripcion_articulo' => [
            'column' => 'articulo.descripcion',
            'type' => 'texto',
            'label' => 'Descripción artículo',
            'relation' => 'requisicion_sala_articulos.articulos',
        ],
        'comentario' => ['column' => 'requisicion_sala.comentario', 'type' => 'texto', 'label' => 'Comentario'],
        'detalle' => ['column' => 'requisicion_sala.detalle', 'type' => 'texto', 'label' => 'Detalle'],
    ];

    private const COLUMNAS_COINCIDENCIA_FLEXIBLE = [
        'empresa.nombre',
        'centrocosto.nombre',
        'depmae.nombre',
        'zona_sala.nombre',
        'prioridad_sala.nombre',
        'usuario.nombre',
        'requisicion_sala.comentario',
        'requisicion_sala.detalle',
        'articulo.sku',
        'articulo.descripcion',
    ];

    public const OPERADORES_TEXTO = [
        'contiene' => 'Contiene (en cualquier parte)',
        'empieza' => 'Empieza con',
        'termina' => 'Termina con',
        'igual' => 'Igual a',
        'distinto' => 'Distinto de',
        'vacio' => 'Vacío',
    ];

    public const OPERADORES_ENTERO = [
        'igual' => 'Igual a',
        'mayor' => 'Mayor que',
        'menor' => 'Menor que',
    ];

    public const OPERADORES_FECHA = [
        'igual' => 'Igual a',
        'desde' => 'Desde (≥)',
        'hasta' => 'Hasta (≤)',
        'entre' => 'Entre',
        'vacio' => 'Sin fecha',
    ];

    public static function resolverDesdeRequest(Request $request, ?string $busquedaRuta = null, ?int $empresaDefault = null): array
    {
        [$empresaId, $empresaScope] = self::resolverEmpresaExterna($request, $empresaDefault);

        if (FiltrosListadoRequest::solicitudLimpiaFiltros($request)) {
            return array_merge(self::filtrosVacios(), [
                'empresa_id' => $empresaId,
                'empresa_scope' => $empresaScope,
                'estado_linea' => self::resolverEstadoLineaExterno($request),
            ]);
        }

        $valor = FiltrosListadoRequest::valorBusqueda($request, $busquedaRuta);
        $busquedaRapida = $request->boolean('filtro_busqueda_rapida');
        $modo = (string) $request->input('filtro_modo', self::MODO_TODOS);
        if (! in_array($modo, [self::MODO_TODOS, self::MODO_CAMPO], true)) {
            $modo = self::MODO_TODOS;
        }
        $campo = (string) $request->input('filtro_campo', 'numerorequisicion');
        if (! isset(self::CAMPOS[$campo])) {
            $campo = 'numerorequisicion';
        }
        $operador = (string) $request->input('filtro_operador', 'contiene');
        if ($busquedaRapida) {
            $modo = self::MODO_TODOS;
            $operador = 'contiene';
        }
        $operador = self::normalizarOperador($operador, $modo === self::MODO_CAMPO ? $campo : 'numerorequisicion');

        return [
            'modo' => $modo,
            'campo' => $campo,
            'operador' => $operador,
            'valor' => $valor,
            'valor_hasta' => trim((string) $request->input('filtro_valor_hasta', '')),
            'busqueda' => $valor,
            'empresa_id' => $empresaId,
            'empresa_scope' => $empresaScope,
            'estado_linea' => self::resolverEstadoLineaExterno($request),
        ];
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

    private static function resolverEstadoLineaExterno(Request $request): string
    {
        $valor = strtolower(trim((string) $request->input('estado_linea', self::ESTADO_LINEA_TODOS)));
        if ($valor === '' || $valor === 'todos') {
            return self::ESTADO_LINEA_TODOS;
        }
        if (! isset(self::ESTADOS_LINEA_EXTERNOS[$valor])) {
            return self::ESTADO_LINEA_TODOS;
        }

        return $valor;
    }

    public static function filtrosVacios(): array
    {
        return [
            'modo' => self::MODO_TODOS,
            'campo' => 'numerorequisicion',
            'operador' => 'contiene',
            'valor' => '',
            'valor_hasta' => '',
            'busqueda' => '',
            'empresa_id' => null,
            'empresa_scope' => 'una',
            'estado_linea' => self::ESTADO_LINEA_TODOS,
        ];
    }

    /**
     * Criterios del panel / búsqueda rápida (sin filtros externos de empresa / estado ítem).
     */
    public static function tieneCriteriosTexto(array $filtros): bool
    {
        return trim((string) ($filtros['valor'] ?? '')) !== ''
            || trim((string) ($filtros['valor_hasta'] ?? '')) !== ''
            || (($filtros['modo'] ?? self::MODO_TODOS) === self::MODO_CAMPO
                && in_array($filtros['operador'] ?? '', ['vacio'], true));
    }

    public static function tieneCriteriosAplicados(array $filtros): bool
    {
        return self::tieneCriteriosTexto($filtros)
            || self::tieneEstadoLineaExterno($filtros);
    }

    public static function tieneEstadoLineaExterno(array $filtros): bool
    {
        $estado = (string) ($filtros['estado_linea'] ?? self::ESTADO_LINEA_TODOS);

        return $estado !== '' && isset(self::ESTADOS_LINEA_EXTERNOS[$estado]);
    }

    public static function paraQueryString(array $filtros): array
    {
        $params = self::paraQueryStringExternos($filtros);

        if (! self::tieneCriteriosTexto($filtros)) {
            return $params;
        }

        $params['filtro_modo'] = $filtros['modo'] ?? self::MODO_TODOS;
        if (! empty($filtros['valor'])) {
            $params['filtro_valor'] = $filtros['valor'];
        }
        if (($filtros['modo'] ?? '') === self::MODO_CAMPO) {
            $params['filtro_campo'] = $filtros['campo'] ?? 'numerorequisicion';
            $params['filtro_operador'] = $filtros['operador'] ?? 'contiene';
            if (($filtros['operador'] ?? '') === 'entre') {
                $params['filtro_valor_hasta'] = $filtros['valor_hasta'] ?? '';
            }
        }

        return $params;
    }

    /**
     * Filtros externos (empresa + estado ítem) para Limpiar texto sin perderlos.
     *
     * @return array<string, int|string>
     */
    public static function paraQueryStringEmpresa(array $filtros): array
    {
        return self::paraQueryStringExternos($filtros);
    }

    /**
     * @return array<string, int|string>
     */
    public static function paraQueryStringExternos(array $filtros): array
    {
        $params = [];
        if (($filtros['empresa_scope'] ?? 'una') === 'todas') {
            $params['empresa_todas'] = 1;
        } elseif (! empty($filtros['empresa_id'])) {
            $params['empresa_id'] = (int) $filtros['empresa_id'];
        }
        if (self::tieneEstadoLineaExterno($filtros)) {
            $params['estado_linea'] = $filtros['estado_linea'];
        }

        return $params;
    }

    public static function aplicar(Builder $query, array $filtros): void
    {
        if (! empty($filtros['empresa_id'])) {
            $query->where('requisicion_sala.empresa_id', (int) $filtros['empresa_id']);
        }

        $valoresEstadoExterno = self::valoresEstadoLineaExterno($filtros);
        if ($valoresEstadoExterno !== []) {
            self::aplicarWhereHasEstadoLinea($query, $valoresEstadoExterno);
        }

        if (! self::tieneCriteriosTexto($filtros)) {
            return;
        }

        $valor = trim((string) ($filtros['valor'] ?? ''));
        $operador = $filtros['operador'] ?? 'contiene';
        if (($filtros['modo'] ?? self::MODO_TODOS) === self::MODO_CAMPO) {
            self::aplicarEnCampo(
                $query,
                $filtros['campo'] ?? 'numerorequisicion',
                $operador,
                $valor,
                $filtros['valor_hasta'] ?? '',
                $valoresEstadoExterno
            );

            return;
        }
        if ($valor === '') {
            return;
        }
        $like = '%'.CoincidenciaFlexibleTexto::escapeLike($valor).'%';
        $textCols = [
            'usuario.nombre',
            'empresa.nombre',
            'centrocosto.nombre',
            'depmae.nombre',
            'zona_sala.nombre',
            'prioridad_sala.nombre',
            'requisicion_sala.estado',
            'requisicion_sala.comentario',
            'requisicion_sala.detalle',
        ];
        $query->where(function ($q) use ($valor, $like, $textCols, $valoresEstadoExterno) {
            if (is_numeric($valor)) {
                $id = (int) $valor;
                $q->where('requisicion_sala.id', $id)
                    ->orWhere('requisicion_sala.numerorequisicion', $id);
            }
            foreach ($textCols as $col) {
                $q->orWhere($col, 'like', $like);
                if (in_array($col, self::COLUMNAS_COINCIDENCIA_FLEXIBLE, true)) {
                    CoincidenciaFlexibleTexto::aplicar($q, $col, $valor, true);
                }
            }
            // Artículo / detalle de línea: si hay filtro externo de estado, debe coincidir en la misma línea.
            $q->orWhereHas('requisicion_sala_articulos', function ($linea) use ($like, $valor, $valoresEstadoExterno) {
                if ($valoresEstadoExterno !== []) {
                    $linea->whereIn('requisicion_sala_articulo.estado', $valoresEstadoExterno);
                }
                $linea->where(function ($w) use ($like, $valor) {
                    $w->where(function ($d) use ($like, $valor) {
                        $d->where('requisicion_sala_articulo.detalle', 'like', $like);
                        CoincidenciaFlexibleTexto::aplicar(
                            $d,
                            'requisicion_sala_articulo.detalle',
                            $valor,
                            true,
                            CoincidenciaFlexibleTexto::LONGITUD_MINIMA_ARTICULO
                        );
                    })->orWhereHas('articulos', function ($aq) use ($like, $valor) {
                        $aq->where(function ($art) use ($like, $valor) {
                            self::aplicarOperadorTextoColumna($art, 'articulo.sku', 'contiene', $valor, $like, true);
                            self::aplicarOperadorTextoColumna($art, 'articulo.descripcion', 'contiene', $valor, $like, true);
                        });
                    });
                });
            });
            // Solo si no hay chip externo de estado: permitir buscar por etiqueta de estado en el texto.
            if ($valoresEstadoExterno === []) {
                $valoresEstadoLinea = self::resolverValoresEstadoLinea($valor);
                if ($valoresEstadoLinea !== []) {
                    $q->orWhereHas('requisicion_sala_articulos', function ($linea) use ($valoresEstadoLinea) {
                        $linea->whereIn('requisicion_sala_articulo.estado', $valoresEstadoLinea);
                    });
                }
            }
        });
    }

    /**
     * @return list<string>
     */
    public static function valoresEstadoLineaExterno(array $filtros): array
    {
        if (! self::tieneEstadoLineaExterno($filtros)) {
            return [];
        }

        return self::resolverValoresEstadoLinea((string) $filtros['estado_linea']);
    }

    /**
     * @param  Builder<\Illuminate\Database\Eloquent\Model>  $query
     * @param  list<string>  $valores
     */
    private static function aplicarWhereHasEstadoLinea(Builder $query, array $valores): void
    {
        if ($valores === []) {
            return;
        }

        $query->whereHas('requisicion_sala_articulos', function ($linea) use ($valores) {
            $linea->whereIn('requisicion_sala_articulo.estado', $valores);
        });
    }

    private static function aplicarEnCampo(
        Builder $query,
        string $campoKey,
        string $operador,
        string $valor,
        string $valorHasta,
        array $valoresEstadoExterno = []
    ): void {
        $def = self::CAMPOS[$campoKey] ?? self::CAMPOS['numerorequisicion'];
        $column = $def['column'];
        $type = $def['type'];
        $relation = $def['relation'] ?? null;

        if ($type === 'estado_linea') {
            self::aplicarEstadoLinea($query, $operador, $valor);

            return;
        }
        if ($relation) {
            self::aplicarEnRelacionArticulo($query, $relation, $column, $operador, $valor, $valoresEstadoExterno);

            return;
        }
        if ($operador === 'vacio') {
            $query->where(function ($q) use ($column) {
                $q->whereNull($column)->orWhere($column, '');
            });

            return;
        }
        if ($type === 'fecha') {
            self::aplicarFecha($query, $column, $operador, $valor, $valorHasta);

            return;
        }
        if ($type === 'entero') {
            self::aplicarEntero($query, $column, $operador, $valor);

            return;
        }
        if ($valor === '') {
            return;
        }
        match ($operador) {
            'empieza' => $query->where($column, 'like', CoincidenciaFlexibleTexto::escapeLike($valor).'%'),
            'termina' => $query->where($column, 'like', '%'.CoincidenciaFlexibleTexto::escapeLike($valor)),
            'igual' => $query->where($column, $valor),
            'distinto' => $query->where($column, '!=', $valor),
            default => $query->where(function ($q) use ($column, $valor) {
                $like = '%'.CoincidenciaFlexibleTexto::escapeLike($valor).'%';
                $q->where($column, 'like', $like);
                if (in_array($column, self::COLUMNAS_COINCIDENCIA_FLEXIBLE, true)) {
                    CoincidenciaFlexibleTexto::aplicar($q, $column, $valor, false);
                }
            }),
        };
    }

    /**
     * @param  array<string, string|int|bool>  $filtrosQuery
     */
    public static function persistir(array $filtrosQuery): void
    {
        if ($filtrosQuery === []) {
            session()->forget(self::SESSION_FILTROS);

            return;
        }

        session([self::SESSION_FILTROS => $filtrosQuery]);
    }

    /**
     * @return array<string, string|int|bool>
     */
    public static function guardados(): array
    {
        $guardados = session(self::SESSION_FILTROS, []);

        return is_array($guardados) ? $guardados : [];
    }

    public static function olvidar(): void
    {
        session()->forget(self::SESSION_FILTROS);
    }

    /**
     * Resuelve etiquetas del badge / nombres / códigos a valores de requisicion_sala_articulo.estado.
     *
     * @return list<string>
     */
    public static function resolverValoresEstadoLinea(string $valor): array
    {
        $raw = trim($valor);
        if ($raw === '') {
            return [];
        }

        $norm = self::normalizarTextoBusqueda($raw);

        // Códigos directos del enum.
        if ($raw === ' ' || in_array(mb_strtoupper($raw), ['E', 'R', 'P', 'A', 'C'], true)) {
            return [$raw === ' ' ? ' ' : mb_strtoupper($raw)];
        }

        if (in_array($norm, ['cumplida', 'cumplido', 'entregado', 'cerrado'], true)
            || str_starts_with($norm, 'cumplid')) {
            return ['E', 'C'];
        }

        if (str_contains($norm, 'parcial')
            || in_array($norm, ['entregado parcial', 'entreg. pa', 'entreg pa'], true)) {
            return ['A'];
        }

        if (str_contains($norm, 'retirar') || $norm === 'a retirar') {
            return ['R'];
        }

        if (str_contains($norm, 'pendiente rep') || str_contains($norm, 'pend. rep') || $norm === 'pend rep') {
            return ['P'];
        }

        if ($norm === 'pendiente' || str_starts_with($norm, 'pendient')) {
            return [' ', 'P'];
        }

        // Nombres completos del enum.
        return match ($norm) {
            'entregado' => ['E'],
            'para retirar' => ['R'],
            'pendiente rep' => ['P'],
            'entregado parcial' => ['A'],
            'cerrado' => ['C'],
            default => [],
        };
    }

    private static function normalizarTextoBusqueda(string $valor): string
    {
        $valor = mb_strtolower(trim($valor));
        $valor = strtr($valor, [
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u',
            'ü' => 'u', 'ñ' => 'n',
        ]);
        $valor = preg_replace('/\s+/', ' ', $valor) ?? $valor;

        return $valor;
    }

    /**
     * @param  Builder<\Illuminate\Database\Eloquent\Model>  $query
     */
    private static function aplicarEstadoLinea(Builder $query, string $operador, string $valor): void
    {
        if ($operador === 'vacio') {
            $query->whereHas('requisicion_sala_articulos', function ($q) {
                $q->where(function ($w) {
                    $w->whereNull('requisicion_sala_articulo.estado')
                        ->orWhere('requisicion_sala_articulo.estado', '')
                        ->orWhere('requisicion_sala_articulo.estado', ' ');
                });
            });

            return;
        }
        if ($valor === '') {
            return;
        }

        $valores = self::resolverValoresEstadoLinea($valor);
        if ($valores === []) {
            $query->whereRaw('0 = 1');

            return;
        }

        if ($operador === 'distinto') {
            $query->whereHas('requisicion_sala_articulos', function ($q) use ($valores) {
                $q->whereNotIn('requisicion_sala_articulo.estado', $valores);
            });

            return;
        }

        $query->whereHas('requisicion_sala_articulos', function ($q) use ($valores) {
            $q->whereIn('requisicion_sala_articulo.estado', $valores);
        });
    }

    /**
     * @param  Builder<\Illuminate\Database\Eloquent\Model>  $query
     * @param  list<string>  $valoresEstadoExterno
     */
    private static function aplicarEnRelacionArticulo(
        Builder $query,
        string $relation,
        string $column,
        string $operador,
        string $valor,
        array $valoresEstadoExterno = []
    ): void {
        if ($operador === 'vacio') {
            $query->where(function ($q) use ($relation, $column, $valoresEstadoExterno) {
                $q->whereDoesntHave('requisicion_sala_articulos', function ($linea) use ($valoresEstadoExterno) {
                    if ($valoresEstadoExterno !== []) {
                        $linea->whereIn('requisicion_sala_articulo.estado', $valoresEstadoExterno);
                    }
                })->orWhereHas('requisicion_sala_articulos', function ($linea) use ($column, $valoresEstadoExterno) {
                    if ($valoresEstadoExterno !== []) {
                        $linea->whereIn('requisicion_sala_articulo.estado', $valoresEstadoExterno);
                    }
                    $linea->whereHas('articulos', function ($sub) use ($column) {
                        $sub->where(function ($w) use ($column) {
                            $w->whereNull($column)->orWhere($column, '');
                        });
                    });
                });
            });

            return;
        }
        if ($valor === '') {
            return;
        }

        $like = '%'.CoincidenciaFlexibleTexto::escapeLike($valor).'%';
        // Relación anidada articulos: el estado vive en la línea, no en articulo.
        $query->whereHas('requisicion_sala_articulos', function ($linea) use ($column, $operador, $valor, $like, $valoresEstadoExterno) {
            if ($valoresEstadoExterno !== []) {
                $linea->whereIn('requisicion_sala_articulo.estado', $valoresEstadoExterno);
            }
            $linea->whereHas('articulos', function ($sub) use ($column, $operador, $valor, $like) {
                self::aplicarOperadorTextoColumna($sub, $column, $operador, $valor, $like, false);
            });
        });
    }

    /**
     * @param  Builder<\Illuminate\Database\Eloquent\Model>|\Illuminate\Database\Query\Builder  $query
     */
    private static function aplicarOperadorTextoColumna(
        $query,
        string $column,
        string $operador,
        string $valor,
        string $like,
        bool $orGroup = false
    ): void {
        $method = $orGroup ? 'orWhere' : 'where';
        match ($operador) {
            'empieza' => $query->{$method}($column, 'like', CoincidenciaFlexibleTexto::escapeLike($valor).'%'),
            'termina' => $query->{$method}($column, 'like', '%'.CoincidenciaFlexibleTexto::escapeLike($valor)),
            'igual' => $query->{$method}($column, $valor),
            'distinto' => $query->{$method}($column, '!=', $valor),
            default => $query->{$method}(function ($q) use ($column, $valor, $like) {
                $q->where($column, 'like', $like);
                if (in_array($column, self::COLUMNAS_COINCIDENCIA_FLEXIBLE, true)) {
                    CoincidenciaFlexibleTexto::aplicar(
                        $q,
                        $column,
                        $valor,
                        true,
                        CoincidenciaFlexibleTexto::LONGITUD_MINIMA_ARTICULO
                    );
                }
            }),
        };
    }

    private static function aplicarEntero(Builder $query, string $column, string $operador, string $valor): void
    {
        if ($valor === '' || ! is_numeric($valor)) {
            return;
        }
        $id = (int) $valor;
        match ($operador) {
            'mayor' => $query->where($column, '>', $id),
            'menor' => $query->where($column, '<', $id),
            default => $query->where($column, '=', $id),
        };
    }

    private static function aplicarFecha(Builder $query, string $column, string $operador, string $valor, string $valorHasta): void
    {
        if ($operador === 'entre' && $valor !== '' && $valorHasta !== '') {
            $query->whereBetween($column, [$valor, $valorHasta]);

            return;
        }
        if ($valor === '') {
            return;
        }
        match ($operador) {
            'desde' => $query->where($column, '>=', $valor),
            'hasta' => $query->where($column, '<=', $valor),
            default => $query->whereDate($column, Carbon::parse($valor)->toDateString()),
        };
    }

    private static function normalizarOperador(string $operador, string $campoKey): string
    {
        $type = self::CAMPOS[$campoKey]['type'] ?? 'texto';
        $map = match ($type) {
            'entero' => self::OPERADORES_ENTERO,
            'fecha' => self::OPERADORES_FECHA,
            default => self::OPERADORES_TEXTO,
        };

        return isset($map[$operador]) ? $operador : array_key_first($map);
    }

    public static function operadoresParaCampo(string $campoKey): array
    {
        $type = self::CAMPOS[$campoKey]['type'] ?? 'texto';

        return match ($type) {
            'entero' => self::OPERADORES_ENTERO,
            'fecha' => self::OPERADORES_FECHA,
            default => self::OPERADORES_TEXTO,
        };
    }
}
