<?php

declare(strict_types=1);

namespace App\Support\Caja;

use App\Support\Database\SqlDialectSupport;
use App\Support\Listado\ListadoAgrupacionSupport;
use App\Support\Listado\ListadoOrdenamientoSupport;
use App\Support\Listado\ListadoQbeSupport;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * Filtros del listado de cobranzas (index). Versión liviana: la búsqueda de texto,
 * fechas y origen siguen resueltos en CobranzaQuery::consulta.
 */
final class CobranzaListadoFiltros
{
    /**
     * Claves = Cobranza_Estado::$enumEstado[*]['nombre'] (valor guardado en cobranza.estado).
     *
     * @var array<string, string>
     */
    public const ESTADOS_FICHA = [
        'CONFIRMADA' => 'Confirmada',
        'PRE CARGA' => 'Pre carga',
        'REVERTIDA' => 'Revertida',
        'BAJA' => 'Baja',
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

    /** @var array<string, string> */
    public const OPERADORES_FECHA = ListadoQbeSupport::OPERADORES_FECHA;

    /**
     * @return array<string, mixed>
     */
    public static function resolverDesdeRequest(Request $request, ?int $empresaDefault = null): array
    {
        [$empresaId, $empresaScope] = self::resolverEmpresaExterna($request, $empresaDefault);

        $qbe = ListadoQbeSupport::normalizar(
            $request->input('qbe', []),
            self::camposQbe(),
            static fn (string $op, string $campo): string => self::normalizarOperadorQbe($op, $campo)
        );

        return [
            'busqueda' => trim((string) $request->input('busqueda', '')),
            'fecha_desde' => trim((string) $request->input('fecha_desde', '')),
            'fecha_hasta' => trim((string) $request->input('fecha_hasta', '')),
            'origen' => CobranzaOrigenFerliSupport::normalizar($request->input('origen', '')),
            'empresa_id' => $empresaId,
            'empresa_scope' => $empresaScope,
            'estado' => self::normalizarEstado((string) $request->input('estado', '')),
            'qbe' => $qbe,
            'sort' => ListadoOrdenamientoSupport::normalizar($request->input('sort', []), self::camposOrdenables()),
            'agrupar' => ListadoAgrupacionSupport::normalizar($request->input('group', []), self::camposOrdenables()),
        ];
    }

    /**
     * Normaliza un QBE crudo (request o vista guardada) con las claves del workbench.
     *
     * @return array<string, mixed>
     */
    public static function normalizarQbe(mixed $raw): array
    {
        return ListadoQbeSupport::normalizar(
            $raw,
            self::camposQbe(),
            static fn (string $op, string $campo): string => self::normalizarOperadorQbe($op, $campo)
        );
    }

    /**
     * @return array{0:?int,1:string}
     */
    public static function resolverEmpresaExterna(Request $request, ?int $empresaDefault): array
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

    /**
     * Vacío (todos) o una clave de ESTADOS_FICHA.
     */
    public static function normalizarEstado(?string $estado): string
    {
        $estado = mb_strtoupper(trim((string) $estado));

        return isset(self::ESTADOS_FICHA[$estado]) ? $estado : '';
    }

    /**
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

    /**
     * @return array<string, mixed>
     */
    public static function paraQueryString(array $filtros): array
    {
        $params = self::paraQueryStringEmpresa($filtros);

        $busqueda = trim((string) ($filtros['busqueda'] ?? ''));
        if ($busqueda !== '') {
            $params['busqueda'] = $busqueda;
        }
        if (! empty($filtros['fecha_desde'])) {
            $params['fecha_desde'] = $filtros['fecha_desde'];
        }
        if (! empty($filtros['fecha_hasta'])) {
            $params['fecha_hasta'] = $filtros['fecha_hasta'];
        }
        if (! empty($filtros['origen'])) {
            $params['origen'] = $filtros['origen'];
        }
        $estado = self::normalizarEstado((string) ($filtros['estado'] ?? ''));
        if ($estado !== '') {
            $params['estado'] = $estado;
        }
        if (! empty($filtros['wb'])) {
            $params['wb'] = 1;
        }
        if (! empty($filtros['vista_id'])) {
            $params['vista_id'] = (int) $filtros['vista_id'];
        }
        if (! empty($filtros['columnas'])) {
            $params['columnas'] = is_array($filtros['columnas'])
                ? implode(',', $filtros['columnas'])
                : (string) $filtros['columnas'];
        }

        $qbe = (array) ($filtros['qbe'] ?? []);
        if ($qbe !== []) {
            $params = array_merge($params, ListadoQbeSupport::paraQueryString($qbe));
        }
        $params = array_merge($params, ListadoOrdenamientoSupport::paraQueryString((array) ($filtros['sort'] ?? [])));
        $params = array_merge($params, ListadoAgrupacionSupport::paraQueryString((array) ($filtros['agrupar'] ?? [])));

        return $params;
    }

    /**
     * @param  Builder<\App\Models\Caja\Cobranza>  $query
     * @param  array<string, mixed>  $filtros
     */
    public static function aplicarEmpresaEstado(Builder $query, array $filtros): void
    {
        if (! empty($filtros['empresa_id'])) {
            $query->where('cobranza.empresa_id', (int) $filtros['empresa_id']);
        }

        $estado = self::normalizarEstado((string) ($filtros['estado'] ?? ''));
        if ($estado !== '') {
            $query->where('cobranza.estado', $estado);
        }
    }

    /**
     * Aplica el QBE del listado. Resuelve cada criterio contra las claves de
     * CobranzaListadoColumnas (workbench) y, si la clave no existe ahí, contra
     * CobranzaListadoVisual::campos() (click de gráfico: igualdad exacta).
     * Un solo recorrido del árbol, así AND/OR/NOT de los grupos se respetan.
     *
     * @param  Builder<\App\Models\Caja\Cobranza>  $query
     * @param  array<string, mixed>|null  $qbe
     */
    public static function aplicarQbe(Builder $query, ?array $qbe): void
    {
        if (! is_array($qbe) || ! ListadoQbeSupport::tieneCriterios($qbe)) {
            return;
        }

        ListadoQbeSupport::aplicar($query, $qbe, static function ($q, array $criterio, string $boolean = ListadoQbeSupport::LOGIC_AND): void {
            self::aplicarCriterioQbe($q, $criterio, $boolean);
        });
    }

    /**
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

        $campo = (string) ($criterio['campo'] ?? '');
        $operador = (string) ($criterio['op'] ?? 'contiene');
        $valor = trim((string) ($criterio['valor'] ?? ''));
        $hasta = trim((string) ($criterio['valor_hasta'] ?? ''));

        $def = self::camposQbe()[$campo] ?? null;
        if ($def === null) {
            $visual = CobranzaListadoVisual::campos()[$campo] ?? null;
            if ($visual === null || $valor === '') {
                return;
            }
            $columna = (string) $visual['column'];
            if (($visual['type'] ?? '') === 'decimal') {
                $query->where($columna, '=', $valor);

                return;
            }
            if (! ListadoOrdenamientoSupport::esColumnaSqlSegura($columna)) {
                return;
            }
            $query->whereRaw(SqlDialectSupport::castTexto($columna).' = ?', [$valor]);

            return;
        }

        $column = (string) $def['column'];
        $type = (string) ($def['type'] ?? 'texto');

        if ($type === 'fecha') {
            ListadoQbeSupport::aplicarFecha($query, $column, $operador, $valor, $hasta);

            return;
        }
        if ($type === 'entero' || $type === 'decimal') {
            if ($operador === 'vacio') {
                $query->whereNull($column);

                return;
            }
            ListadoQbeSupport::aplicarDecimal($query, $column, $operador, $valor, $hasta);

            return;
        }

        self::aplicarTexto($query, $column, $operador, $valor);
    }

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
                $query->where(function ($q) use ($column, $valor) {
                    $q->where($column, '!=', $valor)->orWhereNull($column);
                });
                break;
            case 'contiene':
            default:
                $query->where($column, 'like', '%'.self::escapeLike($valor).'%');
                break;
        }
    }

    private static function escapeLike(string $valor): string
    {
        return addcslashes($valor, '\\%_');
    }

    /**
     * @return array<string, array{label: string, type: string, column: string, group?: string}>
     */
    public static function camposQbe(): array
    {
        return CobranzaListadoColumnas::camposFiltrables();
    }

    private static function normalizarOperadorQbe(string $operador, string $campoKey): string
    {
        $type = self::camposQbe()[$campoKey]['type'] ?? 'texto';
        $permitidos = match ($type) {
            'entero' => array_keys(self::OPERADORES_ENTERO_QBE),
            'fecha' => array_keys(self::OPERADORES_FECHA),
            'decimal' => array_keys(ListadoQbeSupport::OPERADORES_DECIMAL),
            default => array_keys(self::OPERADORES_TEXTO),
        };
        $operador = strtolower(trim($operador));

        return in_array($operador, $permitidos, true) ? $operador : ($permitidos[0] ?? 'contiene');
    }

    /**
     * @return array<string, array{label: string, type: string, column: string}>
     */
    public static function camposOrdenables(): array
    {
        return CobranzaListadoColumnas::camposOrdenables();
    }

    /**
     * @return array<string, array{column: string, type: string, label: string, attr: string}>
     */
    public static function camposVisual(): array
    {
        return CobranzaListadoVisual::campos();
    }
}
