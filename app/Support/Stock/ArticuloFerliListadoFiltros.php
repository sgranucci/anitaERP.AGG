<?php

namespace App\Support\Stock;

use App\Support\Listado\CoincidenciaFlexibleTexto;
use App\Support\Listado\FiltrosListadoRequest;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * Filtros del listado Ferli de artículos (products.index).
 * Externos: estado del artículo (ACTIVO/INACTIVO), canal de venta y estado de combinación (A/I/T).
 * Panel: búsqueda inteligente.
 */
class ArticuloFerliListadoFiltros
{
    public const MODO_TODOS = 'todos';

    public const MODO_CAMPO = 'campo';

    public const ESTADO_ACTIVO = 'ACTIVO';

    public const ESTADO_INACTIVO = 'INACTIVO';

    /** Sin filtro de canal (todos los artículos). */
    public const CANAL_TODOS = '';

    /** Artículos sin ninguna fila en articulo_canal. */
    public const CANAL_SIN = 'SIN';

    /** Combinaciones activas (default). */
    public const ESTADO_COMB_ACTIVAS = 'A';

    public const ESTADO_COMB_INACTIVAS = 'I';

    /** Todos los artículos (sin filtro de combinación). */
    public const ESTADO_COMB_TODOS = 'T';

    /** @var array<string, array{column: string, type: string, label: string}> */
    public const CAMPOS = [
        'sku' => ['column' => 'articulo.sku', 'type' => 'texto', 'label' => 'Código'],
        'descripcion' => ['column' => 'articulo.descripcion', 'type' => 'texto', 'label' => 'Descripción'],
        'categoria' => ['column' => 'categoria.nombre', 'type' => 'texto', 'label' => 'Categoría'],
        'marca' => ['column' => 'mventa.nombre', 'type' => 'texto', 'label' => 'Marca'],
        'linea' => ['column' => 'linea.nombre', 'type' => 'texto', 'label' => 'Línea'],
        'nofactura' => ['column' => 'articulo.nofactura', 'type' => 'texto', 'label' => 'Facturable (0/1)'],
    ];

    /** @var list<string> */
    private const COLUMNAS_BUSQUEDA_GLOBAL = [
        'articulo.sku',
        'articulo.descripcion',
        'categoria.nombre',
        'mventa.nombre',
        'linea.nombre',
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

    public static function resolverDesdeRequest(Request $request, ?string $busquedaRuta = null): array
    {
        $estado = self::resolverEstadoExterno($request);
        $canal = self::resolverCanalExterno($request);
        $estadoComb = self::resolverEstadoCombExterno($request);

        if (FiltrosListadoRequest::solicitudLimpiaFiltros($request)) {
            return array_merge(self::filtrosVacios(), [
                'estado' => $estado,
                'canal' => $canal,
                'estado_comb' => $estadoComb,
            ]);
        }

        $valor = FiltrosListadoRequest::valorBusqueda($request, $busquedaRuta);
        $busquedaRapida = $request->boolean('filtro_busqueda_rapida');

        $modo = (string) $request->input('filtro_modo', self::MODO_TODOS);
        if (! in_array($modo, [self::MODO_TODOS, self::MODO_CAMPO], true)) {
            $modo = self::MODO_TODOS;
        }

        $campo = (string) $request->input('filtro_campo', 'descripcion');
        if (! isset(self::CAMPOS[$campo])) {
            $campo = 'descripcion';
        }

        $operador = (string) $request->input('filtro_operador', 'contiene');

        if ($busquedaRapida) {
            $modo = self::MODO_TODOS;
            $operador = 'contiene';
        }

        $operador = self::normalizarOperador($operador);

        return [
            'modo' => $modo,
            'campo' => $campo,
            'operador' => $operador,
            'valor' => $valor,
            'busqueda' => $valor,
            'busqueda_rapida' => $busquedaRapida,
            'estado' => $estado,
            'canal' => $canal,
            'estado_comb' => $estadoComb,
        ];
    }

    /**
     * Filtro externo: estado del artículo (default Activo).
     * Marcador para expandir: filtro_estado=TODOS.
     */
    private static function resolverEstadoExterno(Request $request): string
    {
        $estadoInput = $request->input('filtro_estado', null);
        if ($estadoInput === 'TODOS' || $request->boolean('estado_todos')) {
            return '';
        }
        if (in_array($estadoInput, [self::ESTADO_ACTIVO, self::ESTADO_INACTIVO], true)) {
            return (string) $estadoInput;
        }

        return self::ESTADO_ACTIVO;
    }

    /**
     * Filtro externo: canal de venta (default todos).
     * filtro_canal=TODOS | SIN | LOCAL | FABRICA.
     */
    private static function resolverCanalExterno(Request $request): string
    {
        $canalInput = strtoupper(trim((string) $request->input('filtro_canal', '')));
        if ($canalInput === '' || $canalInput === 'TODOS' || $request->boolean('canal_todos')) {
            return self::CANAL_TODOS;
        }
        if ($canalInput === self::CANAL_SIN) {
            return self::CANAL_SIN;
        }
        if (preg_match('/^[A-Z0-9_-]{1,30}$/', $canalInput) === 1) {
            return $canalInput;
        }

        return self::CANAL_TODOS;
    }

    private static function resolverEstadoCombExterno(Request $request): string
    {
        $estado = (string) $request->input('estado_comb', self::ESTADO_COMB_ACTIVAS);
        if (! in_array($estado, [self::ESTADO_COMB_ACTIVAS, self::ESTADO_COMB_INACTIVAS, self::ESTADO_COMB_TODOS], true)) {
            return self::ESTADO_COMB_ACTIVAS;
        }

        return $estado;
    }

    public static function tieneCriteriosAplicados(array $filtros): bool
    {
        if (self::tieneCriteriosTexto($filtros)) {
            return true;
        }

        if (($filtros['estado'] ?? self::ESTADO_ACTIVO) !== self::ESTADO_ACTIVO) {
            return true;
        }

        if (($filtros['canal'] ?? self::CANAL_TODOS) !== self::CANAL_TODOS) {
            return true;
        }

        return ($filtros['estado_comb'] ?? self::ESTADO_COMB_ACTIVAS) !== self::ESTADO_COMB_ACTIVAS;
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

    /**
     * @return array{modo: string, campo: string, operador: string, valor: string, busqueda: string, estado: string, canal: string, estado_comb: string}
     */
    public static function filtrosVacios(): array
    {
        return [
            'modo' => self::MODO_TODOS,
            'campo' => 'descripcion',
            'operador' => 'contiene',
            'valor' => '',
            'busqueda' => '',
            'estado' => self::ESTADO_ACTIVO,
            'canal' => self::CANAL_TODOS,
            'estado_comb' => self::ESTADO_COMB_ACTIVAS,
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function paraQueryString(array $filtros): array
    {
        $params = [];
        if (($filtros['modo'] ?? self::MODO_TODOS) !== self::MODO_TODOS) {
            $params['filtro_modo'] = $filtros['modo'];
        }
        if (($filtros['modo'] ?? '') === self::MODO_CAMPO) {
            $params['filtro_campo'] = $filtros['campo'] ?? 'descripcion';
            $params['filtro_operador'] = $filtros['operador'] ?? 'contiene';
        } elseif (($filtros['operador'] ?? 'contiene') !== 'contiene') {
            $params['filtro_operador'] = $filtros['operador'];
        }
        if (! empty($filtros['valor'])) {
            $params['filtro_valor'] = $filtros['valor'];
        }
        $estado = $filtros['estado'] ?? self::ESTADO_ACTIVO;
        if ($estado === '') {
            $params['filtro_estado'] = 'TODOS';
        } elseif ($estado !== self::ESTADO_ACTIVO) {
            $params['filtro_estado'] = $estado;
        }
        $canal = (string) ($filtros['canal'] ?? self::CANAL_TODOS);
        if ($canal !== self::CANAL_TODOS) {
            $params['filtro_canal'] = $canal;
        }
        $estadoComb = $filtros['estado_comb'] ?? self::ESTADO_COMB_ACTIVAS;
        if ($estadoComb !== self::ESTADO_COMB_ACTIVAS) {
            $params['estado_comb'] = $estadoComb;
        }

        return $params;
    }

    /**
     * @param  Builder<\App\Models\Stock\Articulo>  $query
     */
    public static function aplicar(Builder $query, array $filtros): void
    {
        $estado = $filtros['estado'] ?? self::ESTADO_ACTIVO;
        if ($estado !== '') {
            ArticuloEstadoCanalSupport::aplicarFiltroEstadoListado(
                $query,
                $estado,
                (string) ($filtros['canal'] ?? self::CANAL_TODOS)
            );
        }

        self::aplicarCanalExterno($query, $filtros);

        $estadoComb = $filtros['estado_comb'] ?? self::ESTADO_COMB_ACTIVAS;
        if ($estadoComb === self::ESTADO_COMB_ACTIVAS || $estadoComb === self::ESTADO_COMB_INACTIVAS) {
            $query->whereExists(function ($q) use ($estadoComb) {
                $q->selectRaw('1')
                    ->from('combinacion')
                    ->whereColumn('combinacion.articulo_id', 'articulo.id')
                    ->where(
                        \App\Support\Stock\CombinacionEstadoCanalSupport::columnaPorAmbito(
                            \App\Support\Stock\CombinacionEstadoCanalSupport::AMBITO_FABRICA
                        ),
                        $estadoComb
                    );
            });
        }

        $valor = trim((string) ($filtros['valor'] ?? ''));
        if ($valor === '' && ($filtros['operador'] ?? '') !== 'vacio') {
            return;
        }

        $modo = $filtros['modo'] ?? self::MODO_TODOS;
        $operador = $filtros['operador'] ?? 'contiene';

        if ($modo === self::MODO_CAMPO) {
            self::aplicarEnCampo($query, $filtros['campo'] ?? 'descripcion', $operador, $valor);

            return;
        }

        self::aplicarBusquedaGlobal($query, $operador, $valor);
    }

    /**
     * @param  Builder<\App\Models\Stock\Articulo>  $query
     */
    private static function aplicarCanalExterno(Builder $query, array $filtros): void
    {
        $canal = (string) ($filtros['canal'] ?? self::CANAL_TODOS);
        if ($canal === self::CANAL_TODOS) {
            return;
        }

        if ($canal === self::CANAL_SIN) {
            $query->whereNotExists(function ($q) {
                $q->selectRaw('1')
                    ->from('articulo_canal')
                    ->whereColumn('articulo_canal.articulo_id', 'articulo.id');
            });

            return;
        }

        $query->whereExists(function ($q) use ($canal) {
            $q->selectRaw('1')
                ->from('articulo_canal')
                ->join('canal', 'canal.id', '=', 'articulo_canal.canal_id')
                ->whereColumn('articulo_canal.articulo_id', 'articulo.id')
                ->where('canal.codigo', $canal)
                ->where('canal.activo', true);
        });
    }

    /**
     * @param  Builder<\App\Models\Stock\Articulo>  $query
     */
    private static function aplicarBusquedaGlobal(Builder $query, string $operador, string $valor): void
    {
        if ($operador === 'vacio') {
            $query->where(function ($q) {
                foreach (['articulo.sku', 'articulo.descripcion'] as $col) {
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

        $like = self::patronLike($operador, $valor);

        $query->where(function ($q) use ($valor, $like, $operador) {
            foreach (self::COLUMNAS_BUSQUEDA_GLOBAL as $col) {
                $q->orWhereRaw('LOWER('.$col.') LIKE ?', [mb_strtolower($like, 'UTF-8')]);
                if ($operador === 'contiene') {
                    CoincidenciaFlexibleTexto::aplicar(
                        $q,
                        $col,
                        $valor,
                        true,
                        CoincidenciaFlexibleTexto::LONGITUD_MINIMA_ARTICULO
                    );
                }
            }
        });
    }

    /**
     * @param  Builder<\App\Models\Stock\Articulo>  $query
     */
    private static function aplicarEnCampo(Builder $query, string $campoKey, string $operador, string $valor): void
    {
        $def = self::CAMPOS[$campoKey] ?? self::CAMPOS['descripcion'];
        $column = (string) $def['column'];

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
        if ($operador === 'distinto') {
            $query->whereRaw('LOWER('.$column.') NOT LIKE ?', [mb_strtolower($like, 'UTF-8')]);

            return;
        }

        $query->whereRaw('LOWER('.$column.') LIKE ?', [mb_strtolower($like, 'UTF-8')]);
        if ($operador === 'contiene') {
            CoincidenciaFlexibleTexto::aplicar(
                $query,
                $column,
                $valor,
                true,
                CoincidenciaFlexibleTexto::LONGITUD_MINIMA_ARTICULO
            );
        }
    }

    private static function patronLike(string $operador, string $valor): string
    {
        $escaped = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $valor);

        switch ($operador) {
            case 'empieza':
                return $escaped.'%';
            case 'termina':
                return '%'.$escaped;
            case 'igual':
            case 'distinto':
                return $escaped;
            default:
                return '%'.$escaped.'%';
        }
    }

    private static function normalizarOperador(string $operador): string
    {
        if (! isset(self::OPERADORES_TEXTO[$operador])) {
            return 'contiene';
        }

        return $operador;
    }

    /**
     * @return array<string, string>
     */
    public static function operadoresParaCampo(string $campoKey): array
    {
        return self::OPERADORES_TEXTO;
    }
}
