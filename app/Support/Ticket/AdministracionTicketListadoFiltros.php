<?php

namespace App\Support\Ticket;

use App\Support\Listado\CoincidenciaFlexibleTexto;
use App\Support\Listado\FiltrosListadoRequest;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * Filtros del listado de administración de tickets (index / exportaciones).
 */
class AdministracionTicketListadoFiltros
{
    public const MODO_TODOS = 'todos';

    public const MODO_CAMPO = 'campo';

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
        'tecnico' => ['column' => 'nombretecnico', 'type' => 'texto', 'label' => 'Técnico asignado'],
        'estado' => ['column' => 'ticket.estado_ticket', 'type' => 'texto', 'label' => 'Estado'],
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
        'nombretecnico',
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
        if (! in_array($modo, [self::MODO_TODOS, self::MODO_CAMPO], true)) {
            $modo = self::MODO_TODOS;
        }

        $campo = (string) $request->input('filtro_campo', 'titulo');
        if (! isset(self::CAMPOS[$campo])) {
            $campo = 'titulo';
        }

        $operador = (string) $request->input('filtro_operador', 'contiene');

        if ($busquedaRapida) {
            $modo = self::MODO_TODOS;
            $operador = 'contiene';
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
            'estado' => trim((string) $request->input('estado', '')),
            'fecha_desde' => trim((string) $request->input('fecha_desde', '')),
            'fecha_hasta' => trim((string) $request->input('fecha_hasta', '')),
            'ver_todos_tickets' => $request->has('ver_todos_tickets')
                ? $request->boolean('ver_todos_tickets')
                : true,
            'tecnico_usuario_id' => 0,
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
        if (trim((string) ($filtros['estado'] ?? '')) !== '') {
            return true;
        }

        if (trim((string) ($filtros['fecha_desde'] ?? '')) !== ''
            || trim((string) ($filtros['fecha_hasta'] ?? '')) !== '') {
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
            'fecha_desde' => '',
            'fecha_hasta' => '',
            'ver_todos_tickets' => true,
            'tecnico_usuario_id' => 0,
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
        if (! empty($filtros['estado'])) {
            $params['estado'] = $filtros['estado'];
        }
        if (! empty($filtros['fecha_desde'])) {
            $params['fecha_desde'] = $filtros['fecha_desde'];
        }
        if (! empty($filtros['fecha_hasta'])) {
            $params['fecha_hasta'] = $filtros['fecha_hasta'];
        }
        $params['ver_todos_tickets'] = ! empty($filtros['ver_todos_tickets']) ? '1' : '0';

        return $params;
    }

    /**
     * @param  Builder<\App\Models\Ticket\Ticket>  $query
     */
    public static function aplicar(Builder $query, array $filtros): void
    {
        self::aplicarEstado($query, $filtros);
        self::aplicarRangoFechas($query, $filtros);

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

    /**
     * @param  Builder<\App\Models\Ticket\Ticket>  $query
     */
    private static function aplicarEstado(Builder $query, array $filtros): void
    {
        $estado = trim((string) ($filtros['estado'] ?? ''));
        if ($estado !== '') {
            $query->where('ticket.estado_ticket', $estado);

            return;
        }

        $query->where('ticket.estado_ticket', '!=', 'Finalizado');
        $query->where('ticket.estado_ticket', '!=', 'Suspendido');
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

        $estado = trim((string) ($filtros['estado'] ?? ''));
        if ($estado !== '') {
            $partes[] = 'Estado: '.$estado;
        } else {
            $partes[] = 'Estado: excluye Finalizado y Suspendido';
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
}
