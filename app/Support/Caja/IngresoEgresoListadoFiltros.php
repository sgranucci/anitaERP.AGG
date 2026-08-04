<?php

namespace App\Support\Caja;

use App\Support\Listado\CoincidenciaFlexibleTexto;
use App\Support\Listado\FiltrosListadoRequest;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * Filtros del listado de ingresos y egresos de caja (index).
 */
class IngresoEgresoListadoFiltros
{
    public const MODO_TODOS = 'todos';

    public const MODO_CAMPO = 'campo';

    /** @var array<string, array{column: string, type: string, label: string}> */
    public const CAMPOS = [
        'id' => ['column' => 'caja_movimiento.id', 'type' => 'entero', 'label' => 'ID'],
        'numero' => ['column' => 'caja_movimiento.numerotransaccion', 'type' => 'entero', 'label' => 'Número'],
        'fecha' => ['column' => 'caja_movimiento.fecha', 'type' => 'texto', 'label' => 'Fecha'],
        'empresa' => ['column' => 'empresa.nombre', 'type' => 'texto', 'label' => 'Empresa'],
        'tipotransaccion' => ['column' => 'tipotransaccion_caja.nombre', 'type' => 'texto', 'label' => 'Tipo de transacción'],
        'concepto' => ['column' => 'conceptogasto.nombre', 'type' => 'texto', 'label' => 'Concepto'],
        'detalle' => ['column' => 'caja_movimiento.detalle', 'type' => 'texto', 'label' => 'Detalle'],
        'ordenservicio' => ['column' => 'caja_movimiento.ordenservicio_id', 'type' => 'entero', 'label' => 'Orden de servicio'],
    ];

    /** @var list<string> */
    private const COLUMNAS_COINCIDENCIA_FLEXIBLE = [
        'empresa.nombre',
        'tipotransaccion_caja.nombre',
        'conceptogasto.nombre',
        'caja_movimiento.detalle',
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

    public static function resolverDesdeRequest(Request $request, ?string $busquedaRuta = null, ?int $empresaDefault = null): array
    {
        [$empresaId, $empresaScope] = self::resolverEmpresaExterna($request, $empresaDefault);

        if (FiltrosListadoRequest::solicitudLimpiaFiltros($request)) {
            return array_merge(self::filtrosVacios(), [
                'empresa_id' => $empresaId,
                'empresa_scope' => $empresaScope,
            ]);
        }

        $valor = FiltrosListadoRequest::valorBusqueda($request, $busquedaRuta);
        if ($valor === '' && $request->has('busqueda') && ! $request->has('filtro_valor')) {
            $valor = trim((string) $request->input('busqueda', ''));
        }
        if ($valor === '' && is_string($busquedaRuta) && $busquedaRuta !== '') {
            $valor = trim($busquedaRuta);
        }

        $busquedaRapida = $request->boolean('filtro_busqueda_rapida');

        $modo = (string) $request->input('filtro_modo', self::MODO_TODOS);
        if (! in_array($modo, [self::MODO_TODOS, self::MODO_CAMPO], true)) {
            $modo = self::MODO_TODOS;
        }

        $campo = (string) $request->input('filtro_campo', 'detalle');
        if (! isset(self::CAMPOS[$campo])) {
            $campo = 'detalle';
        }

        $operador = (string) $request->input('filtro_operador', 'contiene');

        if ($busquedaRapida) {
            $modo = self::MODO_TODOS;
            $operador = 'contiene';
        }

        $operador = self::normalizarOperador($operador, $modo === self::MODO_CAMPO ? $campo : 'detalle');

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
            'fecha_desde' => trim((string) $request->input('fecha_desde', '')),
            'fecha_hasta' => trim((string) $request->input('fecha_hasta', '')),
        ];
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

        if (trim((string) ($filtros['fecha_desde'] ?? '')) !== '') {
            return true;
        }

        if (trim((string) ($filtros['fecha_hasta'] ?? '')) !== '') {
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

    public static function filtrosVacios(?int $empresaDefault = null): array
    {
        $base = [
            'modo' => self::MODO_TODOS,
            'campo' => 'detalle',
            'operador' => 'contiene',
            'valor' => '',
            'valor_hasta' => '',
            'busqueda' => '',
            'busqueda_rapida' => false,
            'fecha_desde' => '',
            'fecha_hasta' => '',
            'empresa_id' => null,
            'empresa_scope' => 'todas',
        ];

        if ($empresaDefault !== null && $empresaDefault > 0) {
            $base['empresa_id'] = $empresaDefault;
            $base['empresa_scope'] = 'una';
        }

        return $base;
    }

    /**
     * @return array<string, string|int>
     */
    public static function paraQueryString(array $filtros): array
    {
        $params = self::paraQueryStringEmpresa($filtros);

        if (($filtros['modo'] ?? self::MODO_TODOS) !== self::MODO_TODOS) {
            $params['filtro_modo'] = $filtros['modo'];
        }
        if (($filtros['modo'] ?? '') === self::MODO_CAMPO) {
            $params['filtro_campo'] = $filtros['campo'] ?? 'detalle';
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
        if (! empty($filtros['fecha_desde'])) {
            $params['fecha_desde'] = $filtros['fecha_desde'];
        }
        if (! empty($filtros['fecha_hasta'])) {
            $params['fecha_hasta'] = $filtros['fecha_hasta'];
        }

        return $params;
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
     * @param  Builder<\App\Models\Caja\Caja_Movimiento>  $query
     */
    public static function aplicar(Builder $query, array $filtros): void
    {
        if (! empty($filtros['empresa_id'])) {
            $query->where('caja_movimiento.empresa_id', (int) $filtros['empresa_id']);
        }

        if (($filtros['fecha_desde'] ?? '') !== '') {
            $query->whereDate('caja_movimiento.fecha', '>=', $filtros['fecha_desde']);
        }
        if (($filtros['fecha_hasta'] ?? '') !== '') {
            $query->whereDate('caja_movimiento.fecha', '<=', $filtros['fecha_hasta']);
        }

        if (! self::tieneCriteriosTextoParaBusqueda($filtros)) {
            return;
        }

        $valor = trim((string) ($filtros['valor'] ?? ''));
        $modo = $filtros['modo'] ?? self::MODO_TODOS;
        $operador = $filtros['operador'] ?? 'contiene';

        if ($modo === self::MODO_CAMPO) {
            self::aplicarEnCampo($query, $filtros['campo'] ?? 'detalle', $operador, $valor);

            return;
        }

        self::aplicarBusquedaGlobal($query, $operador, $valor);
    }

    /**
     * Criterios de texto/campo (sin rango de fechas, que ya se aplicó aparte).
     */
    private static function tieneCriteriosTextoParaBusqueda(array $filtros): bool
    {
        if (($filtros['operador'] ?? '') === 'vacio') {
            return true;
        }
        if (trim((string) ($filtros['valor'] ?? '')) !== '') {
            return true;
        }
        if (($filtros['modo'] ?? self::MODO_TODOS) === self::MODO_CAMPO
            && ($filtros['operador'] ?? 'contiene') !== 'contiene') {
            return true;
        }

        return false;
    }

    /**
     * @param  Builder<\App\Models\Caja\Caja_Movimiento>  $query
     */
    private static function aplicarBusquedaGlobal(Builder $query, string $operador, string $valor): void
    {
        if ($operador === 'vacio') {
            $query->where(function ($q) {
                foreach (['caja_movimiento.detalle', 'conceptogasto.nombre'] as $col) {
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
        $esIguassu = config('app.empresa') === 'Iguassu Travel';

        $query->where(function ($q) use ($valor, $like, $id, $operador, $esIguassu) {
            if ($id !== false) {
                $q->orWhere('caja_movimiento.id', (int) $id)
                    ->orWhere('caja_movimiento.numerotransaccion', (int) $id);
                if ($esIguassu) {
                    $q->orWhere('caja_movimiento.ordenservicio_id', (int) $id);
                }
            }

            $textCols = [
                'empresa.nombre',
                'tipotransaccion_caja.nombre',
                'caja_movimiento.detalle',
                'conceptogasto.nombre',
                'caja_movimiento.fecha',
            ];
            foreach ($textCols as $col) {
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

            if ($operador === 'igual' || $operador === 'contiene') {
                $q->orWhere('caja_movimiento.numerotransaccion', $valor);
            }
        });
    }

    /**
     * @param  Builder<\App\Models\Caja\Caja_Movimiento>  $query
     */
    private static function aplicarEnCampo(Builder $query, string $campoKey, string $operador, string $valor): void
    {
        $def = self::CAMPOS[$campoKey] ?? self::CAMPOS['detalle'];
        $type = $def['type'];

        if ($type === 'entero') {
            self::aplicarEntero($query, (string) $def['column'], $operador, $valor);

            return;
        }

        self::aplicarTexto($query, (string) $def['column'], $operador, $valor);
    }

    /**
     * @param  Builder<\App\Models\Caja\Caja_Movimiento>  $query
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
     * @param  Builder<\App\Models\Caja\Caja_Movimiento>  $query
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

    private static function usaCoincidenciaFlexibleEnColumna(string $column): bool
    {
        return in_array($column, self::COLUMNAS_COINCIDENCIA_FLEXIBLE, true);
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
        $permitidos = match ($type) {
            'entero' => array_keys(self::OPERADORES_ENTERO),
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
            default => self::OPERADORES_TEXTO,
        };
    }

    /**
     * Campos visibles en el panel (oculta OS fuera de Iguassu).
     *
     * @return array<string, array{column: string, type: string, label: string}>
     */
    public static function camposParaVista(): array
    {
        $campos = self::CAMPOS;
        if (config('app.empresa') !== 'Iguassu Travel') {
            unset($campos['ordenservicio']);
        }

        return $campos;
    }
}
