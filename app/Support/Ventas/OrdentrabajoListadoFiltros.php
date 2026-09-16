<?php

namespace App\Support\Ventas;

use App\Support\Listado\CoincidenciaFlexibleTexto;
use App\Support\Listado\FiltrosListadoRequest;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * Filtros del listado de órdenes de trabajo (indexp / exportaciones).
 */
class OrdentrabajoListadoFiltros
{
    public const MODO_TODOS = 'todos';

    public const MODO_CAMPO = 'campo';

    /** @var array<string, array{column: string, type: string, label: string, relation?: string}> */
    public const CAMPOS = [
        'codigo' => ['column' => 'ordentrabajo.codigo', 'type' => 'entero', 'label' => 'Nro. OT'],
        'fecha' => ['column' => 'ordentrabajo.fecha', 'type' => 'texto', 'label' => 'Fecha'],
        'cliente' => ['column' => 'cliente.nombre', 'type' => 'texto', 'label' => 'Cliente', 'relation' => 'cliente'],
        'articulo' => ['column' => 'articulo.descripcion', 'type' => 'texto', 'label' => 'Artículo', 'relation' => 'articulo'],
        'combinacion' => ['column' => 'combinacion.nombre', 'type' => 'texto', 'label' => 'Combinación', 'relation' => 'combinacion'],
        'estado' => ['column' => 'ordentrabajo.estado', 'type' => 'texto', 'label' => 'Estado'],
        'tarea' => ['column' => 'tarea.nombre', 'type' => 'texto', 'label' => 'Tarea / estado proceso', 'relation' => 'tarea'],
    ];

    /** @var list<string> */
    private const COLUMNAS_COINCIDENCIA_FLEXIBLE = [
        'cliente.nombre',
        'articulo.descripcion',
        'combinacion.nombre',
        'tarea.nombre',
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

        $campo = (string) $request->input('filtro_campo', 'cliente');
        if (! isset(self::CAMPOS[$campo])) {
            $campo = 'cliente';
        }

        $operador = (string) $request->input('filtro_operador', 'contiene');

        if ($busquedaRapida) {
            $modo = self::MODO_TODOS;
            $operador = 'contiene';
        }

        $operador = self::normalizarOperador($operador, $modo === self::MODO_CAMPO ? $campo : 'cliente');

        return [
            'modo' => $modo,
            'campo' => $campo,
            'operador' => $operador,
            'valor' => $valor,
            'valor_hasta' => trim((string) $request->input('filtro_valor_hasta', '')),
            'busqueda' => $valor,
            'busqueda_rapida' => $busquedaRapida,
        ];
    }

    public static function tieneCriteriosTexto(array $filtros): bool
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

        return false;
    }

    public static function tieneCriteriosAplicados(array $filtros): bool
    {
        return self::tieneCriteriosTexto($filtros);
    }

    public static function filtrosVacios(): array
    {
        return [
            'modo' => self::MODO_TODOS,
            'campo' => 'cliente',
            'operador' => 'contiene',
            'valor' => '',
            'valor_hasta' => '',
            'busqueda' => '',
        ];
    }

    /** @return array<string, string|int|bool> */
    public static function paraQueryString(array $filtros): array
    {
        $params = [];
        if (($filtros['modo'] ?? self::MODO_TODOS) !== self::MODO_TODOS) {
            $params['filtro_modo'] = $filtros['modo'];
        }
        if (($filtros['modo'] ?? '') === self::MODO_CAMPO) {
            $params['filtro_campo'] = $filtros['campo'] ?? 'cliente';
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

        return $params;
    }

    /**
     * @param  Builder<\App\Models\Ventas\Ordentrabajo>  $query
     */
    public static function aplicar(Builder $query, array $filtros): void
    {
        if (! self::tieneCriteriosAplicados($filtros)) {
            return;
        }

        $valor = trim((string) ($filtros['valor'] ?? ''));
        $modo = $filtros['modo'] ?? self::MODO_TODOS;
        $operador = $filtros['operador'] ?? 'contiene';

        if ($modo === self::MODO_CAMPO) {
            self::aplicarEnCampo($query, $filtros['campo'] ?? 'cliente', $operador, $valor);

            return;
        }

        self::aplicarBusquedaGlobal($query, $operador, $valor);
    }

    /**
     * @param  Builder<\App\Models\Ventas\Ordentrabajo>  $query
     */
    private static function aplicarBusquedaGlobal(Builder $query, string $operador, string $valor): void
    {
        if ($operador === 'vacio' || $valor === '') {
            return;
        }

        $boletasJuntas = 'BOLETAS JUNTAS';
        if (strncasecmp($valor, $boletasJuntas, strlen($valor)) === 0) {
            $query->whereRaw(
                '(select count(distinct(ordentrabajo_combinacion_talle.cliente_id)) from ordentrabajo_combinacion_talle
				where ordentrabajo.id=ordentrabajo_combinacion_talle.ordentrabajo_id) >= 2'
            );

            return;
        }

        $id = filter_var($valor, FILTER_VALIDATE_INT);
        $like = self::patronLike($operador, $valor);

        $query->where(function ($q) use ($valor, $like, $id, $operador) {
            if ($id !== false) {
                $q->orWhere('ordentrabajo.codigo', (int) $id);
                $q->orWhere('ordentrabajo.id', (int) $id);
            }
            $q->orWhere('ordentrabajo.estado', 'like', $like);
            $q->orWhere('ordentrabajo.fecha', 'like', $like);

            $q->orWhereHas('ordentrabajo_combinacion_talles.clientes', function ($cq) use ($valor, $like, $operador) {
                $cq->where('nombre', 'like', $like);
                if ($operador === 'contiene') {
                    CoincidenciaFlexibleTexto::aplicar(
                        $cq,
                        'nombre',
                        $valor,
                        false,
                        CoincidenciaFlexibleTexto::LONGITUD_MINIMA_DEFAULT
                    );
                }
            });

            $q->orWhereHas(
                'ordentrabajo_combinacion_talles.pedido_combinacion_talles.pedidos_combinacion.articulos',
                function ($aq) use ($valor, $like, $operador) {
                    $aq->where('descripcion', 'like', $like);
                    if ($operador === 'contiene') {
                        CoincidenciaFlexibleTexto::aplicar(
                            $aq,
                            'descripcion',
                            $valor,
                            false,
                            CoincidenciaFlexibleTexto::LONGITUD_MINIMA_ARTICULO
                        );
                    }
                }
            );

            $q->orWhereHas(
                'ordentrabajo_combinacion_talles.pedido_combinacion_talles.pedidos_combinacion.combinaciones',
                function ($cq) use ($valor, $like, $operador) {
                    $cq->where('nombre', 'like', $like);
                    if ($operador === 'contiene') {
                        CoincidenciaFlexibleTexto::aplicar(
                            $cq,
                            'nombre',
                            $valor,
                            false,
                            CoincidenciaFlexibleTexto::LONGITUD_MINIMA_DEFAULT
                        );
                    }
                }
            );

            $q->orWhereHas('ordentrabajo_tareas.tareas', function ($tq) use ($valor, $like, $operador) {
                $tq->where('nombre', 'like', $like);
                if ($operador === 'contiene') {
                    CoincidenciaFlexibleTexto::aplicar(
                        $tq,
                        'nombre',
                        $valor,
                        false,
                        CoincidenciaFlexibleTexto::LONGITUD_MINIMA_DEFAULT
                    );
                }
            });
        });
    }

    /**
     * @param  Builder<\App\Models\Ventas\Ordentrabajo>  $query
     */
    private static function aplicarEnCampo(Builder $query, string $campoKey, string $operador, string $valor): void
    {
        $def = self::CAMPOS[$campoKey] ?? self::CAMPOS['cliente'];
        $relation = $def['relation'] ?? null;

        if ($relation === 'cliente') {
            self::aplicarWhereHasTexto(
                $query,
                'ordentrabajo_combinacion_talles.clientes',
                'nombre',
                $operador,
                $valor
            );

            return;
        }
        if ($relation === 'articulo') {
            self::aplicarWhereHasTexto(
                $query,
                'ordentrabajo_combinacion_talles.pedido_combinacion_talles.pedidos_combinacion.articulos',
                'descripcion',
                $operador,
                $valor
            );

            return;
        }
        if ($relation === 'combinacion') {
            self::aplicarWhereHasTexto(
                $query,
                'ordentrabajo_combinacion_talles.pedido_combinacion_talles.pedidos_combinacion.combinaciones',
                'nombre',
                $operador,
                $valor
            );

            return;
        }
        if ($relation === 'tarea') {
            self::aplicarWhereHasTexto($query, 'ordentrabajo_tareas.tareas', 'nombre', $operador, $valor);

            return;
        }

        if (($def['type'] ?? '') === 'entero') {
            self::aplicarEntero($query, (string) $def['column'], $operador, $valor);

            return;
        }

        self::aplicarTexto($query, (string) $def['column'], $operador, $valor);
    }

    /**
     * @param  Builder<\App\Models\Ventas\Ordentrabajo>  $query
     */
    private static function aplicarWhereHasTexto(
        Builder $query,
        string $relation,
        string $column,
        string $operador,
        string $valor
    ): void {
        if ($operador === 'vacio') {
            $query->whereDoesntHave($relation);

            return;
        }
        if ($valor === '') {
            return;
        }

        $query->whereHas($relation, function ($q) use ($column, $operador, $valor) {
            self::aplicarTexto($q, $column, $operador, $valor);
        });
    }

    /**
     * @param  Builder<\Illuminate\Database\Eloquent\Model>  $query
     */
    private static function aplicarTexto(Builder $query, string $column, string $operador, string $valor): void
    {
        if ($operador === 'vacio') {
            $query->where(function ($q) use ($column) {
                $q->whereNull($column)->orWhere($column, '');
            });

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
            default:
                $query->where(function ($q) use ($column, $valor) {
                    $q->where($column, 'like', '%'.self::escapeLike($valor).'%');
                    if (in_array($column, self::COLUMNAS_COINCIDENCIA_FLEXIBLE, true)
                        || in_array($column, ['nombre', 'descripcion'], true)) {
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
     * @param  Builder<\App\Models\Ventas\Ordentrabajo>  $query
     */
    private static function aplicarEntero(Builder $query, string $column, string $operador, string $valor): void
    {
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
        $type = self::CAMPOS[$campoKey]['type'] ?? 'texto';
        $permitidos = $type === 'entero' ? array_keys(self::OPERADORES_ENTERO) : array_keys(self::OPERADORES_TEXTO);

        return in_array($operador, $permitidos, true) ? $operador : ($permitidos[0] ?? 'contiene');
    }

    /** @return array<string, string> */
    public static function operadoresParaCampo(string $campoKey): array
    {
        $type = self::CAMPOS[$campoKey]['type'] ?? 'texto';

        return $type === 'entero' ? self::OPERADORES_ENTERO : self::OPERADORES_TEXTO;
    }
}
