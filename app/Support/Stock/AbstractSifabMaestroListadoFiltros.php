<?php

namespace App\Support\Stock;

use App\Support\Listado\CoincidenciaFlexibleTexto;
use App\Support\Listado\FiltrosListadoRequest;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * Filtros comunes de maestros SIFAB (codigo / nombre / codigo_interno_sifab).
 */
abstract class AbstractSifabMaestroListadoFiltros
{
    public const MODO_TODOS = 'todos';

    public const MODO_CAMPO = 'campo';

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

    abstract public static function tabla(): string;

    /**
     * @return array<string, array{column: string, type: string, label: string}>
     */
    public static function campos(): array
    {
        $t = static::tabla();

        return [
            'id' => ['column' => $t.'.id', 'type' => 'entero', 'label' => 'ID'],
            'codigo_interno_sifab' => ['column' => $t.'.codigo_interno_sifab', 'type' => 'entero', 'label' => 'Cód. interno SIFAB'],
            'codigo' => ['column' => $t.'.codigo', 'type' => 'texto', 'label' => 'Código'],
            'nombre' => ['column' => $t.'.nombre', 'type' => 'texto', 'label' => 'Nombre'],
            'habilitado' => ['column' => $t.'.habilitado', 'type' => 'texto', 'label' => 'Habilitado'],
        ];
    }

    /** @var list<string> */
    private static function columnasFlexibles(): array
    {
        $t = static::tabla();

        return [$t.'.nombre', $t.'.codigo'];
    }

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
        if (! isset(static::campos()[$campo])) {
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

    public static function aplicar(Builder $query, array $filtros): void
    {
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

    private static function aplicarBusquedaGlobal(Builder $query, string $operador, string $valor): void
    {
        $t = static::tabla();
        $cols = [$t.'.nombre', $t.'.codigo'];

        if ($operador === 'vacio') {
            $query->where(function ($q) use ($cols) {
                foreach ($cols as $col) {
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

        $query->where(function ($q) use ($valor, $like, $id, $operador, $t, $cols) {
            if ($id !== false) {
                $q->orWhere($t.'.id', (int) $id);
                $q->orWhere($t.'.codigo_interno_sifab', (int) $id);
            }
            foreach ($cols as $col) {
                $q->orWhere($col, 'like', $like);
                if ($operador === 'contiene' && in_array($col, self::columnasFlexibles(), true)) {
                    CoincidenciaFlexibleTexto::aplicar(
                        $q,
                        $col,
                        $valor,
                        true,
                        CoincidenciaFlexibleTexto::LONGITUD_MINIMA_DEFAULT
                    );
                }
            }
            $hab = mb_strtolower($valor);
            if (str_contains($hab, 'si') || str_contains($hab, 'hab') || $hab === '1') {
                $q->orWhere($t.'.habilitado', true);
            }
            if (str_contains($hab, 'no') || $hab === '0') {
                $q->orWhere($t.'.habilitado', false);
            }
        });
    }

    private static function aplicarEnCampo(Builder $query, string $campoKey, string $operador, string $valor): void
    {
        $def = static::campos()[$campoKey] ?? static::campos()['nombre'];
        if ($def['type'] === 'entero') {
            self::aplicarEntero($query, (string) $def['column'], $operador, $valor);

            return;
        }
        if ($campoKey === 'habilitado') {
            self::aplicarHabilitado($query, $operador, $valor);

            return;
        }
        self::aplicarTexto($query, (string) $def['column'], $operador, $valor);
    }

    private static function aplicarHabilitado(Builder $query, string $operador, string $valor): void
    {
        $t = static::tabla();
        if ($operador === 'vacio') {
            $query->whereNull($t.'.habilitado');

            return;
        }
        $v = mb_strtolower(trim($valor));
        $bool = match (true) {
            in_array($v, ['1', 'si', 'sí', 'true', 'hab', 'habilitado'], true) => true,
            in_array($v, ['0', 'no', 'false'], true) => false,
            default => null,
        };
        if ($bool === null) {
            return;
        }
        $query->where($t.'.habilitado', $bool);
    }

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
                    if (in_array($column, self::columnasFlexibles(), true)) {
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

    private static function aplicarEntero(Builder $query, string $column, string $operador, string $valor): void
    {
        if (! preg_match('/^-?\d+$/', trim($valor))) {
            return;
        }
        $id = (int) $valor;
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
        $type = static::campos()[$campoKey]['type'] ?? 'texto';
        $permitidos = match ($type) {
            'entero' => array_keys(self::OPERADORES_ENTERO),
            default => array_keys(self::OPERADORES_TEXTO),
        };

        return in_array($operador, $permitidos, true) ? $operador : ($permitidos[0] ?? 'contiene');
    }

    public static function operadoresParaCampo(string $campoKey): array
    {
        $type = static::campos()[$campoKey]['type'] ?? 'texto';

        return match ($type) {
            'entero' => self::OPERADORES_ENTERO,
            default => self::OPERADORES_TEXTO,
        };
    }
}
