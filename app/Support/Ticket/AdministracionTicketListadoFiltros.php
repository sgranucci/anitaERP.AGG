<?php

namespace App\Support\Ticket;

use App\Models\Ticket\Ticket_Estado;
use App\Support\Listado\CoincidenciaFlexibleTexto;
use App\Support\Listado\FiltrosListadoRequest;
use App\Support\Listado\ListadoAgrupacionSupport;
use App\Support\Listado\ListadoOrdenamientoSupport;
use App\Support\Listado\ListadoQbeFormulaSupport;
use App\Support\Listado\ListadoQbeSupport;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * Filtros del listado de administración de tickets (index / exportaciones).
 */
class AdministracionTicketListadoFiltros
{
    public const MODO_TODOS = 'todos';

    public const MODO_CAMPO = 'campo';

    public const MODO_QBE = 'qbe';

    /** Filtro externo de estado: Pendiente + En ejecución (default del index). */
    public const FILTRO_ESTADO_EN_CURSO = 'EN_CURSO';

    public const FILTRO_ESTADO_TODOS = 'TODOS';

    /** @var list<string> */
    public const ESTADOS_EN_CURSO = ['Pendiente', 'En ejecución'];

    /** @var array<string, array{column: string, type: string, label: string}> */
    public const CAMPOS = [
        'id' => ['column' => 'ticket.id', 'type' => 'entero', 'label' => 'ID'],
        'fecha' => ['column' => 'ticket.fecha', 'type' => 'fecha', 'label' => 'Fecha'],
        'sala' => ['column' => 'sala.nombre', 'type' => 'texto', 'label' => 'Sala'],
        'sector' => ['column' => 'sector_ticket.nombre', 'type' => 'texto', 'label' => 'Sector'],
        'areadestino' => ['column' => 'areadestino.nombre', 'type' => 'texto', 'label' => 'Área de destino'],
        'categoria' => ['column' => 'categoria_ticket.nombre', 'type' => 'texto', 'label' => 'Categoría'],
        'subcategoria' => ['column' => 'subcategoria_ticket.nombre', 'type' => 'texto', 'label' => 'Subcategoría'],
        'titulo' => ['column' => 'ticket.titulo', 'type' => 'texto', 'label' => 'Título'],
        'comentario' => ['column' => 'ticket.comentario', 'type' => 'texto', 'label' => 'Comentario'],
        'usuario' => ['column' => 'usuario.nombre', 'type' => 'texto', 'label' => 'Generó usuario'],
        'tecnico' => ['column' => 'tickets_tarea.nombretecnico', 'type' => 'texto', 'label' => 'Técnico asignado'],
        'estado' => ['column' => 'ticket.estado_ticket', 'type' => 'texto', 'label' => 'Estado'],
        'fecha_resolucion' => ['column' => 'ticket.fecha_resolucion', 'type' => 'fecha', 'label' => 'Fecha resolución'],
        'tiempo_insumido' => ['column' => 'ticket.tiempo_insumido_total', 'type' => 'entero', 'label' => 'Tiempo insumido (min)'],
    ];

    /** @var list<string> */
    private const COLUMNAS_COINCIDENCIA_FLEXIBLE = [
        'sala.nombre',
        'sector_ticket.nombre',
        'areadestino.nombre',
        'categoria_ticket.nombre',
        'subcategoria_ticket.nombre',
        'ticket.titulo',
        'ticket.comentario',
        'usuario.nombre',
        'tickets_tarea.nombretecnico',
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

    /** @var array<string, string> */
    public const OPERADORES_FECHA = [
        'igual' => 'Igual a',
        'desde' => 'Desde (≥)',
        'hasta' => 'Hasta (≤)',
        'entre' => 'Entre',
        'vacio' => 'Sin fecha',
    ];

    /** @var array<string, string> */
    public const OPERADORES_ENTERO_QBE = [
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

    public static function esAreaSistemas(int $areadestinoId): bool
    {
        return $areadestinoId === (int) config('ticket.administracion_sistemas_areadestino_id', 1);
    }

    public static function puedeUsarVerTodosTickets(bool $flTecnico, int $areadestinoId): bool
    {
        return $flTecnico && self::esAreaSistemas($areadestinoId);
    }

    public static function resolverDesdeRequest(Request $request, ?string $busquedaRuta = null): array
    {
        if (FiltrosListadoRequest::solicitudLimpiaFiltros($request)) {
            return self::filtrosVacios();
        }

        $valor = FiltrosListadoRequest::valorBusqueda($request, $busquedaRuta);
        $busquedaRapida = $request->boolean('filtro_busqueda_rapida');

        $modo = (string) $request->input('filtro_modo', self::MODO_TODOS);
        if (! in_array($modo, [self::MODO_TODOS, self::MODO_CAMPO, self::MODO_QBE], true)) {
            $modo = self::MODO_TODOS;
        }

        $campo = (string) $request->input('filtro_campo', 'titulo');
        if (! isset(self::CAMPOS[$campo])) {
            $campo = 'titulo';
        }

        $operador = (string) $request->input('filtro_operador', 'contiene');

        $qbe = ListadoQbeSupport::normalizar(
            $request->input('qbe', []),
            self::camposQbe(),
            static fn (string $op, string $campoQbe): string => self::normalizarOperadorQbe($op, $campoQbe)
        );
        $sort = ListadoOrdenamientoSupport::normalizar($request->input('sort', []), self::camposOrdenables());
        $agrupar = ListadoAgrupacionSupport::normalizar($request->input('group', []), self::camposOrdenables());

        if ($busquedaRapida) {
            $modo = self::MODO_TODOS;
            $operador = 'contiene';
            $qbe = ListadoQbeSupport::vacio();
        } elseif ($request->input('aplicar_qbe') === '1' || ListadoQbeSupport::tieneCriterios($qbe)) {
            $modo = self::MODO_QBE;
        }

        $operador = self::normalizarOperador($operador, $modo === self::MODO_CAMPO ? $campo : 'titulo');

        return [
            'modo' => $modo,
            'campo' => $campo,
            'operador' => $operador,
            'valor' => $valor,
            'valor_hasta' => trim((string) $request->input('filtro_valor_hasta', '')),
            'busqueda' => $valor,
            'busqueda_rapida' => $busquedaRapida,
            'estado' => '',
            'filtro_estado' => self::resolverFiltroEstadoDesdeRequest($request),
            'fecha_desde' => trim((string) $request->input('fecha_desde', '')),
            'fecha_hasta' => trim((string) $request->input('fecha_hasta', '')),
            'fecha_resolucion_desde' => trim((string) $request->input('fecha_resolucion_desde', '')),
            'fecha_resolucion_hasta' => trim((string) $request->input('fecha_resolucion_hasta', '')),
            'ver_todos_tickets' => $request->has('ver_todos_tickets')
                ? $request->boolean('ver_todos_tickets')
                : true,
            'tecnico_usuario_id' => 0,
            'qbe' => $qbe,
            'sort' => $sort,
            'agrupar' => $agrupar,
        ];
    }

    /**
     * Alcance en administración: por defecto todos los tickets del área; sin tilde, solo asignados al usuario.
     *
     * @param  array<string, mixed>  $filtros
     * @return array<string, mixed>
     */
    public static function aplicarAlcanceUsuario(array $filtros, int $usuarioId): array
    {
        if (! empty($filtros['ver_todos_tickets'])) {
            $filtros['tecnico_usuario_id'] = 0;

            return $filtros;
        }

        $filtros['ver_todos_tickets'] = false;
        $filtros['tecnico_usuario_id'] = $usuarioId;

        return $filtros;
    }

    public static function tieneCriteriosUsuario(array $filtros): bool
    {
        if (self::normalizarFiltroEstado((string) ($filtros['filtro_estado'] ?? '')) !== self::FILTRO_ESTADO_EN_CURSO) {
            return true;
        }

        if (trim((string) ($filtros['fecha_desde'] ?? '')) !== ''
            || trim((string) ($filtros['fecha_hasta'] ?? '')) !== '') {
            return true;
        }

        if (trim((string) ($filtros['fecha_resolucion_desde'] ?? '')) !== ''
            || trim((string) ($filtros['fecha_resolucion_hasta'] ?? '')) !== '') {
            return true;
        }

        if (empty($filtros['ver_todos_tickets'])) {
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

        if (($filtros['modo'] ?? '') === self::MODO_QBE || ListadoQbeSupport::tieneCriterios($filtros['qbe'] ?? [])) {
            return true;
        }

        if (($filtros['sort'] ?? []) !== [] || ($filtros['agrupar'] ?? []) !== []) {
            return true;
        }

        if (($filtros['operador'] ?? 'contiene') !== 'contiene') {
            return true;
        }

        return false;
    }

    public static function tieneCriteriosAplicados(array $filtros): bool
    {
        return self::tieneCriteriosUsuario($filtros);
    }

    /**
     * @return array<string, mixed>
     */
    public static function filtrosVacios(): array
    {
        return [
            'modo' => self::MODO_TODOS,
            'campo' => 'titulo',
            'operador' => 'contiene',
            'valor' => '',
            'valor_hasta' => '',
            'busqueda' => '',
            'estado' => '',
            'filtro_estado' => self::FILTRO_ESTADO_EN_CURSO,
            'fecha_desde' => '',
            'fecha_hasta' => '',
            'fecha_resolucion_desde' => '',
            'fecha_resolucion_hasta' => '',
            'ver_todos_tickets' => true,
            'tecnico_usuario_id' => 0,
            'qbe' => ListadoQbeSupport::vacio(),
            'sort' => [],
            'agrupar' => [],
        ];
    }

    /**
     * @return array<string, string|int|bool>
     */
    public static function paraQueryString(array $filtros): array
    {
        $params = [];
        if (($filtros['modo'] ?? self::MODO_TODOS) !== self::MODO_TODOS) {
            $params['filtro_modo'] = $filtros['modo'];
        }
        if (($filtros['modo'] ?? '') === self::MODO_CAMPO) {
            $params['filtro_campo'] = $filtros['campo'] ?? 'titulo';
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
        $filtroEstado = self::normalizarFiltroEstado((string) ($filtros['filtro_estado'] ?? ''));
        if ($filtroEstado === self::FILTRO_ESTADO_TODOS) {
            $params['filtro_estado'] = self::FILTRO_ESTADO_TODOS;
        } elseif ($filtroEstado !== self::FILTRO_ESTADO_EN_CURSO) {
            $params['filtro_estado'] = $filtroEstado;
        }
        if (! empty($filtros['fecha_desde'])) {
            $params['fecha_desde'] = $filtros['fecha_desde'];
        }
        if (! empty($filtros['fecha_hasta'])) {
            $params['fecha_hasta'] = $filtros['fecha_hasta'];
        }
        if (! empty($filtros['fecha_resolucion_desde'])) {
            $params['fecha_resolucion_desde'] = $filtros['fecha_resolucion_desde'];
        }
        if (! empty($filtros['fecha_resolucion_hasta'])) {
            $params['fecha_resolucion_hasta'] = $filtros['fecha_resolucion_hasta'];
        }
        $params['ver_todos_tickets'] = ! empty($filtros['ver_todos_tickets']) ? '1' : '0';
        $params = array_merge($params, ListadoQbeSupport::paraQueryString((array) ($filtros['qbe'] ?? [])));
        $params = array_merge($params, ListadoOrdenamientoSupport::paraQueryString((array) ($filtros['sort'] ?? [])));
        $params = array_merge($params, ListadoAgrupacionSupport::paraQueryString((array) ($filtros['agrupar'] ?? [])));
        foreach (self::normalizarCalculadas($filtros['calculadas'] ?? []) as $i => $calc) {
            $params['calculadas'][$i] = [
                'etiqueta' => $calc['etiqueta'],
                'formula' => $calc['formula'],
            ];
        }

        return $params;
    }

    /**
     * @param  Builder<\App\Models\Ticket\Ticket>  $query
     */
    public static function aplicar(Builder $query, array $filtros): void
    {
        self::aplicarEstado($query, $filtros);
        self::aplicarRangoFechas($query, $filtros);
        self::aplicarRangoFechaResolucion($query, $filtros);

        if (($filtros['modo'] ?? self::MODO_TODOS) === self::MODO_QBE) {
            self::aplicarQbe($query, (array) ($filtros['qbe'] ?? []));

            return;
        }

        if (! self::tieneCriteriosTexto($filtros)) {
            return;
        }

        $valor = trim((string) ($filtros['valor'] ?? ''));
        $operador = $filtros['operador'] ?? 'contiene';
        $modo = $filtros['modo'] ?? self::MODO_TODOS;

        if ($modo === self::MODO_CAMPO) {
            self::aplicarEnCampo($query, $filtros['campo'] ?? 'titulo', $operador, $valor, $filtros['valor_hasta'] ?? '');

            return;
        }

        self::aplicarBusquedaGlobal($query, $operador, $valor);
    }

    private static function tieneCriteriosTexto(array $filtros): bool
    {
        if (($filtros['operador'] ?? '') === 'vacio') {
            return true;
        }

        return trim((string) ($filtros['valor'] ?? '')) !== '';
    }

    public static function normalizarFiltroEstado(string $valor): string
    {
        $valor = trim($valor);
        if ($valor === '' || $valor === self::FILTRO_ESTADO_EN_CURSO) {
            return self::FILTRO_ESTADO_EN_CURSO;
        }
        if ($valor === self::FILTRO_ESTADO_TODOS) {
            return self::FILTRO_ESTADO_TODOS;
        }

        foreach (Ticket_Estado::$enumEstado as $item) {
            if (($item['nombre'] ?? '') === $valor) {
                return $valor;
            }
        }

        return self::FILTRO_ESTADO_EN_CURSO;
    }

    public static function etiquetaFiltroEstado(string $filtroEstado): string
    {
        $filtroEstado = self::normalizarFiltroEstado($filtroEstado);
        if ($filtroEstado === self::FILTRO_ESTADO_EN_CURSO) {
            return 'En curso (Pendiente / En ejecución)';
        }
        if ($filtroEstado === self::FILTRO_ESTADO_TODOS) {
            return 'Todos';
        }

        return $filtroEstado;
    }

    /**
     * @return list<array{valor: string, label: string}>
     */
    public static function opcionesFiltroEstadoExterno(): array
    {
        return [
            ['valor' => self::FILTRO_ESTADO_EN_CURSO, 'label' => 'En curso'],
            ['valor' => 'Sin Asignar', 'label' => 'Sin asignar'],
            ['valor' => 'Pendiente', 'label' => 'Pendiente'],
            ['valor' => 'En ejecución', 'label' => 'En ejecución'],
            ['valor' => 'Finalizado', 'label' => 'Finalizado'],
            ['valor' => 'Suspendido', 'label' => 'Suspendido'],
            ['valor' => self::FILTRO_ESTADO_TODOS, 'label' => 'Todos'],
        ];
    }

    private static function resolverFiltroEstadoDesdeRequest(Request $request): string
    {
        if ($request->has('filtro_estado')) {
            return self::normalizarFiltroEstado((string) $request->input('filtro_estado'));
        }

        $legacy = trim((string) $request->input('estado', ''));
        if ($legacy !== '') {
            return self::normalizarFiltroEstado($legacy);
        }

        return self::FILTRO_ESTADO_EN_CURSO;
    }

    private static function aplicarEstado(Builder $query, array $filtros): void
    {
        $filtro = self::normalizarFiltroEstado((string) ($filtros['filtro_estado'] ?? ''));

        if ($filtro === self::FILTRO_ESTADO_TODOS) {
            return;
        }

        if (self::tieneFiltroFechaResolucion($filtros) && $filtro === self::FILTRO_ESTADO_EN_CURSO) {
            return;
        }

        if ($filtro === self::FILTRO_ESTADO_EN_CURSO) {
            $query->whereIn('ticket.estado_ticket', self::ESTADOS_EN_CURSO);

            return;
        }

        $query->where('ticket.estado_ticket', $filtro);
    }

    /**
     * @param  Builder<\App\Models\Ticket\Ticket>  $query
     */
    private static function aplicarRangoFechas(Builder $query, array $filtros): void
    {
        [$desde, $hasta] = self::normalizarRangoFechas(
            (string) ($filtros['fecha_desde'] ?? ''),
            (string) ($filtros['fecha_hasta'] ?? '')
        );

        if ($desde !== '' && $hasta !== '') {
            $query->whereBetween('ticket.fecha', [$desde, $hasta]);

            return;
        }

        if ($desde !== '') {
            $query->where('ticket.fecha', '>=', $desde);
        } elseif ($hasta !== '') {
            $query->where('ticket.fecha', '<=', $hasta);
        }
    }

    /**
     * @return array{0: string, 1: string}
     */
    public static function normalizarRangoFechas(string $fechaDesde, string $fechaHasta): array
    {
        $desde = trim($fechaDesde);
        $hasta = trim($fechaHasta);

        if ($desde !== '' && $hasta === '') {
            $hasta = $desde;
        } elseif ($hasta !== '' && $desde === '') {
            $desde = $hasta;
        }

        return [$desde, $hasta];
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    private static function tieneFiltroFechaResolucion(array $filtros): bool
    {
        return trim((string) ($filtros['fecha_resolucion_desde'] ?? '')) !== ''
            || trim((string) ($filtros['fecha_resolucion_hasta'] ?? '')) !== '';
    }

    /**
     * @param  Builder<\App\Models\Ticket\Ticket>  $query
     * @param  array<string, mixed>  $filtros
     */
    private static function aplicarRangoFechaResolucion(Builder $query, array $filtros): void
    {
        [$desde, $hasta] = self::normalizarRangoFechas(
            (string) ($filtros['fecha_resolucion_desde'] ?? ''),
            (string) ($filtros['fecha_resolucion_hasta'] ?? '')
        );

        if ($desde !== '' && $hasta !== '') {
            $query->whereBetween('ticket.fecha_resolucion', [$desde, $hasta]);

            return;
        }

        if ($desde !== '') {
            $query->where('ticket.fecha_resolucion', '>=', $desde);
        } elseif ($hasta !== '') {
            $query->where('ticket.fecha_resolucion', '<=', $hasta);
        }
    }

    /**
     * @param  Builder<\App\Models\Ticket\Ticket>  $query
     */
    private static function aplicarBusquedaGlobal(Builder $query, string $operador, string $valor): void
    {
        if ($operador === 'vacio') {
            $query->where(function ($q) {
                foreach (['ticket.titulo', 'ticket.comentario', 'usuario.nombre', 'nombretecnico'] as $col) {
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
                $q->orWhere('ticket.id', (int) $id);
            }

            $fechaBusqueda = self::fechaNormalizadaParaBusqueda($valor);
            if ($fechaBusqueda !== null) {
                $q->orWhereDate('ticket.fecha', '=', $fechaBusqueda);
            }

            foreach (self::COLUMNAS_COINCIDENCIA_FLEXIBLE as $col) {
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

            $q->orWhere('ticket.estado_ticket', 'like', $like);
        });
    }

    private static function usaCoincidenciaFlexibleEnColumna(string $column): bool
    {
        return in_array($column, self::COLUMNAS_COINCIDENCIA_FLEXIBLE, true);
    }

    /**
     * @param  Builder<\App\Models\Ticket\Ticket>  $query
     */
    private static function aplicarEnCampo(Builder $query, string $campoKey, string $operador, string $valor, string $valorHasta): void
    {
        $def = self::CAMPOS[$campoKey] ?? self::CAMPOS['titulo'];
        $type = $def['type'];

        if ($type === 'entero') {
            self::aplicarEntero($query, (string) $def['column'], $operador, $valor);

            return;
        }

        if ($type === 'fecha') {
            self::aplicarFechaCampo($query, (string) $def['column'], $operador, $valor, $valorHasta);

            return;
        }

        self::aplicarTexto($query, (string) $def['column'], $operador, $valor);
    }

    /**
     * @param  Builder<\App\Models\Ticket\Ticket>  $query
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
                            false,
                            CoincidenciaFlexibleTexto::LONGITUD_MINIMA_DEFAULT
                        );
                    }
                });
                break;
        }
    }

    /**
     * @param  Builder<\App\Models\Ticket\Ticket>  $query
     */
    private static function aplicarEntero(Builder $query, string $column, string $operador, string $valor): void
    {
        $id = filter_var($valor, FILTER_VALIDATE_INT);
        if ($id === false) {
            return;
        }
        $id = (int) $id;
        switch ($operador) {
            case 'mayor':
                $query->where($column, '>', $id);
                break;
            case 'menor':
                $query->where($column, '<', $id);
                break;
            case 'igual':
            default:
                $query->where($column, '=', $id);
                break;
        }
    }

    /**
     * @param  Builder<\App\Models\Ticket\Ticket>  $query
     */
    private static function aplicarFechaCampo(Builder $query, string $column, string $operador, string $valor, string $valorHasta): void
    {
        switch ($operador) {
            case 'vacio':
                $query->whereNull($column);
                break;
            case 'desde':
                if ($valor !== '') {
                    $query->where($column, '>=', $valor);
                }
                break;
            case 'hasta':
                if ($valor !== '') {
                    $query->where($column, '<=', $valor);
                }
                break;
            case 'entre':
                if ($valor !== '' && $valorHasta !== '') {
                    $query->whereBetween($column, [$valor, $valorHasta]);
                }
                break;
            case 'igual':
            default:
                if ($valor !== '') {
                    $query->where($column, '=', $valor);
                }
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

    /**
     * Solo intenta filtrar por fecha si el texto tiene formato de fecha (evita errores con acentos/nombres).
     */
    private static function fechaNormalizadaParaBusqueda(string $valor): ?string
    {
        $valor = trim($valor);
        if ($valor === '') {
            return null;
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $valor)) {
            return $valor;
        }

        if (preg_match('/^(\d{1,2})[\/\-](\d{1,2})[\/\-](\d{2,4})$/', $valor, $partes)) {
            $anio = strlen($partes[3]) === 2 ? (2000 + (int) $partes[3]) : (int) $partes[3];

            return sprintf('%04d-%02d-%02d', $anio, (int) $partes[2], (int) $partes[1]);
        }

        return null;
    }

    private static function normalizarOperador(string $operador, string $campoKey): string
    {
        $type = self::CAMPOS[$campoKey]['type'] ?? 'texto';
        $permitidos = match ($type) {
            'entero' => array_keys(self::OPERADORES_ENTERO),
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
        $type = self::CAMPOS[$campoKey]['type'] ?? 'texto';

        return match ($type) {
            'entero' => self::OPERADORES_ENTERO,
            'fecha' => self::OPERADORES_FECHA,
            default => self::OPERADORES_TEXTO,
        };
    }

    /**
     * Texto de filtros activos para PDF / Excel.
     *
     * @param  array<string, mixed>  $filtros
     */
    public static function formatearResumenExport(array $filtros): string
    {
        $partes = [];

        if (! empty($filtros['ver_todos_tickets'])) {
            $partes[] = 'Alcance: todos los tickets del área Sistemas';
        } else {
            $partes[] = 'Alcance: tickets asignados al usuario';
        }

        $filtroEstado = self::normalizarFiltroEstado((string) ($filtros['filtro_estado'] ?? ''));
        if (self::tieneFiltroFechaResolucion($filtros) && $filtroEstado === self::FILTRO_ESTADO_EN_CURSO) {
            $partes[] = 'Estado: incluye finalizados (filtro por fecha de resolución)';
        } else {
            $partes[] = 'Estado: '.self::etiquetaFiltroEstado($filtroEstado);
        }

        [$desde, $hasta] = self::normalizarRangoFechas(
            (string) ($filtros['fecha_desde'] ?? ''),
            (string) ($filtros['fecha_hasta'] ?? '')
        );

        if ($desde !== '' && $hasta !== '') {
            $partes[] = 'Fechas: '.self::formatearFechaDisplay($desde).' – '.self::formatearFechaDisplay($hasta);
        } elseif ($desde !== '') {
            $partes[] = 'Fecha desde: '.self::formatearFechaDisplay($desde);
        } elseif ($hasta !== '') {
            $partes[] = 'Fecha hasta: '.self::formatearFechaDisplay($hasta);
        }

        [$resDesde, $resHasta] = self::normalizarRangoFechas(
            (string) ($filtros['fecha_resolucion_desde'] ?? ''),
            (string) ($filtros['fecha_resolucion_hasta'] ?? '')
        );
        if ($resDesde !== '' && $resHasta !== '') {
            $partes[] = 'Resolución: '.self::formatearFechaDisplay($resDesde).' – '.self::formatearFechaDisplay($resHasta);
        }

        if (($filtros['operador'] ?? '') === 'vacio') {
            $partes[] = 'Búsqueda: campos vacíos';
        } elseif (trim((string) ($filtros['valor'] ?? '')) !== '') {
            $valor = trim((string) $filtros['valor']);
            if (($filtros['modo'] ?? self::MODO_TODOS) === self::MODO_CAMPO) {
                $campo = self::CAMPOS[$filtros['campo'] ?? 'titulo']['label'] ?? 'Campo';
                $operadores = array_merge(self::OPERADORES_TEXTO, self::OPERADORES_ENTERO, self::OPERADORES_FECHA);
                $operador = $operadores[$filtros['operador'] ?? 'contiene'] ?? (string) ($filtros['operador'] ?? '');
                $partes[] = 'Filtro: '.$campo.' ('.$operador.') «'.$valor.'»';
            } else {
                $partes[] = 'Búsqueda: «'.$valor.'» (todos los campos)';
            }
        }

        return implode(' · ', $partes);
    }

    private static function formatearFechaDisplay(string $fechaYmd): string
    {
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $fechaYmd, $partes)) {
            return $partes[3].'/'.$partes[2].'/'.$partes[1];
        }

        return $fechaYmd;
    }

    /**
     * La vista no pisa el estado, las fechas ni el tilde de alcance.
     * El recorte por rol lo sigue aplicando la consulta, no esta clase.
     *
     * @param  array<string, mixed>  $base
     * @param  array<string, mixed>  $desdeVista
     * @return array<string, mixed>
     */
    public static function fusionarDesdeVista(array $base, array $desdeVista): array
    {
        $externos = [
            'filtro_estado' => $base['filtro_estado'] ?? self::FILTRO_ESTADO_EN_CURSO,
            'fecha_desde' => $base['fecha_desde'] ?? '',
            'fecha_hasta' => $base['fecha_hasta'] ?? '',
            'fecha_resolucion_desde' => $base['fecha_resolucion_desde'] ?? '',
            'fecha_resolucion_hasta' => $base['fecha_resolucion_hasta'] ?? '',
            'ver_todos_tickets' => $base['ver_todos_tickets'] ?? true,
            'tecnico_usuario_id' => $base['tecnico_usuario_id'] ?? 0,
            'valor' => $base['valor'] ?? '',
            'valor_hasta' => $base['valor_hasta'] ?? '',
            'busqueda' => $base['busqueda'] ?? '',
            'campo' => $base['campo'] ?? 'titulo',
            'operador' => $base['operador'] ?? 'contiene',
        ];
        $qbeExplicito = ! empty($base['_qbe_explicito']) || (($base['modo'] ?? '') === self::MODO_QBE);
        $qbe = $qbeExplicito
            ? ($base['qbe'] ?? ListadoQbeSupport::vacio())
            : ListadoQbeSupport::normalizar(
                $desdeVista['qbe'] ?? [],
                self::camposQbe(),
                static fn (string $op, string $campo): string => self::normalizarOperadorQbe($op, $campo)
            );
        $modo = (string) ($base['modo'] ?? self::MODO_TODOS);
        if (ListadoQbeSupport::tieneCriterios($qbe)) {
            $modo = self::MODO_QBE;
        }
        $campos = self::camposOrdenables();
        $orden = ListadoOrdenamientoSupport::normalizar($base['sort'] ?? [], $campos);
        if ($orden === []) {
            $orden = ListadoOrdenamientoSupport::normalizar($desdeVista['sort'] ?? ($desdeVista['orden'] ?? []), $campos);
        }
        $agrupar = ListadoAgrupacionSupport::normalizar($base['agrupar'] ?? [], $campos);
        if ($agrupar === []) {
            $agrupar = ListadoAgrupacionSupport::normalizar($desdeVista['agrupar'] ?? ($desdeVista['group'] ?? []), $campos);
        }

        return array_merge($base, $externos, [
            'modo' => $modo,
            'qbe' => $qbe,
            'sort' => $orden,
            'agrupar' => $agrupar,
        ]);
    }

    /**
     * @return array<string, array{label: string, type: string, column: string}>
     */
    public static function camposQbe(): array
    {
        return AdministracionTicketListadoColumnas::camposFiltrables();
    }

    /**
     * @return array<string, array{label: string, type: string, column: string}>
     */
    public static function camposQbeDisponibles(): array
    {
        return self::camposQbe();
    }

    /**
     * @return array<string, array{label: string, type: string, column: string}>
     */
    public static function camposOrdenables(): array
    {
        return AdministracionTicketListadoColumnas::camposOrdenables();
    }

    /**
     * Ejes del gráfico. Los minutos se suman, no se usan como eje.
     *
     * @return array<string, array{label: string, type: string, column: string}>
     */
    public static function camposVisual(): array
    {
        $out = self::camposOrdenables();
        $out['tiempo_insumido']['column'] = '';

        return $out;
    }

    /**
     * @return array<string, array{column: string, type: string, label: string}>
     */
    public static function camposFormula(): array
    {
        $out = [];
        foreach (self::camposQbe() as $key => $meta) {
            $column = (string) ($meta['column'] ?? '');
            if ($column === '' || ! ListadoOrdenamientoSupport::esColumnaSqlSegura($column)) {
                continue;
            }
            $out[$key] = $meta;
        }

        return $out;
    }

    /**
     * @return list<array{etiqueta: string, formula: string, valida: bool}>
     */
    public static function normalizarCalculadas(mixed $raw): array
    {
        if (! is_array($raw)) {
            return [];
        }
        $campos = self::camposFormula();
        $out = [];
        foreach ($raw as $fila) {
            if (count($out) >= 2 || ! is_array($fila)) {
                continue;
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
     * @param  array<string, mixed>|null  $vistaJson
     * @return array<string, mixed>
     */
    public static function mezclarCalculadas(array $filtros, Request $request, ?array $vistaJson): array
    {
        if ($request->exists('calculadas')) {
            $filtros['calculadas'] = self::normalizarCalculadas($request->input('calculadas'));
        } elseif (is_array($vistaJson)) {
            $filtros['calculadas'] = self::normalizarCalculadas($vistaJson['calculadas'] ?? []);
        } else {
            $filtros['calculadas'] = self::normalizarCalculadas($filtros['calculadas'] ?? []);
        }

        return $filtros;
    }

    /**
     * @param  Builder<\App\Models\Ticket\Ticket>  $query
     * @param  array<string, mixed>  $filtros
     */
    public static function aplicarCalculadas(Builder $query, array $filtros): void
    {
        $campos = self::camposFormula();
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
        $limite = (int) ($filtros['_mail_limite'] ?? 0);
        if ($limite > 0) {
            $query->limit($limite);
        }
    }

    /**
     * @param  Builder<\App\Models\Ticket\Ticket>  $query
     * @param  array<string, mixed>  $filtros
     */
    public static function aplicarOrden(Builder $query, array $filtros): void
    {
        $campos = self::camposOrdenables();
        $agrupar = ListadoAgrupacionSupport::normalizar($filtros['agrupar'] ?? [], $campos);
        $sort = ListadoOrdenamientoSupport::normalizar($filtros['sort'] ?? [], $campos);
        if ($agrupar !== []) {
            ListadoAgrupacionSupport::aplicarOrdenPrefijo($query, $agrupar, $campos);
        }
        if ($sort !== []) {
            ListadoOrdenamientoSupport::aplicar($query, $sort, $campos, [
                'campo' => 'id',
                'dir' => ListadoOrdenamientoSupport::DIR_DESC,
            ]);

            return;
        }

        $query->orderBy('ticket.id', 'desc');
    }

    /**
     * @param  Builder<\App\Models\Ticket\Ticket>  $query
     * @param  array<string, mixed>  $qbe
     */
    private static function aplicarQbe(Builder $query, array $qbe): void
    {
        $norm = ListadoQbeSupport::normalizar(
            $qbe,
            self::camposQbe(),
            static fn (string $op, string $campo): string => self::normalizarOperadorQbe($op, $campo)
        );
        ListadoQbeSupport::aplicar(
            $query,
            $norm,
            static function (Builder $q, array $criterio, string $boolean = 'and'): void {
                self::aplicarCriterioQbe($q, $criterio, $boolean);
            }
        );
    }

    /**
     * @param  Builder<\App\Models\Ticket\Ticket>  $query
     * @param  array<string, mixed>  $criterio
     */
    private static function aplicarCriterioQbe(Builder $query, array $criterio, string $boolean): void
    {
        if ($boolean === ListadoQbeSupport::LOGIC_OR) {
            $query->orWhere(function (Builder $inner) use ($criterio) {
                self::aplicarCriterioQbe($inner, $criterio, ListadoQbeSupport::LOGIC_AND);
            });

            return;
        }

        $formula = trim((string) ($criterio['formula'] ?? ''));
        if ($formula !== '') {
            $compiled = ListadoQbeFormulaSupport::compilar($formula, self::camposFormula());
            if ($compiled === null) {
                return;
            }
            ListadoQbeFormulaSupport::aplicarComparacion(
                $query,
                $compiled,
                (string) ($criterio['op'] ?? 'contiene'),
                trim((string) ($criterio['valor'] ?? '')),
                trim((string) ($criterio['valor_hasta'] ?? ''))
            );

            return;
        }

        $campo = (string) ($criterio['campo'] ?? '');
        $def = self::camposQbe()[$campo] ?? null;
        if ($def === null) {
            return;
        }
        $operador = (string) ($criterio['op'] ?? 'contiene');
        $valor = trim((string) ($criterio['valor'] ?? ''));
        $hasta = trim((string) ($criterio['valor_hasta'] ?? ''));
        $column = (string) $def['column'];
        $type = (string) ($def['type'] ?? 'texto');
        if ($column === '' || ! ListadoOrdenamientoSupport::esColumnaSqlSegura($column)) {
            return;
        }
        if ($type === 'fecha') {
            ListadoQbeSupport::aplicarFecha($query, $column, $operador, $valor, $hasta);

            return;
        }
        if ($type === 'entero') {
            if ($operador === 'vacio') {
                $query->whereNull($column);

                return;
            }
            ListadoQbeSupport::aplicarDecimal($query, $column, $operador, $valor, $hasta);

            return;
        }
        self::aplicarTexto($query, $column, $operador, $valor);
    }

    private static function normalizarOperadorQbe(string $operador, string $campoKey): string
    {
        $type = self::camposQbe()[$campoKey]['type'] ?? 'texto';
        $permitidos = match ($type) {
            'entero' => array_keys(self::OPERADORES_ENTERO_QBE),
            'fecha' => array_keys(ListadoQbeSupport::OPERADORES_FECHA),
            'decimal' => array_keys(ListadoQbeSupport::OPERADORES_DECIMAL),
            default => array_keys(self::OPERADORES_TEXTO),
        };
        $operador = strtolower(trim($operador));
        if (in_array($operador, $permitidos, true)) {
            return $operador;
        }

        return $permitidos[0] ?? 'contiene';
    }
}
