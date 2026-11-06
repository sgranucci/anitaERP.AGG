<?php

namespace App\Support\Caja;

use App\Models\Caja\TesoreriaCrownResultadoOnline;
use App\Support\Listado\FiltrosListadoRequest;
use App\Support\Listado\ListadoAgrupacionSupport;
use App\Support\Listado\ListadoOrdenamientoSupport;
use App\Support\Listado\ListadoQbeSupport;
use DateTime;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class TesoreriaCrownResultadoOnlineListadoFiltros
{
    /**
     * @return array<string, mixed>
     */
    public static function resolverDesdeRequest(Request $request, ?string $busquedaRuta = null): array
    {
        $salaId = (int) $request->input('sala_id', 0);
        if (FiltrosListadoRequest::solicitudLimpiaFiltros($request)) {
            $vacios = self::filtrosVacios();
            $vacios['sala_id'] = $salaId;

            return $vacios;
        }

        return [
            'valor' => FiltrosListadoRequest::valorBusqueda($request, $busquedaRuta),
            'sala_id' => $salaId,
            'fecha_desde' => self::fecha($request->input('fecha_desde')),
            'fecha_hasta' => self::fecha($request->input('fecha_hasta')),
            'qbe' => ListadoQbeSupport::resolverDesdeRequest(
                $request,
                self::camposQbe(),
                static fn (string $op, string $campo): string => self::normalizarOperador($op, $campo)
            ),
            'sort' => ListadoOrdenamientoSupport::resolverDesdeRequest($request, self::camposOrdenables()),
            'agrupar' => ListadoAgrupacionSupport::normalizar($request->input('group', []), self::camposOrdenables()),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function filtrosVacios(): array
    {
        return [
            'valor' => '',
            'sala_id' => 0,
            'fecha_desde' => '',
            'fecha_hasta' => '',
            'qbe' => ListadoQbeSupport::vacio(),
            'sort' => [],
            'agrupar' => [],
        ];
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    public static function tieneCriteriosAplicados(array $filtros): bool
    {
        if (trim((string) ($filtros['valor'] ?? '')) !== '') {
            return true;
        }
        if (trim((string) ($filtros['fecha_desde'] ?? '')) !== '') {
            return true;
        }
        if (trim((string) ($filtros['fecha_hasta'] ?? '')) !== '') {
            return true;
        }

        return ListadoQbeSupport::tieneCriterios((array) ($filtros['qbe'] ?? []))
            || ($filtros['agrupar'] ?? []) !== [];
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return array<string, string|int>
     */
    public static function paraQueryString(array $filtros): array
    {
        $params = [];
        if (trim((string) ($filtros['valor'] ?? '')) !== '') {
            $params['filtro_valor'] = $filtros['valor'];
        }
        if ((int) ($filtros['sala_id'] ?? 0) > 0) {
            $params['sala_id'] = (int) $filtros['sala_id'];
        }
        foreach (['fecha_desde', 'fecha_hasta'] as $clave) {
            $valor = trim((string) ($filtros[$clave] ?? ''));
            if ($valor !== '') {
                $params[$clave] = $valor;
            }
        }

        return array_merge(
            $params,
            ListadoQbeSupport::paraQueryString((array) ($filtros['qbe'] ?? [])),
            ListadoOrdenamientoSupport::paraQueryString((array) ($filtros['sort'] ?? [])),
            ListadoAgrupacionSupport::paraQueryString((array) ($filtros['agrupar'] ?? []))
        );
    }

    /**
     * @return array<string, array{column: string, type: string, label: string}>
     */
    public static function camposQbe(): array
    {
        return TesoreriaCrownResultadoOnlineListadoColumnas::camposFiltrables();
    }

    /**
     * @return array<string, array{label: string, type: string, column: string}>
     */
    public static function camposOrdenables(): array
    {
        return TesoreriaCrownResultadoOnlineListadoColumnas::camposOrdenables();
    }

    public static function normalizarOperador(string $op, string $campo): string
    {
        $tipo = self::camposQbe()[$campo]['type'] ?? 'texto';
        $validos = match ($tipo) {
            'fecha' => array_keys(ListadoQbeSupport::OPERADORES_FECHA),
            'decimal' => array_keys(ListadoQbeSupport::OPERADORES_DECIMAL),
            'entero' => ['igual', 'mayor', 'menor', 'mayor_igual', 'menor_igual', 'entre'],
            default => ['contiene', 'igual', 'empieza'],
        };

        return in_array($op, $validos, true) ? $op : match ($tipo) {
            'fecha' => 'entre',
            'decimal', 'entero' => 'igual',
            default => 'contiene',
        };
    }

    /**
     * @param  Builder<TesoreriaCrownResultadoOnline>  $query
     * @param  array<string, mixed>  $filtros
     */
    public static function aplicar(Builder $query, array $filtros): void
    {
        $salaId = (int) ($filtros['sala_id'] ?? 0);
        if ($salaId > 0) {
            $query->where('tesoreria_crown_resultado_online.sala_id', $salaId);
        } else {
            $query->whereRaw('1 = 0');
        }

        $desde = trim((string) ($filtros['fecha_desde'] ?? ''));
        if ($desde !== '') {
            $query->where('tesoreria_crown_resultado_online.fecha', '>=', $desde);
        }
        $hasta = trim((string) ($filtros['fecha_hasta'] ?? ''));
        if ($hasta !== '') {
            $query->where('tesoreria_crown_resultado_online.fecha', '<=', $hasta);
        }

        self::aplicarQbe($query, (array) ($filtros['qbe'] ?? []));

        $valor = trim((string) ($filtros['valor'] ?? ''));
        if ($valor !== '') {
            $fecha = self::fecha($valor) !== '' ? self::fecha($valor) : self::fechaDma($valor);
            $numero = self::numero($valor);
            $query->where(function (Builder $q) use ($fecha, $numero, $valor) {
                if ($fecha !== '') {
                    $q->orWhere('tesoreria_crown_resultado_online.fecha', $fecha);
                }
                if ($numero !== null && preg_match('/\d/', $valor)) {
                    foreach (['apostado', 'pagado', 'drop', 'resultado', 'poder_publico', 'slot_resultado'] as $columna) {
                        $q->orWhere('tesoreria_crown_resultado_online.'.$columna, $numero);
                    }
                    if (ctype_digit(str_replace(['.', ','], '', $valor))) {
                        $entero = (int) str_replace(['.', ','], '', $valor);
                        $q->orWhere('tesoreria_crown_resultado_online.attendance', $entero);
                        $q->orWhere('tesoreria_crown_resultado_online.cantidad_slots', $entero);
                    }
                }
                if ($fecha === '' && $numero === null) {
                    $q->whereRaw('1 = 0');
                }
            });
        }

        ListadoOrdenamientoSupport::aplicar(
            $query,
            (array) ($filtros['sort'] ?? []),
            self::camposOrdenables(),
            ['campo' => 'fecha', 'dir' => 'desc']
        );
        $query->orderByDesc('tesoreria_crown_resultado_online.id');
    }

    /**
     * @param  Builder<TesoreriaCrownResultadoOnline>  $query
     * @param  array<string, mixed>  $qbe
     */
    private static function aplicarQbe(Builder $query, array $qbe): void
    {
        if (! ListadoQbeSupport::tieneCriterios($qbe)) {
            return;
        }
        ListadoQbeSupport::aplicar($query, $qbe, static function (Builder $q, array $criterio, string $boolean = 'and'): void {
            if ($boolean === ListadoQbeSupport::LOGIC_OR) {
                $q->orWhere(function (Builder $inner) use ($criterio) {
                    self::aplicarCriterio($inner, $criterio);
                });

                return;
            }
            self::aplicarCriterio($q, $criterio);
        });
    }

    /**
     * @param  Builder<TesoreriaCrownResultadoOnline>  $query
     * @param  array<string, mixed>  $criterio
     */
    private static function aplicarCriterio(Builder $query, array $criterio): void
    {
        $campo = (string) ($criterio['campo'] ?? '');
        $def = self::camposQbe()[$campo] ?? null;
        if ($def === null) {
            return;
        }
        $op = (string) ($criterio['op'] ?? 'igual');
        $valor = trim((string) ($criterio['valor'] ?? ''));
        $hasta = trim((string) ($criterio['valor_hasta'] ?? ''));
        if ($valor === '' && $op !== 'vacio' && ! in_array($op, array_keys(ListadoQbeSupport::OPERADORES_FECHA), true)) {
            return;
        }
        $tipo = (string) ($def['type'] ?? '');
        if ($tipo === 'fecha') {
            ListadoQbeSupport::aplicarFecha($query, (string) $def['column'], $op, $valor, $hasta);

            return;
        }
        if ($tipo === 'decimal') {
            self::aplicarDecimal($query, (string) $def['column'], $op, $valor, $hasta);

            return;
        }
        if ($tipo === 'entero') {
            self::aplicarEntero($query, (string) $def['column'], $op, $valor, $hasta);
        }
    }

    private static function aplicarDecimal(Builder $query, string $column, string $op, string $valor, string $hasta): void
    {
        if ($op === 'vacio') {
            $query->whereNull($column);

            return;
        }
        $numero = self::numero($valor) ?? 0.0;
        $tope = self::numero($hasta) ?? $numero;
        match ($op) {
            'mayor' => $query->where($column, '>', $numero),
            'menor' => $query->where($column, '<', $numero),
            'mayor_igual' => $query->where($column, '>=', $numero),
            'menor_igual' => $query->where($column, '<=', $numero),
            'entre' => $query->whereBetween($column, [min($numero, $tope), max($numero, $tope)]),
            default => $query->where($column, $numero),
        };
    }

    private static function aplicarEntero(Builder $query, string $column, string $op, string $valor, string $hasta): void
    {
        $numero = (int) round(self::numero($valor) ?? 0);
        $tope = (int) round(self::numero($hasta) ?? $numero);
        match ($op) {
            'mayor' => $query->where($column, '>', $numero),
            'menor' => $query->where($column, '<', $numero),
            'mayor_igual' => $query->where($column, '>=', $numero),
            'menor_igual' => $query->where($column, '<=', $numero),
            'entre' => $query->whereBetween($column, [min($numero, $tope), max($numero, $tope)]),
            default => $query->where($column, $numero),
        };
    }

    private static function fecha(mixed $valor): string
    {
        $texto = trim((string) $valor);
        $dt = DateTime::createFromFormat('Y-m-d', $texto);

        return $dt && $dt->format('Y-m-d') === $texto ? $texto : '';
    }

    private static function fechaDma(string $valor): string
    {
        $dt = DateTime::createFromFormat('d/m/Y', $valor);

        return $dt && $dt->format('d/m/Y') === $valor ? $dt->format('Y-m-d') : '';
    }

    private static function numero(string $valor): ?float
    {
        $valor = trim($valor);
        if ($valor === '' || ! preg_match('/\d/', $valor)) {
            return null;
        }
        if (str_contains($valor, ',') && str_contains($valor, '.')) {
            $valor = str_replace('.', '', $valor);
            $valor = str_replace(',', '.', $valor);
        } elseif (str_contains($valor, ',')) {
            $valor = str_replace(',', '.', $valor);
        }

        return is_numeric($valor) ? (float) $valor : null;
    }
}
