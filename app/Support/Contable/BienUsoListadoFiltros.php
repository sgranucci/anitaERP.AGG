<?php

namespace App\Support\Contable;

use App\Models\Contable\BienUso;
use App\Support\Listado\CoincidenciaFlexibleTexto;
use App\Support\Listado\FiltrosListadoRequest;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class BienUsoListadoFiltros
{
    public const MODO_TODOS = 'todos';

    public const MODO_CAMPO = 'campo';

    /** @var array<string, array{column: string, type: string, label: string}> */
    public const CAMPOS = [
        'id' => ['column' => 'bien_uso.id', 'type' => 'entero', 'label' => 'ID'],
        'codigo_inventario' => ['column' => 'bien_uso.codigo_inventario', 'type' => 'entero', 'label' => 'Cód. inventario'],
        'hostname' => ['column' => 'bien_uso.hostname', 'type' => 'texto', 'label' => 'Hostname'],
        'ip' => ['column' => 'bien_uso.ip', 'type' => 'texto', 'label' => 'IP'],
        'modelo' => ['column' => 'bien_uso.modelo', 'type' => 'texto', 'label' => 'Modelo'],
        'numero_serie' => ['column' => 'bien_uso.numero_serie', 'type' => 'texto', 'label' => 'Número de serie'],
        'estado' => ['column' => 'bien_uso.estado', 'type' => 'estado', 'label' => 'Estado'],
        'centrocosto' => ['column' => 'centrocosto.codigo', 'type' => 'texto', 'label' => 'Centro de costo'],
        'centrocosto_nombre' => ['column' => 'centrocosto.nombre', 'type' => 'texto', 'label' => 'Nombre CC'],
        'tipo_bien' => ['column' => 'bien_uso.tipo_bien', 'type' => 'tipo_bien', 'label' => 'Tipo de bien'],
        'observaciones' => ['column' => 'bien_uso.observaciones', 'type' => 'texto', 'label' => 'Observaciones'],
    ];

    /** @var list<string> */
    private const COLUMNAS_COINCIDENCIA_FLEXIBLE = [
        'bien_uso.hostname',
        'bien_uso.modelo',
        'bien_uso.numero_serie',
        'bien_uso.observaciones',
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

        $campo = (string) $request->input('filtro_campo', 'hostname');
        if (! isset(self::CAMPOS[$campo])) {
            $campo = 'hostname';
        }

        $operador = (string) $request->input('filtro_operador', 'contiene');

        if ($busquedaRapida) {
            $modo = self::MODO_TODOS;
            $operador = 'contiene';
        }

        $operador = self::normalizarOperador($operador, $modo === self::MODO_CAMPO ? $campo : 'hostname');

        $centrocostoId = filter_var($request->input('filtro_centrocosto_id'), FILTER_VALIDATE_INT);

        return [
            'modo' => $modo,
            'campo' => $campo,
            'operador' => $operador,
            'valor' => $valor,
            'valor_hasta' => trim((string) $request->input('filtro_valor_hasta', '')),
            'busqueda' => $valor,
            'busqueda_rapida' => $busquedaRapida,
            'centrocosto_id' => $centrocostoId !== false && $centrocostoId > 0 ? (int) $centrocostoId : null,
        ];
    }

    public static function tieneCriteriosAplicados(array $filtros): bool
    {
        if (! empty($filtros['centrocosto_id'])) {
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

    /** @return array{modo: string, campo: string, operador: string, valor: string, valor_hasta: string, busqueda: string} */
    public static function filtrosVacios(): array
    {
        return [
            'modo' => self::MODO_TODOS,
            'campo' => 'hostname',
            'operador' => 'contiene',
            'valor' => '',
            'valor_hasta' => '',
            'busqueda' => '',
            'centrocosto_id' => null,
        ];
    }

    /** @return array<string, string|int|bool> */
    public static function paraQueryString(array $filtros): array
    {
        $params = [];
        if (($filtros['modo'] ?? self::MODO_TODOS) !== self::MODO_TODOS) {
            $params['filtro_modo'] = $filtros['modo'];
        }
        if (($filtros['modo'] ?? '') === self::MODO_CAMPO) {
            $params['filtro_campo'] = $filtros['campo'] ?? 'hostname';
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
        if (! empty($filtros['centrocosto_id'])) {
            $params['filtro_centrocosto_id'] = $filtros['centrocosto_id'];
        }

        return $params;
    }

    /**
     * @param  Builder<\App\Models\Contable\BienUso>  $query
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
            self::aplicarEnCampo($query, $filtros['campo'] ?? 'hostname', $operador, $valor);

            return;
        }

        self::aplicarBusquedaGlobal($query, $operador, $valor);
    }

    /**
     * @param  Builder<\App\Models\Contable\BienUso>  $query
     */
    private static function aplicarBusquedaGlobal(Builder $query, string $operador, string $valor): void
    {
        if ($operador === 'vacio') {
            $query->where(function ($q) {
                foreach (['bien_uso.hostname', 'bien_uso.modelo', 'bien_uso.numero_serie'] as $col) {
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
                $q->orWhere('bien_uso.id', (int) $id);
                $q->orWhere('bien_uso.codigo_inventario', (int) $id);
            }

            $textCols = [
                'bien_uso.hostname',
                'bien_uso.ip',
                'bien_uso.modelo',
                'bien_uso.numero_serie',
                'bien_uso.observaciones',
                'centrocosto.codigo',
                'centrocosto.nombre',
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

            self::aplicarCoincidenciaEnum($q, $valor, $operador);
        });
    }

    /**
     * @param  Builder<\App\Models\Contable\BienUso>  $query
     */
    private static function aplicarCoincidenciaEnum(Builder $query, string $valor, string $operador): void
    {
        if ($operador !== 'contiene') {
            return;
        }

        $valorNorm = mb_strtolower(trim($valor));

        foreach (BienUso::$enumEstado as $item) {
            if (str_contains(mb_strtolower($item['nombre']), $valorNorm)) {
                $query->orWhere('bien_uso.estado', $item['valor']);
            }
        }
        foreach (BienUso::$enumTipoBien as $item) {
            if (str_contains(mb_strtolower($item['nombre']), $valorNorm)) {
                $query->orWhere('bien_uso.tipo_bien', $item['valor']);
            }
        }
    }

    private static function usaCoincidenciaFlexibleEnColumna(string $column): bool
    {
        return in_array($column, self::COLUMNAS_COINCIDENCIA_FLEXIBLE, true);
    }

    /**
     * @param  Builder<\App\Models\Contable\BienUso>  $query
     */
    private static function aplicarEnCampo(Builder $query, string $campoKey, string $operador, string $valor): void
    {
        $def = self::CAMPOS[$campoKey] ?? self::CAMPOS['hostname'];
        $type = $def['type'];

        if ($type === 'entero') {
            self::aplicarEntero($query, (string) $def['column'], $operador, $valor);

            return;
        }

        if (in_array($type, ['estado', 'tipo_bien'], true)) {
            self::aplicarEnum($query, (string) $def['column'], $type, $operador, $valor);

            return;
        }

        self::aplicarTexto($query, (string) $def['column'], $operador, $valor);
    }

    /**
     * @param  Builder<\App\Models\Contable\BienUso>  $query
     */
    private static function aplicarEnum(Builder $query, string $column, string $tipo, string $operador, string $valor): void
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

        $enum = match ($tipo) {
            'estado' => BienUso::$enumEstado,
            default => BienUso::$enumTipoBien,
        };

        $valorNorm = mb_strtolower(trim($valor));
        $codigo = null;
        foreach ($enum as $item) {
            $nombre = mb_strtolower($item['nombre']);
            $letra = mb_strtolower($item['valor']);
            if ($valorNorm === $letra || str_contains($nombre, $valorNorm)) {
                $codigo = $item['valor'];
                break;
            }
        }

        if ($codigo !== null && in_array($operador, ['contiene', 'igual', 'empieza', 'termina'], true)) {
            $query->where($column, $codigo);

            return;
        }

        self::aplicarTexto($query, $column, $operador, $valor);
    }

    /**
     * @param  Builder<\App\Models\Contable\BienUso>  $query
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
     * @param  Builder<\App\Models\Contable\BienUso>  $query
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
            default => array_keys(self::OPERADORES_TEXTO),
        };

        if (in_array($operador, $permitidos, true)) {
            return $operador;
        }

        return $permitidos[0] ?? 'contiene';
    }

    /** @return array<string, string> */
    public static function operadoresParaCampo(string $campoKey): array
    {
        $type = self::CAMPOS[$campoKey]['type'] ?? 'texto';

        return match ($type) {
            'entero' => self::OPERADORES_ENTERO,
            default => self::OPERADORES_TEXTO,
        };
    }
}
