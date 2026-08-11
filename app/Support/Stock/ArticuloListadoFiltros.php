<?php

namespace App\Support\Stock;

use App\Support\Listado\CoincidenciaFlexibleTexto;
use App\Support\Listado\FiltrosListadoRequest;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * Filtros del listado de artículos (index / exportaciones).
 *
 * El estado (activo/inactivo) es filtro externo principal; el panel inteligente
 * aplica sobre el subconjunto ya filtrado por estado.
 */
class ArticuloListadoFiltros
{
    public const MODO_TODOS = 'todos';

    public const MODO_CAMPO = 'campo';

    public const ESTADO_ACTIVO = 'ACTIVO';

    public const ESTADO_INACTIVO = 'INACTIVO';

    /** Tipos de imputación de articulo_cuentacontable (venta / compra / gasto). */
    private const TIPOS_IMPUTACION_FILTRO = [
        'cuentaventa' => 'VENTAS',
        'cuentacompra' => 'COMPRAS',
        'cuentagasto' => 'GASTOS',
    ];

    /** @var array<string, array{column: string, type: string, label: string}> */
    public const CAMPOS = [
        'id' => ['column' => 'articulo.id', 'type' => 'entero', 'label' => 'ID'],
        'sku' => ['column' => 'articulo.sku', 'type' => 'texto', 'label' => 'SKU'],
        'codigobarra' => ['column' => 'articulo.codigobarra', 'type' => 'texto', 'label' => 'Cód. barra'],
        'descripcion' => ['column' => 'articulo.descripcion', 'type' => 'texto', 'label' => 'Descripción'],
        'unidadmedida' => ['column' => 'unidadmedida.nombre', 'type' => 'texto', 'label' => 'Unidad de medida'],
        'categoria' => ['column' => 'categoria.nombre', 'type' => 'texto', 'label' => 'Categoría'],
        'tipoarticulo' => ['column' => 'tipoarticulo.nombre', 'type' => 'texto', 'label' => 'Tipo de artículo'],
        'usoarticulo' => ['column' => 'usoarticulo.nombre', 'type' => 'texto', 'label' => 'Uso'],
        'empresa' => ['column' => 'empresa.nombre', 'type' => 'texto', 'label' => 'Empresa'],
        'numeroparte' => ['column' => 'articulo.numeroparte', 'type' => 'texto', 'label' => 'Nro. parte'],
        'ubicacionparte' => ['column' => 'articulo.ubicacionparte', 'type' => 'texto', 'label' => 'Ubic. parte'],
        'cuentaventa' => ['column' => 'cuentacontable.codigo', 'type' => 'cuenta_imputacion', 'label' => 'Cta. contable venta'],
        'cuentacompra' => ['column' => 'cuentacontable.codigo', 'type' => 'cuenta_imputacion', 'label' => 'Cta. contable compra'],
        'cuentagasto' => ['column' => 'cuentacontable.codigo', 'type' => 'cuenta_imputacion', 'label' => 'Cta. contable gasto'],
        'nofactura' => ['column' => 'articulo.nofactura', 'type' => 'texto', 'label' => 'Facturable (0/1)'],
    ];

    public static function filtroEmpresaActivo(): bool
    {
        return (bool) config('articulo.filtro_empresa', false);
    }

    /**
     * Normaliza empresa_id en create/update ABM.
     * Flag off: no toca el campo (comportamiento histórico AGG).
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function normalizarEmpresaIdEnData(array $data): array
    {
        if (! self::filtroEmpresaActivo()) {
            unset($data['empresa_id']);

            return $data;
        }

        if (! array_key_exists('empresa_id', $data) || $data['empresa_id'] === '' || $data['empresa_id'] === null) {
            $data['empresa_id'] = null;
        } else {
            $data['empresa_id'] = (int) $data['empresa_id'];
            if ($data['empresa_id'] <= 0) {
                $data['empresa_id'] = null;
            }
        }

        return $data;
    }

    /**
     * empresa_id al persistir desde Anita (stkmae).
     * Flag on: usa el id indicado (null = todas). Flag off: histórico 1, salvo El Bierzo (maestro compartido → null).
     */
    public static function empresaIdDesdeSyncAnita(?int $empresaId = null): ?int
    {
        if (self::filtroEmpresaActivo()) {
            return ($empresaId !== null && $empresaId > 0) ? $empresaId : null;
        }

        if (strtoupper((string) config('app.empresa')) === 'EL BIERZO') {
            return null;
        }

        return 1;
    }

    /** @var array<string, string> */
    public const OPERADORES_TEXTO = [
        'contiene' => 'Contiene (en cualquier parte)',
        'empieza' => 'Empieza con',
        'termina' => 'Termina con',
        'igual' => 'Igual a',
        'distinto' => 'Distinto de',
        'vacio' => 'Vacío',
    ];

    /**
     * Columnas donde aplica coincidencia flexible (tolerancia a errores de tipeo).
     *
     * @var list<string>
     */
    private const COLUMNAS_COINCIDENCIA_FLEXIBLE = [
        'articulo.sku',
        'articulo.codigobarra',
        'articulo.descripcion',
        'unidadmedida.nombre',
        'articulo.ubicacionparte',
        'articulo.numeroparte',
        'categoria.nombre',
        'tipoarticulo.nombre',
        'usoarticulo.nombre',
        'empresa.nombre',
        'cuentacontable.nombre',
        'cuentacontable.codigo',
    ];

    /** @var array<string, string> */
    public const OPERADORES_ENTERO = [
        'igual' => 'Igual a',
        'mayor' => 'Mayor que',
        'menor' => 'Menor que',
    ];

    public static function resolverDesdeRequest(Request $request, ?string $busquedaRuta = null, ?int $empresaDefault = null): array
    {
        $estado = self::resolverEstadoExterno($request);
        [$empresaId, $empresaScope] = self::filtroEmpresaActivo()
            ? self::resolverEmpresaExterna($request, $empresaDefault)
            : [null, 'todas'];

        if (FiltrosListadoRequest::solicitudLimpiaFiltros($request)) {
            return array_merge(self::filtrosVacios(), [
                'estado' => $estado,
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

        $campo = (string) $request->input('filtro_campo', 'descripcion');
        if (! isset(self::CAMPOS[$campo])) {
            $campo = 'descripcion';
        }
        if ($campo === 'empresa' && ! self::filtroEmpresaActivo()) {
            $campo = 'descripcion';
        }

        $operador = (string) $request->input('filtro_operador', 'contiene');

        if ($busquedaRapida) {
            $modo = self::MODO_TODOS;
            $operador = 'contiene';
        }

        $operador = self::normalizarOperador($operador, $modo === self::MODO_CAMPO ? $campo : 'descripcion');

        return [
            'modo' => $modo,
            'campo' => $campo,
            'operador' => $operador,
            'valor' => $valor,
            'valor_hasta' => trim((string) $request->input('filtro_valor_hasta', '')),
            'busqueda' => $valor,
            'busqueda_rapida' => $busquedaRapida,
            'estado' => $estado,
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
     * Filtro externo principal: estado del artículo (default Activo).
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

    public static function tieneCriteriosAplicados(array $filtros): bool
    {
        if (self::tieneCriteriosTexto($filtros)) {
            return true;
        }

        if (($filtros['estado'] ?? self::ESTADO_ACTIVO) !== self::ESTADO_ACTIVO) {
            return true;
        }

        return false;
    }

    /**
     * Criterios del panel inteligente (sin el filtro externo de estado).
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

    /**
     * @return array{modo: string, campo: string, operador: string, valor: string, valor_hasta: string, busqueda: string, estado: string, empresa_id: ?int, empresa_scope: string}
     */
    public static function filtrosVacios(): array
    {
        return [
            'modo' => self::MODO_TODOS,
            'campo' => 'descripcion',
            'operador' => 'contiene',
            'valor' => '',
            'valor_hasta' => '',
            'busqueda' => '',
            'estado' => self::ESTADO_ACTIVO,
            'empresa_id' => null,
            'empresa_scope' => 'una',
        ];
    }

    /**
     * @return array<string, int>
     */
    public static function paraQueryStringEmpresa(array $filtros): array
    {
        if (! self::filtroEmpresaActivo()) {
            return [];
        }
        if (($filtros['empresa_scope'] ?? 'una') === 'todas') {
            return ['empresa_todas' => 1];
        }
        if (! empty($filtros['empresa_id'])) {
            return ['empresa_id' => (int) $filtros['empresa_id']];
        }

        return [];
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
            $params['filtro_campo'] = $filtros['campo'] ?? 'descripcion';
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
        $estado = $filtros['estado'] ?? self::ESTADO_ACTIVO;
        if ($estado === '') {
            $params['filtro_estado'] = 'TODOS';
        } elseif ($estado !== self::ESTADO_ACTIVO) {
            $params['filtro_estado'] = $estado;
        }

        return $params;
    }

    /**
     * @param  Builder<\App\Models\Stock\Articulo>  $query
     */
    public static function aplicar(Builder $query, array $filtros): void
    {
        if (self::filtroEmpresaActivo() && ! empty($filtros['empresa_id'])) {
            $eid = (int) $filtros['empresa_id'];
            $query->where(function ($q) use ($eid) {
                $q->where('articulo.empresa_id', $eid)
                    ->orWhereNull('articulo.empresa_id');
            });
        }

        $estado = $filtros['estado'] ?? self::ESTADO_ACTIVO;
        if ($estado !== '') {
            $query->where('articulo.estado', $estado);
        }

        $valor = trim((string) ($filtros['valor'] ?? ''));
        if ($valor === '' && ($filtros['operador'] ?? '') !== 'vacio') {
            return;
        }

        $modo = $filtros['modo'] ?? self::MODO_TODOS;
        $operador = $filtros['operador'] ?? 'contiene';

        if ($modo === self::MODO_CAMPO) {
            self::aplicarEnCampo($query, $filtros['campo'] ?? 'descripcion', $operador, $valor, $filtros['valor_hasta'] ?? '');

            return;
        }

        self::aplicarBusquedaGlobal($query, $operador, $valor);
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

        $id = filter_var($valor, FILTER_VALIDATE_INT);
        $like = self::patronLike($operador, $valor);

        $query->where(function ($q) use ($valor, $like, $id, $operador) {
            if ($id !== false) {
                $q->orWhere('articulo.id', (int) $id);
            }
            $textCols = [
                'articulo.sku',
                'articulo.codigobarra',
                'articulo.descripcion',
                'unidadmedida.nombre',
                'articulo.ubicacionparte',
                'articulo.numeroparte',
                'categoria.nombre',
                'tipoarticulo.nombre',
                'usoarticulo.nombre',
                'articulo.nofactura',
            ];
            if (self::filtroEmpresaActivo()) {
                $textCols[] = 'empresa.nombre';
            }
            foreach ($textCols as $col) {
                $q->orWhereRaw(self::expresionTextoMinusculas($col).' LIKE ?', [self::normalizarTextoBusqueda($like)]);
                if ($operador === 'contiene' && self::usaCoincidenciaFlexibleEnColumna($col)) {
                    CoincidenciaFlexibleTexto::aplicar(
                        $q,
                        $col,
                        $valor,
                        true,
                        CoincidenciaFlexibleTexto::LONGITUD_MINIMA_ARTICULO
                    );
                }
            }
            foreach (array_values(self::TIPOS_IMPUTACION_FILTRO) as $tipoImputacion) {
                $q->orWhere(function ($sub) use ($tipoImputacion, $operador, $valor) {
                    self::aplicarCuentaImputacion($sub, $tipoImputacion, $operador, $valor);
                });
            }
        });
    }

    private static function usaCoincidenciaFlexibleEnColumna(string $column): bool
    {
        return in_array($column, self::COLUMNAS_COINCIDENCIA_FLEXIBLE, true);
    }

    /**
     * @param  Builder<\App\Models\Stock\Articulo>  $query
     */
    private static function aplicarEnCampo(Builder $query, string $campoKey, string $operador, string $valor, string $valorHasta): void
    {
        $def = self::CAMPOS[$campoKey] ?? self::CAMPOS['descripcion'];
        $type = $def['type'];

        if ($type === 'entero') {
            self::aplicarEntero($query, (string) $def['column'], $operador, $valor);

            return;
        }

        if ($type === 'cuenta_imputacion') {
            $tipoImputacion = self::TIPOS_IMPUTACION_FILTRO[$campoKey] ?? 'VENTAS';
            self::aplicarCuentaImputacion($query, $tipoImputacion, $operador, $valor);

            return;
        }

        self::aplicarTexto($query, (string) $def['column'], $operador, $valor);
    }

    /**
     * Filtra por código o nombre de cuenta contable del tipo de imputación (VENTAS/COMPRAS/GASTOS).
     *
     * @param  Builder<\App\Models\Stock\Articulo>  $query
     */
    private static function aplicarCuentaImputacion(Builder $query, string $tipoImputacion, string $operador, string $valor): void
    {
        if ($operador === 'vacio') {
            $query->whereDoesntHave('articulo_cuentacontables', function ($q) use ($tipoImputacion) {
                $q->where('tipoimputacion', $tipoImputacion)
                    ->whereNotNull('cuentacontable_id')
                    ->where('cuentacontable_id', '>', 0);
            });

            return;
        }

        if ($valor === '') {
            return;
        }

        $query->whereHas('articulo_cuentacontables', function ($q) use ($tipoImputacion, $operador, $valor) {
            $q->where('tipoimputacion', $tipoImputacion)
                ->whereHas('cuentacontables', function ($c) use ($operador, $valor) {
                    if ($operador === 'distinto') {
                        // Ningún campo debe coincidir con el valor.
                        $c->where(function ($w) use ($operador, $valor) {
                            self::aplicarTexto($w, 'cuentacontable.codigo', $operador, $valor);
                        })->where(function ($w) use ($operador, $valor) {
                            self::aplicarTexto($w, 'cuentacontable.nombre', $operador, $valor);
                        });

                        return;
                    }

                    $c->where(function ($w) use ($operador, $valor) {
                        self::aplicarTexto($w, 'cuentacontable.codigo', $operador, $valor);
                        $w->orWhere(function ($nombre) use ($operador, $valor) {
                            self::aplicarTexto($nombre, 'cuentacontable.nombre', $operador, $valor);
                        });
                    });
                });
        });
    }

    /**
     * @param  Builder<\App\Models\Stock\Articulo>  $query
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
                self::whereTextoInsensitive($query, $column, self::escapeLike($valor).'%');
                break;
            case 'termina':
                self::whereTextoInsensitive($query, $column, '%'.self::escapeLike($valor));
                break;
            case 'igual':
                $query->whereRaw(self::expresionTextoMinusculas($column).' = ?', [self::normalizarTextoBusqueda($valor)]);
                break;
            case 'distinto':
                $query->whereRaw(self::expresionTextoMinusculas($column).' != ?', [self::normalizarTextoBusqueda($valor)]);
                break;
            case 'contiene':
            default:
                $query->where(function ($q) use ($column, $valor) {
                    self::whereTextoInsensitive($q, $column, '%'.self::escapeLike($valor).'%');
                    if (self::usaCoincidenciaFlexibleEnColumna($column)) {
                        CoincidenciaFlexibleTexto::aplicar(
                            $q,
                            $column,
                            $valor,
                            true,
                            CoincidenciaFlexibleTexto::LONGITUD_MINIMA_ARTICULO
                        );
                    }
                });
                break;
        }
    }

    /**
     * LIKE insensible a mayúsculas (ej. «lab» coincide con SKU «LAB001»).
     *
     * @param  Builder<\App\Models\Stock\Articulo>  $query
     */
    private static function whereTextoInsensitive(Builder $query, string $column, string $patronLike): void
    {
        $query->whereRaw(self::expresionTextoMinusculas($column).' LIKE ?', [self::normalizarTextoBusqueda($patronLike)]);
    }

    private static function expresionTextoMinusculas(string $column): string
    {
        return 'LOWER('.$column.')';
    }

    private static function normalizarTextoBusqueda(string $valor): string
    {
        return mb_strtolower($valor, 'UTF-8');
    }

    /**
     * @param  Builder<\App\Models\Stock\Articulo>  $query
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
            'cuenta_imputacion' => array_keys(self::OPERADORES_TEXTO),
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
            'cuenta_imputacion' => self::OPERADORES_TEXTO,
            default => self::OPERADORES_TEXTO,
        };
    }
}
