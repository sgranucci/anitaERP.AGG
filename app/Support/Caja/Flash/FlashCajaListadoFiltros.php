<?php

namespace App\Support\Caja\Flash;

use App\Support\Listado\CoincidenciaFlexibleTexto;
use App\Support\Listado\FiltrosListadoRequest;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class FlashCajaListadoFiltros
{
    public const MODO_TODOS = 'todos';

    public const MODO_CAMPO = 'campo';

    /** @var array<string, array{column: string, type: string, label: string}> */
    public const CAMPOS = [
        'id' => ['column' => 'flash_caja.id', 'type' => 'entero', 'label' => 'ID'],
        'fecha' => ['column' => 'flash_caja.fecha', 'type' => 'fecha', 'label' => 'Fecha'],
        'empresa' => ['column' => 'empresa.nombre', 'type' => 'texto', 'label' => 'Empresa'],
        'ayb' => ['column' => 'flash_caja.ayb', 'type' => 'decimal', 'label' => 'AyB'],
        'estac' => ['column' => 'flash_caja.estac', 'type' => 'decimal', 'label' => 'Estacionamiento'],
        'bingo_total_venta' => ['column' => 'flash_caja.bingo_total_venta', 'type' => 'decimal', 'label' => 'Bingo venta'],
        'comentario' => ['column' => 'flash_caja.comentario', 'type' => 'texto', 'label' => 'Comentario'],
    ];

    /** @var list<string> */
    private const COLUMNAS_COINCIDENCIA_FLEXIBLE = [
        'empresa.nombre',
        'flash_caja.comentario',
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
    public const OPERADORES_DECIMAL = [
        'igual' => 'Igual a',
        'mayor' => 'Mayor que',
        'menor' => 'Menor que',
    ];

    public static function resolverDesdeRequest(Request $request, ?string $busquedaRuta = null, ?int $empresaDefault = null): array
    {
        [$empresaId, $empresaScope] = self::resolverEmpresaExterna($request, $empresaDefault);

        if (FiltrosListadoRequest::solicitudLimpiaFiltros($request)) {
            return array_merge(self::filtrosVacios(), [
                'empresa_id' => $empresaId ?? 0,
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
            'empresa_id' => $empresaId ?? 0,
            'empresa_scope' => $empresaScope,
            'empresas_asignadas' => [],
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

    public static function tieneCriteriosAplicados(array $filtros): bool
    {
        return self::tieneCriteriosTexto($filtros);
    }

    /**
     * Criterios del panel / búsqueda rápida (sin el filtro externo de empresa).
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

    /** @return array<string, mixed> */
    public static function filtrosVacios(): array
    {
        return [
            'modo' => self::MODO_TODOS,
            'campo' => 'fecha',
            'operador' => 'contiene',
            'valor' => '',
            'valor_hasta' => '',
            'busqueda' => '',
            'empresa_id' => 0,
            'empresa_scope' => 'una',
            'empresas_asignadas' => [],
        ];
    }

    /** @return array<string, string|int|bool> */
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

        return $params;
    }

    /**
     * Solo el filtro externo de empresa (para Limpiar texto sin perder empresa).
     *
     * @return array<string, int>
     */
    public static function paraQueryStringEmpresa(array $filtros): array
    {
        if (($filtros['empresa_scope'] ?? 'una') === 'todas') {
            return ['empresa_todas' => 1];
        }
        if (($filtros['empresa_id'] ?? 0) > 0) {
            return ['empresa_id' => (int) $filtros['empresa_id']];
        }

        return [];
    }

    /**
     * @param  Builder<\App\Models\Caja\Flash\FlashCaja>  $query
     * @param  array<string, mixed>  $filtros
     */
    public static function aplicarScopeEmpresasAsignadas(Builder $query, array $filtros): void
    {
        $empresaId = (int) ($filtros['empresa_id'] ?? 0);
        if ($empresaId > 0) {
            $query->where('flash_caja.empresa_id', $empresaId);

            return;
        }

        $asignadas = array_values(array_filter(
            array_map('intval', (array) ($filtros['empresas_asignadas'] ?? [])),
            fn (int $id) => $id > 0,
        ));

        if ($asignadas === []) {
            return;
        }

        $query->whereIn('flash_caja.empresa_id', $asignadas);
    }

    /**
     * @param  Builder<\App\Models\Caja\Flash\FlashCaja>  $query
     */
    public static function aplicar(Builder $query, array $filtros): void
    {
        $valor = trim((string) ($filtros['valor'] ?? ''));
        if ($valor === '' && ($filtros['operador'] ?? '') !== 'vacio') {
            return;
        }

        $modo = $filtros['modo'] ?? self::MODO_TODOS;
        $operador = $filtros['operador'] ?? 'contiene';

        if ($modo === self::MODO_CAMPO) {
            self::aplicarEnCampo($query, $filtros['campo'] ?? 'fecha', $operador, $valor, $filtros['valor_hasta'] ?? '');

            return;
        }

        self::aplicarBusquedaGlobal($query, $operador, $valor);
    }

    /**
     * @param  Builder<\App\Models\Caja\Flash\FlashCaja>  $query
     */
    private static function aplicarBusquedaGlobal(Builder $query, string $operador, string $valor): void
    {
        if ($operador === 'vacio') {
            $query->where(function ($q) {
                foreach (['flash_caja.comentario'] as $col) {
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
                $q->orWhere('flash_caja.id', (int) $id);
            }
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $valor)) {
                $q->orWhereDate('flash_caja.fecha', $valor);
            }
            if (preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $valor)) {
                $partes = explode('/', $valor);
                $q->orWhereDate('flash_caja.fecha', $partes[2].'-'.$partes[1].'-'.$partes[0]);
            }
            foreach (['empresa.nombre', 'flash_caja.comentario'] as $col) {
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
        });
    }

    /**
     * @param  Builder<\App\Models\Caja\Flash\FlashCaja>  $query
     */
    private static function aplicarEnCampo(
        Builder $query,
        string $campo,
        string $operador,
        string $valor,
        string $valorHasta,
    ): void {
        $def = self::CAMPOS[$campo] ?? self::CAMPOS['fecha'];
        $column = $def['column'];
        $type = $def['type'];

        if ($operador === 'vacio') {
            $query->where(function ($q) use ($column) {
                $q->whereNull($column)->orWhere($column, '');
            });

            return;
        }

        if ($valor === '' && $type !== 'fecha') {
            return;
        }

        match ($type) {
            'entero' => self::aplicarEntero($query, $column, $operador, $valor, $valorHasta),
            'decimal' => self::aplicarDecimal($query, $column, $operador, $valor, $valorHasta),
            'fecha' => self::aplicarFecha($query, $column, $operador, $valor, $valorHasta),
            default => self::aplicarTexto($query, $column, $operador, $valor),
        };
    }

    /** @param  Builder<\App\Models\Caja\Flash\FlashCaja>  $query */
    private static function aplicarTexto(Builder $query, string $column, string $operador, string $valor): void
    {
        if ($valor === '') {
            return;
        }

        $like = self::patronLike($operador, $valor);
        $query->where(function ($q) use ($column, $like, $operador, $valor) {
            if ($operador === 'distinto') {
                $q->where($column, 'not like', $like);
            } else {
                $q->where($column, 'like', $like);
            }
            if ($operador === 'contiene' && self::usaCoincidenciaFlexibleEnColumna($column)) {
                CoincidenciaFlexibleTexto::aplicar(
                    $q,
                    $column,
                    $valor,
                    $operador !== 'distinto',
                    CoincidenciaFlexibleTexto::LONGITUD_MINIMA_DEFAULT
                );
            }
        });
    }

    /** @param  Builder<\App\Models\Caja\Flash\FlashCaja>  $query */
    private static function aplicarEntero(
        Builder $query,
        string $column,
        string $operador,
        string $valor,
        string $valorHasta,
    ): void {
        $num = filter_var($valor, FILTER_VALIDATE_INT);
        if ($num === false) {
            return;
        }

        match ($operador) {
            'mayor' => $query->where($column, '>', $num),
            'menor' => $query->where($column, '<', $num),
            'distinto' => $query->where($column, '!=', $num),
            default => $query->where($column, '=', $num),
        };
    }

    /** @param  Builder<\App\Models\Caja\Flash\FlashCaja>  $query */
    private static function aplicarDecimal(
        Builder $query,
        string $column,
        string $operador,
        string $valor,
        string $valorHasta,
    ): void {
        $num = (float) str_replace(',', '.', trim($valor));
        match ($operador) {
            'mayor' => $query->where($column, '>', $num),
            'menor' => $query->where($column, '<', $num),
            'distinto' => $query->where($column, '!=', $num),
            default => $query->where($column, '=', $num),
        };
    }

    /** @param  Builder<\App\Models\Caja\Flash\FlashCaja>  $query */
    private static function aplicarFecha(
        Builder $query,
        string $column,
        string $operador,
        string $valor,
        string $valorHasta,
    ): void {
        $fecha = self::normalizarFecha($valor);
        if ($fecha === null) {
            return;
        }

        match ($operador) {
            'mayor' => $query->whereDate($column, '>', $fecha),
            'menor' => $query->whereDate($column, '<', $fecha),
            'distinto' => $query->whereDate($column, '!=', $fecha),
            default => $query->whereDate($column, '=', $fecha),
        };
    }

    private static function normalizarFecha(string $valor): ?string
    {
        $valor = trim($valor);
        if ($valor === '') {
            return null;
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $valor)) {
            return $valor;
        }
        if (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $valor, $m)) {
            return $m[3].'-'.$m[2].'-'.$m[1];
        }

        return null;
    }

    private static function patronLike(string $operador, string $valor): string
    {
        return match ($operador) {
            'empieza' => $valor.'%',
            'termina' => '%'.$valor,
            'igual' => $valor,
            'distinto' => $valor,
            default => '%'.$valor.'%',
        };
    }

    private static function usaCoincidenciaFlexibleEnColumna(string $column): bool
    {
        return in_array($column, self::COLUMNAS_COINCIDENCIA_FLEXIBLE, true);
    }

    public static function operadoresParaCampo(string $campo): array
    {
        $type = self::CAMPOS[$campo]['type'] ?? 'texto';

        return match ($type) {
            'entero' => self::OPERADORES_ENTERO,
            'decimal' => self::OPERADORES_DECIMAL,
            'fecha' => self::OPERADORES_ENTERO,
            default => self::OPERADORES_TEXTO,
        };
    }

    private static function normalizarOperador(string $operador, string $campo): string
    {
        $ops = self::operadoresParaCampo($campo);

        return isset($ops[$operador]) ? $operador : array_key_first($ops);
    }
}
