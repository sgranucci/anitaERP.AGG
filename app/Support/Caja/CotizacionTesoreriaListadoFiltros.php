<?php

namespace App\Support\Caja;

use App\Support\Listado\FiltrosListadoRequest;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * Filtros del listado de cotización tesorería (index / exportaciones).
 */
class CotizacionTesoreriaListadoFiltros
{
    public const MODO_TODOS = 'todos';

    public const MODO_CAMPO = 'campo';

    /** @var array<string, array{column: string, type: string, label: string}> */
    public const CAMPOS = [
        'id' => ['column' => 'cotizacion_tesoreria.id', 'type' => 'entero', 'label' => 'ID'],
        'empresa' => ['column' => 'empresa.nombre', 'type' => 'texto', 'label' => 'Empresa'],
        'empresa_id' => ['column' => 'cotizacion_tesoreria.empresa_id', 'type' => 'entero', 'label' => 'ID empresa'],
        'fecha' => ['column' => 'cotizacion_tesoreria.fecha', 'type' => 'fecha', 'label' => 'Fecha'],
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
        'contiene' => 'Contiene (año o parte)',
        'mayor' => 'Mayor que',
        'menor' => 'Menor que',
        'vacio' => 'Vacío',
    ];

    public static function resolverDesdeRequest(
        Request $request,
        ?string $busquedaRuta = null,
        ?int $empresaDefault = null,
    ): array {
        [$empresaId, $empresaScope] = self::resolverEmpresaExterna($request, $empresaDefault);

        if (FiltrosListadoRequest::solicitudLimpiaFiltros($request)) {
            return array_merge(self::filtrosVacios(), [
                'empresa_id' => $empresaId,
                'empresa_scope' => $empresaScope,
            ]);
        }

        $valor = FiltrosListadoRequest::valorBusqueda($request, $busquedaRuta);
        $busquedaRapida = $request->boolean('filtro_busqueda_rapida');

        $modo = (string) $request->input('filtro_modo', self::MODO_TODOS);
        if (! in_array($modo, [self::MODO_TODOS, self::MODO_CAMPO], true)) {
            $modo = self::MODO_TODOS;
        }

        $campo = (string) $request->input('filtro_campo', 'fecha');
        if (! isset(self::CAMPOS[$campo])) {
            $campo = 'fecha';
        }

        $operador = (string) $request->input('filtro_operador', 'contiene');

        if ($busquedaRapida) {
            $modo = self::MODO_TODOS;
            $operador = 'contiene';
        }

        $operador = self::normalizarOperador($operador, $modo === self::MODO_CAMPO ? $campo : 'fecha');

        return [
            'modo' => $modo,
            'campo' => $campo,
            'operador' => $operador,
            'valor' => $valor,
            'valor_hasta' => trim((string) $request->input('filtro_valor_hasta', '')),
            'busqueda' => $valor,
            'busqueda_rapida' => $busquedaRapida,
            'empresa_id' => $empresaId,
            'empresa_scope' => $empresaScope,
        ];
    }

    /**
     * @return array{0:?int,1:string}
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

    /**
     * Criterios del panel / búsqueda (sin el filtro externo de empresa).
     */
    public static function tieneCriteriosTexto(array $filtros): bool
    {
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
        return self::tieneCriteriosTexto($filtros);
    }

    /**
     * @return array<string, mixed>
     */
    public static function filtrosVacios(): array
    {
        return [
            'modo' => self::MODO_TODOS,
            'campo' => 'fecha',
            'operador' => 'contiene',
            'valor' => '',
            'valor_hasta' => '',
            'busqueda' => '',
            'empresa_id' => null,
            'empresa_scope' => 'una',
            'empresas_asignadas' => [],
        ];
    }

    /**
     * @return array<string, string|int|bool>
     */
    public static function paraQueryString(array $filtros): array
    {
        $params = self::paraQueryStringEmpresa($filtros);
        if (($filtros['modo'] ?? self::MODO_TODOS) !== self::MODO_TODOS) {
            $params['filtro_modo'] = $filtros['modo'];
        }
        if (($filtros['modo'] ?? '') === self::MODO_CAMPO) {
            $params['filtro_campo'] = $filtros['campo'] ?? 'fecha';
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
        if (($filtros['modo'] ?? '') === self::MODO_TODOS && trim((string) ($filtros['valor'] ?? '')) !== '') {
            $params['filtro_busqueda_rapida'] = 1;
        }

        return $params;
    }

    /**
     * Solo el filtro externo de empresa (Limpiar texto sin perder empresa).
     *
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
     * @param  Builder<\App\Models\Caja\CotizacionTesoreria>  $query
     */
    public static function aplicar(Builder $query, array $filtros): void
    {
        if (($filtros['empresa_scope'] ?? 'una') !== 'todas' && ! empty($filtros['empresa_id'])) {
            $query->where('cotizacion_tesoreria.empresa_id', (int) $filtros['empresa_id']);
        } elseif (! empty($filtros['empresas_asignadas']) && is_array($filtros['empresas_asignadas'])) {
            $query->whereIn('cotizacion_tesoreria.empresa_id', $filtros['empresas_asignadas']);
        }

        $valor = trim((string) ($filtros['valor'] ?? ''));
        if ($valor === '' && ($filtros['operador'] ?? '') !== 'vacio') {
            return;
        }

        $modo = $filtros['modo'] ?? self::MODO_TODOS;
        $operador = $filtros['operador'] ?? 'contiene';

        if ($modo === self::MODO_CAMPO) {
            self::aplicarEnCampo($query, $filtros['campo'] ?? 'fecha', $operador, $valor);

            return;
        }

        self::aplicarBusquedaGlobal($query, $operador, $valor);
    }

    /**
     * @param  Builder<\App\Models\Caja\CotizacionTesoreria>  $query
     */
    private static function aplicarBusquedaGlobal(Builder $query, string $operador, string $valor): void
    {
        if ($operador === 'vacio') {
            $query->where(function ($q) {
                $q->whereNull('cotizacion_tesoreria.fecha')->orWhere('cotizacion_tesoreria.fecha', '');
            });

            return;
        }

        if ($valor === '') {
            return;
        }

        $id = filter_var($valor, FILTER_VALIDATE_INT);
        $fecha = self::normalizarFecha($valor);
        $like = self::patronLike($operador, $valor);

        $query->where(function ($q) use ($valor, $like, $id, $fecha, $operador) {
            if ($id !== false && strlen($valor) <= 8) {
                $q->orWhere('cotizacion_tesoreria.id', (int) $id);
                $q->orWhere('cotizacion_tesoreria.empresa_id', (int) $id);
            }
            if ($fecha !== null) {
                if ($operador === 'igual') {
                    $q->orWhereDate('cotizacion_tesoreria.fecha', $fecha);
                } else {
                    $q->orWhere('cotizacion_tesoreria.fecha', 'like', $like);
                    $q->orWhereDate('cotizacion_tesoreria.fecha', $fecha);
                }
            } else {
                $q->orWhere('cotizacion_tesoreria.fecha', 'like', $like);
            }
            if (preg_match('/^\d{4}$/', $valor)) {
                $q->orWhere('cotizacion_tesoreria.fecha', 'like', $valor.'-%');
            }
            if (preg_match('/^\d{8}$/', $valor)) {
                $q->orWhere('cotizacion_tesoreria.fecha_anita', (int) $valor);
            }
            $q->orWhere('empresa.nombre', 'like', $like);
        });
    }

    /**
     * @param  Builder<\App\Models\Caja\CotizacionTesoreria>  $query
     */
    private static function aplicarEnCampo(Builder $query, string $campoKey, string $operador, string $valor): void
    {
        $def = self::CAMPOS[$campoKey] ?? self::CAMPOS['fecha'];
        $type = $def['type'];

        if ($type === 'entero') {
            self::aplicarEntero($query, (string) $def['column'], $operador, $valor);

            return;
        }

        if ($type === 'texto') {
            self::aplicarTexto($query, (string) $def['column'], $operador, $valor);

            return;
        }

        self::aplicarFecha($query, (string) $def['column'], $operador, $valor);
    }

    /**
     * @param  Builder<\App\Models\Caja\CotizacionTesoreria>  $query
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

        $like = self::patronLike($operador, $valor);
        switch ($operador) {
            case 'igual':
                $query->where($column, '=', $valor);
                break;
            case 'distinto':
                $query->where($column, '!=', $valor);
                break;
            case 'empieza':
            case 'termina':
            case 'contiene':
            default:
                $query->where($column, 'like', $like);
                break;
        }
    }

    /**
     * @param  Builder<\App\Models\Caja\CotizacionTesoreria>  $query
     */
    private static function aplicarFecha(Builder $query, string $column, string $operador, string $valor): void
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

        $fecha = self::normalizarFecha($valor);

        switch ($operador) {
            case 'mayor':
                if ($fecha !== null) {
                    $query->whereDate($column, '>', $fecha);
                }
                break;
            case 'menor':
                if ($fecha !== null) {
                    $query->whereDate($column, '<', $fecha);
                }
                break;
            case 'igual':
                if ($fecha !== null) {
                    $query->whereDate($column, '=', $fecha);
                } elseif (preg_match('/^\d{4}$/', $valor)) {
                    $query->where($column, 'like', $valor.'-%');
                }
                break;
            case 'contiene':
            default:
                if (preg_match('/^\d{4}$/', $valor)) {
                    $query->where($column, 'like', $valor.'-%');
                } elseif ($fecha !== null) {
                    $query->whereDate($column, '=', $fecha);
                } else {
                    $query->where($column, 'like', '%'.self::escapeLike($valor).'%');
                }
                break;
        }
    }

    /**
     * @param  Builder<\App\Models\Caja\CotizacionTesoreria>  $query
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
     * @return array<string, string>
     */
    public static function operadoresParaCampo(string $campoKey): array
    {
        $type = self::CAMPOS[$campoKey]['type'] ?? 'fecha';

        return match ($type) {
            'entero' => self::OPERADORES_ENTERO,
            'fecha' => self::OPERADORES_FECHA,
            default => self::OPERADORES_TEXTO,
        };
    }

    private static function normalizarOperador(string $operador, string $campoKey): string
    {
        $ops = self::operadoresParaCampo($campoKey);
        if (! isset($ops[$operador])) {
            return array_key_first($ops) ?: 'contiene';
        }

        return $operador;
    }

    private static function normalizarFecha(string $valor): ?string
    {
        $valor = trim($valor);
        if ($valor === '') {
            return null;
        }

        foreach (['d/m/Y', 'Y-m-d', 'd-m-Y', 'Ymd'] as $fmt) {
            try {
                $c = Carbon::createFromFormat($fmt, $valor);
                if ($c !== false) {
                    return $c->format('Y-m-d');
                }
            } catch (\Throwable) {
            }
        }

        try {
            return Carbon::parse($valor)->format('Y-m-d');
        } catch (\Throwable) {
            return null;
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

    private static function escapeLike(string $valor): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $valor);
    }
}
