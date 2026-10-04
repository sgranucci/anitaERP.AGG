<?php

declare(strict_types=1);

namespace App\Support\Finanzas;

use App\Models\Finanzas\FinanzaMovimientoPrecarga;
use App\Repositories\Configuracion\EmpresaRepository;
use App\Support\Listado\CoincidenciaFlexibleTexto;
use App\Support\Listado\ListadoOrdenamientoSupport;
use App\Support\Listado\ListadoQbeSupport;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class FinanzaMovimientoPrecargaListadoFiltros
{
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
    public const OPERADORES_FECHA = ListadoQbeSupport::OPERADORES_FECHA;

    /** @var array<string, string> */
    public const OPERADORES_DECIMAL = ListadoQbeSupport::OPERADORES_DECIMAL;

    /** @var array<string, string> */
    public const OPERADORES_BOOLEANO = [
        'igual' => 'Es',
    ];

    /**
     * @return array<string, array{column: string, type: string, label: string}>
     */
    public static function campos(): array
    {
        return [
            'id' => ['column' => 'finanza_movimiento_precarga.id', 'type' => 'entero', 'label' => 'ID'],
            'fecha' => ['column' => 'finanza_movimiento_precarga.fecha', 'type' => 'fecha', 'label' => 'Fecha'],
            'empresa' => ['column' => 'empresa.nombre', 'type' => 'texto', 'label' => 'Empresa'],
            'tipo' => ['column' => 'finanza_movimiento_precarga.tipo', 'type' => 'texto', 'label' => 'Tipo'],
            'rubro' => ['column' => 'finanza_movimiento_precarga.rubro', 'type' => 'texto', 'label' => 'Rubro'],
            'detalle' => ['column' => 'finanza_movimiento_precarga.detalle', 'type' => 'texto', 'label' => 'Detalle'],
            'monto' => ['column' => 'finanza_movimiento_precarga.monto', 'type' => 'decimal', 'label' => 'Monto'],
            'cotizacion' => ['column' => 'finanza_movimiento_precarga.cotizacion', 'type' => 'decimal', 'label' => 'Cotización'],
            'estado' => ['column' => 'finanza_movimiento_precarga.estado', 'type' => 'texto', 'label' => 'Estado'],
        ];
    }

    /**
     * @return array<string, array{column: string, type: string, label: string}>
     */
    public static function camposOrdenables(): array
    {
        $campos = self::campos();
        unset($campos['empresa']);

        return $campos;
    }

    /**
     * @return array<string, mixed>
     */
    public static function resolverDesdeRequest(Request $request): array
    {
        $campos = self::campos();
        $qbe = ListadoQbeSupport::resolverDesdeRequest(
            $request,
            $campos,
            static fn (string $op): string => $op
        );
        $sort = ListadoOrdenamientoSupport::resolverDesdeRequest($request, self::camposOrdenables());

        return [
            'qbe' => $qbe,
            'sort' => $sort,
            'empresa_id' => (int) $request->input('empresa_id', 0),
            'filtro_estado' => (string) $request->input('filtro_estado', 'todos'),
            'fecha_desde' => self::fecha($request->input('fecha_desde')),
            'fecha_hasta' => self::fecha($request->input('fecha_hasta')),
        ];
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return array<string, mixed>
     */
    public static function paraQueryString(array $filtros): array
    {
        $params = [];
        if ((int) ($filtros['empresa_id'] ?? 0) > 0) {
            $params['empresa_id'] = (int) $filtros['empresa_id'];
        }
        $estado = (string) ($filtros['filtro_estado'] ?? 'todos');
        if ($estado !== '' && $estado !== 'todos') {
            $params['filtro_estado'] = $estado;
        }
        if (($filtros['fecha_desde'] ?? '') !== '') {
            $params['fecha_desde'] = $filtros['fecha_desde'];
        }
        if (($filtros['fecha_hasta'] ?? '') !== '') {
            $params['fecha_hasta'] = $filtros['fecha_hasta'];
        }
        $qbe = ListadoQbeSupport::paraQueryString($filtros['qbe'] ?? []);
        $sort = ListadoOrdenamientoSupport::paraQueryString($filtros['sort'] ?? []);

        return array_merge($params, $qbe, $sort);
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    public static function tieneCriteriosAplicados(array $filtros): bool
    {
        if ((int) ($filtros['empresa_id'] ?? 0) > 0) {
            return true;
        }
        if ((string) ($filtros['filtro_estado'] ?? 'todos') !== 'todos') {
            return true;
        }
        if (($filtros['fecha_desde'] ?? '') !== '' || ($filtros['fecha_hasta'] ?? '') !== '') {
            return true;
        }

        return ListadoQbeSupport::tieneCriterios($filtros['qbe'] ?? []);
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return Builder<FinanzaMovimientoPrecarga>
     */
    public static function query(array $filtros): Builder
    {
        $query = FinanzaMovimientoPrecarga::query()
            ->with([
                'empresa:id,nombre',
                'moneda:id,nombre,abreviatura',
                'cuentacaja:id,codigo,nombre',
                'cuentacajaDesde:id,codigo,nombre',
                'cuentacajaHasta:id,codigo,nombre',
                'cajaMovimiento:id,numerotransaccion,caja_movimiento_revertido_por_id',
            ]);

        app(EmpresaRepository::class)->aplicarFiltroEmpresasAsignadas($query, 'finanza_movimiento_precarga.empresa_id');

        $empresaId = (int) ($filtros['empresa_id'] ?? 0);
        if ($empresaId > 0) {
            $query->where('finanza_movimiento_precarga.empresa_id', $empresaId);
        }

        $desde = (string) ($filtros['fecha_desde'] ?? '');
        $hasta = (string) ($filtros['fecha_hasta'] ?? '');
        if ($desde !== '') {
            $query->whereDate('finanza_movimiento_precarga.fecha', '>=', $desde);
        }
        if ($hasta !== '') {
            $query->whereDate('finanza_movimiento_precarga.fecha', '<=', $hasta);
        }

        $estado = (string) ($filtros['filtro_estado'] ?? 'todos');
        if ($estado === 'abierto') {
            $query->where(function (Builder $q) {
                $q->where('finanza_movimiento_precarga.estado', FinanzaMovimientoPrecarga::ESTADO_ABIERTO)
                    ->orWhereHas('cajaMovimiento', function (Builder $m) {
                        $m->where('caja_movimiento_revertido_por_id', '>', 0);
                    });
            });
        } elseif ($estado === 'convertido') {
            $query->where('finanza_movimiento_precarga.estado', FinanzaMovimientoPrecarga::ESTADO_CONVERTIDO)
                ->where(function (Builder $q) {
                    $q->whereDoesntHave('cajaMovimiento')
                        ->orWhereHas('cajaMovimiento', function (Builder $m) {
                            $m->where(function (Builder $w) {
                                $w->whereNull('caja_movimiento_revertido_por_id')
                                    ->orWhere('caja_movimiento_revertido_por_id', 0);
                            });
                        });
                });
        }

        $qbe = $filtros['qbe'] ?? [];
        if (is_array($qbe) && ListadoQbeSupport::tieneCriterios($qbe)) {
            ListadoQbeSupport::aplicar($query, $qbe, function (Builder $q, array $criterio, string $boolean) {
                if ($boolean === ListadoQbeSupport::LOGIC_OR) {
                    $q->orWhere(function (Builder $inner) use ($criterio) {
                        self::aplicarCriterio($inner, $criterio);
                    });

                    return;
                }
                self::aplicarCriterio($q, $criterio);
            });
        }

        ListadoOrdenamientoSupport::aplicar(
            $query,
            $filtros['sort'] ?? [],
            self::camposOrdenables(),
            ['campo' => 'fecha', 'dir' => 'desc']
        );
        $query->orderByDesc('finanza_movimiento_precarga.id');

        return $query;
    }

    /**
     * @param  Builder<FinanzaMovimientoPrecarga>  $query
     * @param  array{campo: string, op: string, valor: string, valor_hasta?: string}  $criterio
     */
    private static function aplicarCriterio(Builder $query, array $criterio): void
    {
        $campo = (string) ($criterio['campo'] ?? '');
        $def = self::campos()[$campo] ?? null;
        if ($def === null) {
            return;
        }
        $op = (string) ($criterio['op'] ?? 'contiene');
        $valor = (string) ($criterio['valor'] ?? '');
        $hasta = (string) ($criterio['valor_hasta'] ?? '');
        $type = (string) $def['type'];
        $column = (string) $def['column'];

        if ($campo === 'empresa') {
            $query->whereHas('empresa', function (Builder $q) use ($op, $valor, $hasta) {
                self::aplicarTexto($q, 'nombre', $op, $valor, $hasta);
            });

            return;
        }
        if ($type === 'fecha') {
            ListadoQbeSupport::aplicarFecha($query, $column, $op, $valor, $hasta);

            return;
        }
        if ($type === 'decimal') {
            ListadoQbeSupport::aplicarDecimal($query, $column, $op, $valor, $hasta);

            return;
        }
        if ($type === 'entero') {
            self::aplicarEntero($query, $column, $op, $valor, $hasta);

            return;
        }
        self::aplicarTexto($query, $column, $op, $valor, $hasta);
    }

    /**
     * @param  Builder<FinanzaMovimientoPrecarga>  $query
     */
    private static function aplicarTexto(Builder $query, string $column, string $operador, string $valor, string $valorHasta): void
    {
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
            if (trim($valorHasta) !== '') {
                $query->where($column, '<=', $valorHasta);
            }

            return;
        }
        if ($valor === '') {
            return;
        }
        $like = self::escapeLike($valor);
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
                    if ($column === 'finanza_movimiento_precarga.detalle') {
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
     * @param  Builder<FinanzaMovimientoPrecarga>  $query
     */
    private static function aplicarEntero(Builder $query, string $column, string $operador, string $valor, string $valorHasta): void
    {
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
        $op = match ($operador) {
            'mayor' => '>',
            'mayor_igual' => '>=',
            'menor' => '<',
            'menor_igual' => '<=',
            'distinto' => '!=',
            default => '=',
        };
        $query->where($column, $op, $n);
    }

    private static function escapeLike(string $valor): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $valor);
    }

    private static function fecha(mixed $valor): string
    {
        $texto = trim((string) $valor);
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $texto) === 1) {
            return $texto;
        }

        return '';
    }
}
