<?php

namespace App\Support\Ventas;

use App\Support\Listado\CoincidenciaFlexibleTexto;
use App\Support\Listado\FiltrosListadoRequest;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * Filtros del listado de clientes Ventas (index / exportaciones).
 */
class ClienteListadoFiltros
{
    public const MODO_TODOS = 'todos';

    public const MODO_CAMPO = 'campo';

    public const MODO_QBE = 'qbe';

    /**
     * @deprecated Usar campos() — se mantiene alias para código legacy.
     * @var array<string, array{column: string, type: string, label: string}>
     */
    public const CAMPOS = [];

    /**
     * Columnas con coincidencia flexible (tolera errores de tipeo).
     *
     * @var list<string>
     */
    private const COLUMNAS_COINCIDENCIA_FLEXIBLE = [
        'cliente.nombre',
        'cliente.fantasia',
        'cliente.domicilio',
        'cliente.numerodocumento',
        'cliente.contacto',
        'cliente.email',
        'cliente.telefono',
        'cliente.leyenda',
        'localidad.nombre',
        'provincia.nombre',
        'pais.nombre',
        'transporte.nombre',
        'vendedor.nombre',
        'cobrador.nombre',
        'zonavta.nombre',
        'subzonavta.nombre',
        'condicionventa.nombre',
        'listaprecio.nombre',
        'tipoempresa_cliente.nombre',
        'condicioniva.nombre',
    ];

    /** @var array<string, string> */
    public const OPERADORES_TEXTO = [
        'contiene' => 'Contiene',
        'empieza' => 'Empieza con',
        'termina' => 'Termina con',
        'igual' => 'Es igual a',
        'distinto' => 'Distinto de',
        'vacio' => 'Está vacío',
    ];

    /** @var array<string, string> */
    public const OPERADORES_ENTERO = [
        'igual' => 'Es igual a',
        'mayor' => 'Mayor que',
        'menor' => 'Menor que',
        'vacio' => 'Está vacío',
    ];

    /** @var array<string, string> */
    public const OPERADORES_BOOLEANO = [
        'igual' => 'Es',
        'vacio' => 'Sin dato',
    ];

    /**
     * Catálogo de campos filtrables (sincronizado con columnas del workbench).
     *
     * @return array<string, array{column: string, type: string, label: string}>
     */
    public static function campos(): array
    {
        $out = [];
        foreach (ClienteListadoColumnas::camposFiltrables() as $key => $meta) {
            $out[$key] = [
                'column' => $meta['column'],
                'type' => $meta['type'],
                'label' => $meta['label'],
            ];
        }

        return $out;
    }

    public static function resolverDesdeRequest(Request $request, ?string $busquedaRuta = null): array
    {
        if (FiltrosListadoRequest::solicitudLimpiaFiltros($request)) {
            return self::filtrosVacios();
        }

        $valor = FiltrosListadoRequest::valorBusqueda($request, $busquedaRuta);
        $busquedaRapida = $request->boolean('filtro_busqueda_rapida');

        $qbe = self::resolverQbeDesdeRequest($request);

        $modo = (string) $request->input('filtro_modo', self::MODO_TODOS);
        if (! in_array($modo, [self::MODO_TODOS, self::MODO_CAMPO, self::MODO_QBE], true)) {
            $modo = self::MODO_TODOS;
        }

        if ($qbe !== []) {
            $modo = self::MODO_QBE;
        }

        $campo = (string) $request->input('filtro_campo', 'nombre');
        $campos = self::campos();
        if (! isset($campos[$campo])) {
            $campo = 'nombre';
        }

        $operador = (string) $request->input('filtro_operador', 'contiene');

        if ($busquedaRapida) {
            $modo = self::MODO_TODOS;
            $operador = 'contiene';
            $qbe = [];
        }

        if ($modo !== self::MODO_QBE) {
            $operador = self::normalizarOperador($operador, $modo === self::MODO_CAMPO ? $campo : 'nombre');
        } else {
            $operador = 'contiene';
        }

        return [
            'modo' => $modo,
            'campo' => $campo,
            'operador' => $operador,
            'valor' => $valor,
            'valor_hasta' => trim((string) $request->input('filtro_valor_hasta', '')),
            'codigo' => trim((string) $request->input('filtro_codigo', '')),
            'busqueda' => $valor,
            'busqueda_rapida' => $busquedaRapida,
            'qbe' => $qbe,
        ];
    }

    /**
     * Criterios Advanced Find: lista de {campo, op, valor}.
     * Acepta también el formato plano legacy qbe[campo]=valor.
     *
     * @return list<array{campo: string, op: string, valor: string}>
     */
    public static function resolverQbeDesdeRequest(Request $request): array
    {
        $raw = $request->input('qbe', []);
        if (! is_array($raw)) {
            $raw = [];
        }

        $campos = self::campos();
        $criterios = [];

        $esLista = $raw !== [] && array_is_list($raw);
        if ($esLista || (isset($raw[0]) && is_array($raw[0] ?? null))) {
            foreach ($raw as $fila) {
                if (! is_array($fila)) {
                    continue;
                }
                $campo = (string) ($fila['campo'] ?? '');
                if (! isset($campos[$campo])) {
                    continue;
                }
                $op = self::normalizarOperador((string) ($fila['op'] ?? 'contiene'), $campo);
                $valor = trim((string) ($fila['valor'] ?? ''));
                if ($op === 'vacio' || $valor !== '') {
                    $criterios[] = ['campo' => $campo, 'op' => $op, 'valor' => $valor];
                }
            }

            return $criterios;
        }

        foreach ($raw as $key => $valor) {
            $key = (string) $key;
            if (! isset($campos[$key])) {
                continue;
            }
            $valor = trim((string) $valor);
            if ($valor === '') {
                continue;
            }
            $criterios[] = ['campo' => $key, 'op' => 'contiene', 'valor' => $valor];
        }

        return $criterios;
    }

    /**
     * @return array<string, array{column: string, type: string, label: string}>
     */
    public static function camposQbeDisponibles(): array
    {
        return self::campos();
    }

    public static function tieneCriteriosTexto(array $filtros): bool
    {
        foreach ((array) ($filtros['qbe'] ?? []) as $criterio) {
            if (is_array($criterio)) {
                $op = (string) ($criterio['op'] ?? '');
                $valor = trim((string) ($criterio['valor'] ?? ''));
                if ($op === 'vacio' || $valor !== '') {
                    return true;
                }
            } elseif (trim((string) $criterio) !== '') {
                return true;
            }
        }

        if (($filtros['modo'] ?? '') === self::MODO_QBE && ! empty($filtros['qbe'])) {
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
        if (trim((string) ($filtros['codigo'] ?? '')) !== '') {
            return true;
        }

        return self::tieneCriteriosTexto($filtros);
    }

    /**
     * @return array{modo: string, campo: string, operador: string, valor: string, valor_hasta: string, codigo: string, busqueda: string, qbe: list<array{campo: string, op: string, valor: string}>}
     */
    public static function filtrosVacios(): array
    {
        return [
            'modo' => self::MODO_TODOS,
            'campo' => 'nombre',
            'operador' => 'contiene',
            'valor' => '',
            'valor_hasta' => '',
            'codigo' => '',
            'busqueda' => '',
            'qbe' => [],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function paraQueryString(array $filtros): array
    {
        $params = [];
        $modo = $filtros['modo'] ?? self::MODO_TODOS;

        if (trim((string) ($filtros['codigo'] ?? '')) !== '') {
            $params['filtro_codigo'] = trim((string) $filtros['codigo']);
        }

        if ($modo === self::MODO_QBE) {
            $params['filtro_modo'] = self::MODO_QBE;
            $i = 0;
            foreach ((array) ($filtros['qbe'] ?? []) as $criterio) {
                if (! is_array($criterio)) {
                    continue;
                }
                $campo = (string) ($criterio['campo'] ?? '');
                $op = (string) ($criterio['op'] ?? 'contiene');
                $valor = trim((string) ($criterio['valor'] ?? ''));
                if ($campo === '' || ($op !== 'vacio' && $valor === '')) {
                    continue;
                }
                $params['qbe'][$i] = [
                    'campo' => $campo,
                    'op' => $op,
                    'valor' => $valor,
                ];
                $i++;
            }

            return $params;
        }

        if ($modo !== self::MODO_TODOS) {
            $params['filtro_modo'] = $modo;
        }
        if ($modo === self::MODO_CAMPO) {
            $params['filtro_campo'] = $filtros['campo'] ?? 'nombre';
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
     * @param  array<string, mixed>  $base
     * @param  array<string, mixed>  $desdeVista
     * @return array<string, mixed>
     */
    public static function fusionarDesdeVista(array $base, array $desdeVista): array
    {
        if (self::tieneCriteriosAplicados($base)) {
            return $base;
        }

        $qbeRaw = (array) ($desdeVista['qbe'] ?? []);
        $qbe = [];
        if ($qbeRaw !== [] && (array_is_list($qbeRaw) || isset($qbeRaw[0]))) {
            foreach ($qbeRaw as $fila) {
                if (! is_array($fila)) {
                    continue;
                }
                $campo = (string) ($fila['campo'] ?? '');
                if ($campo === '') {
                    continue;
                }
                $op = self::normalizarOperador((string) ($fila['op'] ?? 'contiene'), $campo);
                $valor = trim((string) ($fila['valor'] ?? ''));
                if ($op === 'vacio' || $valor !== '') {
                    $qbe[] = ['campo' => $campo, 'op' => $op, 'valor' => $valor];
                }
            }
        } else {
            foreach ($qbeRaw as $k => $v) {
                $v = trim((string) $v);
                if ($v === '' || ! is_string($k)) {
                    continue;
                }
                $qbe[] = ['campo' => $k, 'op' => 'contiene', 'valor' => $v];
            }
        }

        $modo = (string) ($desdeVista['modo'] ?? self::MODO_TODOS);
        if ($qbe !== []) {
            $modo = self::MODO_QBE;
        }

        return array_merge($base, [
            'modo' => $modo,
            'campo' => (string) ($desdeVista['campo'] ?? $base['campo']),
            'operador' => (string) ($desdeVista['operador'] ?? $base['operador']),
            'valor' => (string) ($desdeVista['valor'] ?? ''),
            'valor_hasta' => (string) ($desdeVista['valor_hasta'] ?? ''),
            'busqueda' => (string) ($desdeVista['valor'] ?? $desdeVista['busqueda'] ?? ''),
            'codigo' => (string) ($desdeVista['codigo'] ?? $base['codigo'] ?? ''),
            'qbe' => $qbe,
        ]);
    }

    /**
     * @param  Builder<\App\Models\Ventas\Cliente>  $query
     */
    public static function aplicar(Builder $query, array $filtros): void
    {
        self::aplicarFiltroCodigo($query, $filtros);

        if (! self::tieneCriteriosTexto($filtros)) {
            return;
        }

        $modo = $filtros['modo'] ?? self::MODO_TODOS;

        if ($modo === self::MODO_QBE) {
            self::aplicarQbe($query, (array) ($filtros['qbe'] ?? []));

            return;
        }

        $valor = trim((string) ($filtros['valor'] ?? ''));
        $operador = $filtros['operador'] ?? 'contiene';

        if ($modo === self::MODO_CAMPO) {
            self::aplicarEnCampo($query, $filtros['campo'] ?? 'nombre', $operador, $valor, $filtros['valor_hasta'] ?? '');

            return;
        }

        self::aplicarBusquedaGlobal($query, $operador, $valor);
    }

    /**
     * Filtro dedicado de código (barra superior El Bierzo):
     * acepta el código con o sin ceros a la izquierda.
     *
     * @param  Builder<\App\Models\Ventas\Cliente>  $query
     */
    private static function aplicarFiltroCodigo(Builder $query, array $filtros): void
    {
        $codigo = trim((string) ($filtros['codigo'] ?? ''));
        if ($codigo === '') {
            return;
        }

        $variantes = self::variantesCodigo($codigo);
        if ($variantes === []) {
            return;
        }

        $query->whereIn('cliente.codigo', $variantes);
    }

    /**
     * @return list<string>
     */
    private static function variantesCodigo(string $codigo): array
    {
        $codigo = trim($codigo);
        if ($codigo === '') {
            return [];
        }
        if (! ctype_digit($codigo)) {
            return [$codigo];
        }

        $norm = ltrim($codigo, '0');
        if ($norm === '') {
            $norm = '0';
        }

        return array_values(array_unique([$codigo, $norm, str_pad($norm, 6, '0', STR_PAD_LEFT)]));
    }

    /**
     * Advanced Find: AND de criterios (campo + operador + valor).
     *
     * @param  Builder<\App\Models\Ventas\Cliente>  $query
     * @param  list<array{campo?: string, op?: string, valor?: string}>|array<string, string>  $qbe
     */
    private static function aplicarQbe(Builder $query, array $qbe): void
    {
        $campos = self::campos();
        foreach ($qbe as $key => $criterio) {
            if (is_array($criterio) && isset($criterio['campo'])) {
                $campo = (string) $criterio['campo'];
                $op = (string) ($criterio['op'] ?? 'contiene');
                $valor = trim((string) ($criterio['valor'] ?? ''));
            } else {
                $campo = (string) $key;
                $op = 'contiene';
                $valor = trim((string) $criterio);
            }

            if (! isset($campos[$campo])) {
                continue;
            }
            $op = self::normalizarOperador($op, $campo);
            if ($op !== 'vacio' && $valor === '') {
                continue;
            }
            self::aplicarEnCampo($query, $campo, $op, $valor, '');
        }
    }

    /**
     * @param  Builder<\App\Models\Ventas\Cliente>  $query
     */
    private static function aplicarBusquedaGlobal(Builder $query, string $operador, string $valor): void
    {
        if ($operador === 'vacio') {
            $query->where(function ($q) {
                foreach (['cliente.nombre', 'cliente.numerodocumento', 'cliente.domicilio', 'cliente.codigo'] as $col) {
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
                $q->orWhere('cliente.id', (int) $id);
            }
            $textCols = [
                'cliente.nombre',
                'cliente.fantasia',
                'cliente.numerodocumento',
                'cliente.domicilio',
                'cliente.codigo',
                'cliente.estado',
                'localidad.nombre',
                'provincia.nombre',
                'transporte.codigo',
                'transporte.nombre',
                'vendedor.codigo',
                'vendedor.nombre',
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
            self::aplicarCuitSinSeparadores($q, $valor);
        });
    }

    /**
     * Igual que el modal: el CUIT se busca también sin guiones, puntos ni espacios.
     *
     * @param  Builder<\App\Models\Ventas\Cliente>  $q
     */
    private static function aplicarCuitSinSeparadores(Builder $q, string $valor): void
    {
        $soloDigitos = preg_replace('/\D+/', '', $valor);
        if ($soloDigitos === '' || mb_strlen($soloDigitos) < 2) {
            return;
        }

        $q->orWhereRaw(
            "REPLACE(REPLACE(REPLACE(cliente.numerodocumento, '-', ''), '.', ''), ' ', '') LIKE ?",
            ['%'.$soloDigitos.'%']
        );
    }

    private static function usaCoincidenciaFlexibleEnColumna(string $column): bool
    {
        return in_array($column, self::COLUMNAS_COINCIDENCIA_FLEXIBLE, true);
    }

    /**
     * @param  Builder<\App\Models\Ventas\Cliente>  $query
     */
    private static function aplicarEnCampo(Builder $query, string $campoKey, string $operador, string $valor, string $valorHasta): void
    {
        $campos = self::campos();
        $def = $campos[$campoKey] ?? $campos['nombre'] ?? null;
        if ($def === null) {
            return;
        }

        $type = $def['type'];

        if ($type === 'booleano') {
            self::aplicarBooleano($query, (string) $def['column'], $operador, $valor);

            return;
        }

        if ($type === 'entero') {
            if ($operador === 'vacio') {
                $query->where(function ($q) use ($def) {
                    $q->whereNull($def['column']);
                });

                return;
            }
            self::aplicarEntero($query, (string) $def['column'], $operador, $valor);

            return;
        }

        self::aplicarTexto($query, (string) $def['column'], $operador, $valor);
    }

    /**
     * @param  Builder<\App\Models\Ventas\Cliente>  $query
     */
    private static function aplicarBooleano(Builder $query, string $column, string $operador, string $valor): void
    {
        if ($operador === 'vacio') {
            $query->where(function ($q) use ($column) {
                $q->whereNull($column)->orWhere($column, 0)->orWhere($column, false);
            });

            return;
        }

        $truthy = in_array(strtolower($valor), ['1', 'si', 'sí', 'true', 's', 'yes'], true);
        $query->where($column, $truthy ? 1 : 0);
    }

    /**
     * @param  Builder<\App\Models\Ventas\Cliente>  $query
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
                            true,
                            CoincidenciaFlexibleTexto::LONGITUD_MINIMA_DEFAULT
                        );
                    }
                    if ($column === 'cliente.numerodocumento') {
                        self::aplicarCuitSinSeparadores($q, $valor);
                    }
                });
                break;
        }
    }

    /**
     * @param  Builder<\App\Models\Ventas\Cliente>  $query
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
        $type = self::campos()[$campoKey]['type'] ?? 'texto';
        $permitidos = match ($type) {
            'entero' => array_keys(self::OPERADORES_ENTERO),
            'booleano' => array_keys(self::OPERADORES_BOOLEANO),
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
        $type = self::campos()[$campoKey]['type'] ?? 'texto';

        return match ($type) {
            'entero' => self::OPERADORES_ENTERO,
            'booleano' => self::OPERADORES_BOOLEANO,
            default => self::OPERADORES_TEXTO,
        };
    }
}
