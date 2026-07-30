<?php

namespace App\Support\Caja;

use App\Support\Caja\RendicionMaquina\RendicionMaquinaTurno;
use App\Support\Listado\CoincidenciaFlexibleTexto;
use App\Support\Listado\FiltrosListadoRequest;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * Filtros del listado de rendiciones de máquinas.
 */
class RendicionMaquinaListadoFiltros
{
    public const MODO_TODOS = 'todos';

    public const MODO_CAMPO = 'campo';

    /** @var array<string, array{column: string, type: string, label: string}> */
    public const CAMPOS = [
        'id' => ['column' => 'rendicion_maquina.id', 'type' => 'entero', 'label' => 'ID'],
        'codigo' => ['column' => 'rendicion_maquina.codigo', 'type' => 'texto', 'label' => 'Código'],
        'fecha' => ['column' => 'rendicion_maquina.fecha', 'type' => 'texto', 'label' => 'Fecha'],
        'turno' => ['column' => 'rendicion_maquina.turno', 'type' => 'texto', 'label' => 'Turno'],
        'empresa' => ['column' => 'empresa.nombre', 'type' => 'texto', 'label' => 'Empresa'],
        'estado' => ['column' => 'rendicion_maquina.estado', 'type' => 'texto', 'label' => 'Estado'],
        'transferencia' => ['column' => 'rendicion_maquina.transferencia', 'type' => 'entero', 'label' => 'Transferencia'],
    ];

    /** @var list<string> */
    private const COLUMNAS_COINCIDENCIA_FLEXIBLE = [
        'rendicion_maquina.codigo',
        'empresa.nombre',
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

        $campo = (string) $request->input('filtro_campo', 'codigo');
        if (! isset(self::CAMPOS[$campo])) {
            $campo = 'codigo';
        }

        $operador = (string) $request->input('filtro_operador', 'contiene');

        if ($busquedaRapida) {
            $modo = self::MODO_TODOS;
            $operador = 'contiene';
        }

        $operador = self::normalizarOperador($operador, $modo === self::MODO_CAMPO ? $campo : 'codigo');

        return [
            'modo' => $modo,
            'campo' => $campo,
            'operador' => $operador,
            'valor' => $valor,
            'valor_hasta' => trim((string) $request->input('filtro_valor_hasta', '')),
            'busqueda' => $valor,
            'busqueda_rapida' => $busquedaRapida,
            'empresa_id' => (int) $request->input('empresa_id', 0),
            'empresas_asignadas' => [],
        ];
    }

    public static function tieneCriteriosAplicados(array $filtros): bool
    {
        if ((int) ($filtros['empresa_id'] ?? 0) > 0) {
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

    /**
     * @return array{modo: string, campo: string, operador: string, valor: string, valor_hasta: string, busqueda: string}
     */
    public static function filtrosVacios(): array
    {
        return [
            'modo' => self::MODO_TODOS,
            'campo' => 'codigo',
            'operador' => 'contiene',
            'valor' => '',
            'valor_hasta' => '',
            'busqueda' => '',
            'empresa_id' => 0,
            'empresas_asignadas' => [],
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
            $params['filtro_campo'] = $filtros['campo'] ?? 'codigo';
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
        if ((int) ($filtros['empresa_id'] ?? 0) > 0) {
            $params['empresa_id'] = (int) $filtros['empresa_id'];
        }

        return $params;
    }

    /**
     * @param  Builder<\App\Models\Caja\RendicionMaquina>  $query
     * @param  array<string, mixed>  $filtros
     */
    public static function aplicarScopeEmpresasAsignadas(Builder $query, array $filtros): void
    {
        $empresaId = (int) ($filtros['empresa_id'] ?? 0);
        if ($empresaId > 0) {
            $query->where('rendicion_maquina.empresa_id', $empresaId);

            return;
        }

        /** @var list<int> $asignadas */
        $asignadas = array_values(array_filter(
            array_map('intval', (array) ($filtros['empresas_asignadas'] ?? [])),
            fn (int $id) => $id > 0,
        ));

        if ($asignadas === []) {
            return;
        }

        $query->whereIn('rendicion_maquina.empresa_id', $asignadas);
    }

    /**
     * @param  Builder<\App\Models\Caja\RendicionMaquina>  $query
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
            self::aplicarEnCampo($query, $filtros['campo'] ?? 'codigo', $operador, $valor, $filtros['valor_hasta'] ?? '');

            return;
        }

        self::aplicarBusquedaGlobal($query, $operador, $valor);
    }

    /**
     * @param  Builder<\App\Models\Caja\RendicionMaquina>  $query
     */
    private static function aplicarBusquedaGlobal(Builder $query, string $operador, string $valor): void
    {
        if ($operador === 'vacio') {
            $query->where(function ($q) {
                foreach (['rendicion_maquina.codigo', 'rendicion_maquina.estado'] as $col) {
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
                $q->orWhere('rendicion_maquina.id', (int) $id);
            }

            $textCols = [
                'rendicion_maquina.codigo',
                'rendicion_maquina.turno',
                'rendicion_maquina.estado',
                'empresa.nombre',
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

            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $valor)) {
                $q->orWhereDate('rendicion_maquina.fecha', $valor);
            }
        });
    }

    private static function usaCoincidenciaFlexibleEnColumna(string $column): bool
    {
        return in_array($column, self::COLUMNAS_COINCIDENCIA_FLEXIBLE, true);
    }

    /**
     * @param  Builder<\App\Models\Caja\RendicionMaquina>  $query
     */
    private static function aplicarEnCampo(Builder $query, string $campoKey, string $operador, string $valor, string $valorHasta): void
    {
        $def = self::CAMPOS[$campoKey] ?? self::CAMPOS['codigo'];
        $type = $def['type'];

        if ($type === 'entero') {
            self::aplicarEntero($query, (string) $def['column'], $operador, $valor);

            return;
        }

        if ($campoKey === 'fecha' && $operador === 'igual' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $valor)) {
            $query->whereDate((string) $def['column'], $valor);

            return;
        }

        if ($campoKey === 'turno' && $valor !== '') {
            try {
                $letra = RendicionMaquinaTurno::normalizar($valor);
                $query->where((string) $def['column'], $letra);

                return;
            } catch (\InvalidArgumentException) {
                // fallback texto
            }
        }

        self::aplicarTexto($query, (string) $def['column'], $operador, $valor);
    }

    /**
     * @param  Builder<\App\Models\Caja\RendicionMaquina>  $query
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
     * @param  Builder<\App\Models\Caja\RendicionMaquina>  $query
     */
    private static function aplicarEntero(Builder $query, string $column, string $operador, string $valor): void
    {
        $id = filter_var(str_replace([',', '.'], ['', '.'], $valor), FILTER_VALIDATE_FLOAT);
        if ($id === false) {
            return;
        }
        $num = (float) $id;
        switch ($operador) {
            case 'mayor':
                $query->where($column, '>', $num);
                break;
            case 'menor':
                $query->where($column, '<', $num);
                break;
            case 'igual':
            default:
                $query->where($column, '=', $num);
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
