<?php

namespace App\Support\Caja;

use App\Models\Caja\Tipotransaccion_Caja;
use App\Support\Listado\CoincidenciaFlexibleTexto;
use App\Support\Listado\FiltrosListadoRequest;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * Filtros del listado de tipos de transacción de caja.
 */
class TipotransaccionCajaListadoFiltros
{
    public const MODO_TODOS = 'todos';

    public const MODO_CAMPO = 'campo';

    /** @var array<string, array{column: string, type: string, label: string}> */
    public const CAMPOS = [
        'id' => ['column' => 'tipotransaccion_caja.id', 'type' => 'entero', 'label' => 'ID'],
        'nombre' => ['column' => 'tipotransaccion_caja.nombre', 'type' => 'texto', 'label' => 'Nombre'],
        'abreviatura' => ['column' => 'tipotransaccion_caja.abreviatura', 'type' => 'texto', 'label' => 'Abreviatura'],
        'operacion' => ['column' => 'tipotransaccion_caja.operacion', 'type' => 'texto', 'label' => 'Operación'],
        'signo' => ['column' => 'tipotransaccion_caja.signo', 'type' => 'texto', 'label' => 'Signo'],
        'estado' => ['column' => 'tipotransaccion_caja.estado', 'type' => 'texto', 'label' => 'Estado'],
    ];

    /** @var list<string> */
    private const COLUMNAS_COINCIDENCIA_FLEXIBLE = [
        'tipotransaccion_caja.nombre',
        'tipotransaccion_caja.abreviatura',
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

        $campo = (string) $request->input('filtro_campo', 'nombre');
        if (! isset(self::CAMPOS[$campo])) {
            $campo = 'nombre';
        }

        $operador = (string) $request->input('filtro_operador', 'contiene');

        if ($busquedaRapida) {
            $modo = self::MODO_TODOS;
            $operador = 'contiene';
        }

        $operador = self::normalizarOperador($operador, $modo === self::MODO_CAMPO ? $campo : 'nombre');

        return [
            'modo' => $modo,
            'campo' => $campo,
            'operador' => $operador,
            'valor' => $valor,
            'valor_hasta' => trim((string) $request->input('filtro_valor_hasta', '')),
            'busqueda' => $valor,
            'busqueda_rapida' => $busquedaRapida,
        ];
    }

    public static function tieneCriteriosAplicados(array $filtros): bool
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
     * @return array{modo: string, campo: string, operador: string, valor: string, valor_hasta: string, busqueda: string}
     */
    public static function filtrosVacios(): array
    {
        return [
            'modo' => self::MODO_TODOS,
            'campo' => 'nombre',
            'operador' => 'contiene',
            'valor' => '',
            'valor_hasta' => '',
            'busqueda' => '',
        ];
    }

    /**
     * @return array<string, string|int|bool>
     */
    public static function paraQueryString(array $filtros): array
    {
        $params = [];
        if (($filtros['modo'] ?? self::MODO_TODOS) !== self::MODO_TODOS) {
            $params['filtro_modo'] = $filtros['modo'];
        }
        if (($filtros['modo'] ?? '') === self::MODO_CAMPO) {
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
     * @param  Builder<\App\Models\Caja\Tipotransaccion_Caja>  $query
     */
    public static function aplicar(Builder $query, array $filtros): void
    {
        if (! self::tieneCriteriosAplicados($filtros)) {
            return;
        }

        $valor = trim((string) ($filtros['valor'] ?? ''));
        if ($valor === '' && ($filtros['operador'] ?? '') !== 'vacio') {
            return;
        }

        $modo = $filtros['modo'] ?? self::MODO_TODOS;
        $operador = $filtros['operador'] ?? 'contiene';

        if ($modo === self::MODO_CAMPO) {
            self::aplicarEnCampo($query, $filtros['campo'] ?? 'nombre', $operador, $valor);

            return;
        }

        self::aplicarBusquedaGlobal($query, $operador, $valor);
    }

    /**
     * @param  Builder<\App\Models\Caja\Tipotransaccion_Caja>  $query
     */
    private static function aplicarBusquedaGlobal(Builder $query, string $operador, string $valor): void
    {
        if ($operador === 'vacio') {
            $query->where(function ($q) {
                foreach (['tipotransaccion_caja.nombre', 'tipotransaccion_caja.abreviatura'] as $col) {
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
                $q->orWhere('tipotransaccion_caja.id', (int) $id);
            }
            foreach (['tipotransaccion_caja.nombre', 'tipotransaccion_caja.abreviatura'] as $col) {
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
            self::aplicarCoincidenciaEnum($q, 'tipotransaccion_caja.operacion', Tipotransaccion_Caja::$enumOperacion, $valor, $operador);
            self::aplicarCoincidenciaEnum($q, 'tipotransaccion_caja.estado', Tipotransaccion_Caja::$enumEstado, $valor, $operador);
            self::aplicarCoincidenciaSigno($q, $valor, $operador);
        });
    }

    private static function usaCoincidenciaFlexibleEnColumna(string $column): bool
    {
        return in_array($column, self::COLUMNAS_COINCIDENCIA_FLEXIBLE, true);
    }

    /**
     * @param  Builder<\App\Models\Caja\Tipotransaccion_Caja>  $query
     */
    private static function aplicarEnCampo(Builder $query, string $campoKey, string $operador, string $valor): void
    {
        $def = self::CAMPOS[$campoKey] ?? self::CAMPOS['nombre'];
        $type = $def['type'];

        if ($type === 'entero') {
            self::aplicarEntero($query, (string) $def['column'], $operador, $valor);

            return;
        }

        if ($campoKey === 'operacion') {
            self::aplicarCampoEnum($query, 'tipotransaccion_caja.operacion', Tipotransaccion_Caja::$enumOperacion, $operador, $valor);

            return;
        }

        if ($campoKey === 'estado') {
            self::aplicarCampoEnum($query, 'tipotransaccion_caja.estado', Tipotransaccion_Caja::$enumEstado, $operador, $valor);

            return;
        }

        if ($campoKey === 'signo') {
            self::aplicarCampoSigno($query, $operador, $valor);

            return;
        }

        self::aplicarTexto($query, (string) $def['column'], $operador, $valor);
    }

    /**
     * @param  Builder<\App\Models\Caja\Tipotransaccion_Caja>  $query
     * @param  array<string, string>  $enum
     */
    private static function aplicarCampoEnum(Builder $query, string $column, array $enum, string $operador, string $valor): void
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

        $codigos = self::codigosEnumCoincidentes($enum, $valor, $operador);
        if ($codigos !== []) {
            if ($operador === 'distinto') {
                $query->whereNotIn($column, $codigos);

                return;
            }
            $query->whereIn($column, $codigos);

            return;
        }

        self::aplicarTexto($query, $column, $operador, $valor);
    }

    /**
     * @param  Builder<\App\Models\Caja\Tipotransaccion_Caja>  $query
     */
    private static function aplicarCampoSigno(Builder $query, string $operador, string $valor): void
    {
        if ($operador === 'vacio') {
            $query->whereNull('tipotransaccion_caja.signo');

            return;
        }
        if ($valor === '') {
            return;
        }

        $valoresDb = self::valoresSignoDbCoincidentes($valor, $operador);
        if ($valoresDb === []) {
            return;
        }

        if ($operador === 'distinto') {
            $query->whereNotIn('tipotransaccion_caja.signo', $valoresDb);

            return;
        }

        $query->whereIn('tipotransaccion_caja.signo', $valoresDb);
    }

    /**
     * @param  Builder<\App\Models\Caja\Tipotransaccion_Caja>  $query
     * @param  array<string, string>  $enum
     */
    private static function aplicarCoincidenciaEnum(Builder $query, string $column, array $enum, string $valor, string $operador): void
    {
        $codigos = self::codigosEnumCoincidentes($enum, $valor, $operador);
        if ($codigos === []) {
            return;
        }

        $query->orWhereIn($column, $codigos);
    }

    /**
     * @param  Builder<\App\Models\Caja\Tipotransaccion_Caja>  $query
     */
    private static function aplicarCoincidenciaSigno(Builder $query, string $valor, string $operador): void
    {
        $valoresDb = self::valoresSignoDbCoincidentes($valor, $operador);
        if ($valoresDb === []) {
            return;
        }

        $query->orWhereIn('tipotransaccion_caja.signo', $valoresDb);
    }

    /**
     * @param  array<string, string>  $enum
     * @return list<string>
     */
    private static function codigosEnumCoincidentes(array $enum, string $valor, string $operador): array
    {
        $valorNorm = mb_strtolower(trim($valor));
        $codigos = [];

        foreach ($enum as $codigo => $etiqueta) {
            $codigoNorm = mb_strtolower((string) $codigo);
            $etiquetaNorm = mb_strtolower($etiqueta);

            $coincide = match ($operador) {
                'igual' => $valorNorm === $codigoNorm || $valorNorm === $etiquetaNorm,
                'empieza' => str_starts_with($etiquetaNorm, $valorNorm) || str_starts_with($codigoNorm, $valorNorm),
                'termina' => str_ends_with($etiquetaNorm, $valorNorm) || str_ends_with($codigoNorm, $valorNorm),
                'distinto' => $valorNorm === $codigoNorm || $valorNorm === $etiquetaNorm,
                default => str_contains($etiquetaNorm, $valorNorm) || str_contains($codigoNorm, $valorNorm),
            };

            if ($coincide) {
                $codigos[] = (string) $codigo;
            }
        }

        return array_values(array_unique($codigos));
    }

    /**
     * Signo en BD: 1 = Ingreso, -1 = Egreso (accessor expone I/E).
     *
     * @return list<int>
     */
    private static function valoresSignoDbCoincidentes(string $valor, string $operador): array
    {
        $map = [
            'I' => 1,
            'E' => -1,
        ];
        $etiquetas = Tipotransaccion_Caja::$enumSigno;
        $valorNorm = mb_strtolower(trim($valor));
        $valores = [];

        foreach ($etiquetas as $codigo => $etiqueta) {
            $codigoNorm = mb_strtolower((string) $codigo);
            $etiquetaNorm = mb_strtolower($etiqueta);

            $coincide = match ($operador) {
                'igual', 'distinto' => $valorNorm === $codigoNorm || $valorNorm === $etiquetaNorm,
                'empieza' => str_starts_with($etiquetaNorm, $valorNorm) || str_starts_with($codigoNorm, $valorNorm),
                'termina' => str_ends_with($etiquetaNorm, $valorNorm) || str_ends_with($codigoNorm, $valorNorm),
                default => str_contains($etiquetaNorm, $valorNorm) || str_contains($codigoNorm, $valorNorm),
            };

            if ($coincide && isset($map[$codigo])) {
                $valores[] = $map[$codigo];
            }
        }

        return array_values(array_unique($valores));
    }

    /**
     * @param  Builder<\App\Models\Caja\Tipotransaccion_Caja>  $query
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
     * @param  Builder<\App\Models\Caja\Tipotransaccion_Caja>  $query
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
}
