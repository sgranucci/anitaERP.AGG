<?php

namespace App\Support\Caja;

use App\Support\Listado\CoincidenciaFlexibleTexto;
use App\Support\Listado\FiltrosListadoRequest;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * Filtros del listado de pérdidas de personal.
 */
class PerdidaPersonalListadoFiltros
{
    public const MODO_TODOS = 'todos';

    public const MODO_CAMPO = 'campo';

    /** @var array<string, array{column: string, type: string, label: string}> */
    public const CAMPOS = [
        'id' => ['column' => 'perdida_personal.id', 'type' => 'entero', 'label' => 'ID'],
        'numero' => ['column' => 'perdida_personal.numero', 'type' => 'entero', 'label' => 'Número'],
        'fecha' => ['column' => 'perdida_personal.fecha', 'type' => 'texto', 'label' => 'Fecha'],
        'empresa' => ['column' => 'empresa.nombre', 'type' => 'texto', 'label' => 'Empresa'],
        'empleado' => ['column' => 'empleado_sueldos.nombre', 'type' => 'texto', 'label' => 'Empleado'],
        'supervisor' => ['column' => 'supervisor_sueldos.nombre', 'type' => 'texto', 'label' => 'Supervisor'],
        'concepto' => ['column' => 'concepto_perdida.nombre', 'type' => 'texto', 'label' => 'Concepto'],
        'imputacion' => ['column' => 'imputacion_perdida.nombre', 'type' => 'texto', 'label' => 'Imputación'],
        'maquina' => ['column' => 'perdida_personal.maquina', 'type' => 'texto', 'label' => 'Máquina'],
        'importe' => ['column' => 'perdida_personal.importe', 'type' => 'entero', 'label' => 'Importe'],
        'estado' => ['column' => 'perdida_personal.estado', 'type' => 'texto', 'label' => 'Estado'],
        'leyenda' => ['column' => 'perdida_personal.leyenda', 'type' => 'texto', 'label' => 'Leyenda'],
        'turno' => ['column' => 'perdida_personal.turno', 'type' => 'texto', 'label' => 'Turno'],
    ];

    /** @var list<string> */
    private const COLUMNAS_COINCIDENCIA_FLEXIBLE = [
        'perdida_personal.leyenda',
        'empresa.nombre',
        'empleado_sueldos.nombre',
        'supervisor_sueldos.nombre',
        'concepto_perdida.nombre',
        'imputacion_perdida.nombre',
        'perdida_personal.maquina',
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

    /**
     * @param  Builder<\App\Models\Caja\PerdidaPersonal>  $query
     * @param  array<string, mixed>  $filtros
     */
    public static function aplicarJoinsListado(Builder $query, array $filtros): void
    {
        if (! self::requiereJoins($filtros)) {
            return;
        }

        $query
            ->leftJoin('empresa', 'empresa.id', '=', 'perdida_personal.empresa_id')
            ->leftJoin('empleado_sueldos', 'empleado_sueldos.id', '=', 'perdida_personal.empleado_sueldos_id')
            ->leftJoin('empleado_sueldos as supervisor_sueldos', 'supervisor_sueldos.id', '=', 'perdida_personal.supervisor_empleado_sueldos_id')
            ->leftJoin('concepto_perdida', 'concepto_perdida.id', '=', 'perdida_personal.concepto_perdida_id')
            ->leftJoin('imputacion_perdida', 'imputacion_perdida.id', '=', 'perdida_personal.imputacion_perdida_id');
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    private static function requiereJoins(array $filtros): bool
    {
        if (! self::tieneCriteriosAplicados($filtros)) {
            return false;
        }

        // empresa_id filtra por columna directa; no requiere join solo por eso
        $modo = $filtros['modo'] ?? self::MODO_TODOS;
        if ($modo === self::MODO_TODOS) {
            return trim((string) ($filtros['valor'] ?? '')) !== ''
                || ($filtros['operador'] ?? '') === 'vacio';
        }

        $campo = (string) ($filtros['campo'] ?? '');

        return in_array($campo, ['empresa', 'empleado', 'supervisor', 'concepto', 'imputacion'], true);
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

        $campo = (string) $request->input('filtro_campo', 'numero');
        if (! isset(self::CAMPOS[$campo])) {
            $campo = 'numero';
        }

        $operador = (string) $request->input('filtro_operador', 'contiene');

        if ($busquedaRapida) {
            $modo = self::MODO_TODOS;
            $operador = 'contiene';
        }

        $operador = self::normalizarOperador($operador, $modo === self::MODO_CAMPO ? $campo : 'numero');

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
     * @return array{modo: string, campo: string, operador: string, valor: string, valor_hasta: string, busqueda: string, empresa_id: int, empresas_asignadas: list<int>}
     */
    public static function filtrosVacios(): array
    {
        return [
            'modo' => self::MODO_TODOS,
            'campo' => 'numero',
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
            $params['filtro_campo'] = $filtros['campo'] ?? 'numero';
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
     * Filtro empresa_id y empresas_asignadas sobre perdida_personal.empresa_id (columna directa).
     *
     * @param  Builder<\App\Models\Caja\PerdidaPersonal>  $query
     * @param  array<string, mixed>  $filtros
     */
    public static function aplicarScopeEmpresasAsignadas(Builder $query, array $filtros): void
    {
        $empresaId = (int) ($filtros['empresa_id'] ?? 0);
        if ($empresaId > 0) {
            $query->where('perdida_personal.empresa_id', $empresaId);

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

        $query->whereIn('perdida_personal.empresa_id', $asignadas);
    }

    /**
     * @param  Builder<\App\Models\Caja\PerdidaPersonal>  $query
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
            self::aplicarEnCampo($query, $filtros['campo'] ?? 'numero', $operador, $valor, $filtros['valor_hasta'] ?? '');

            return;
        }

        self::aplicarBusquedaGlobal($query, $operador, $valor);
    }

    /**
     * @param  Builder<\App\Models\Caja\PerdidaPersonal>  $query
     */
    private static function aplicarBusquedaGlobal(Builder $query, string $operador, string $valor): void
    {
        if ($operador === 'vacio') {
            $query->where(function ($q) {
                foreach (['perdida_personal.leyenda', 'perdida_personal.maquina'] as $col) {
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
                $q->orWhere('perdida_personal.id', (int) $id)
                    ->orWhere('perdida_personal.numero', (int) $id);
            }

            $textCols = [
                'perdida_personal.leyenda',
                'perdida_personal.maquina',
                'perdida_personal.estado',
                'perdida_personal.turno',
                'empresa.nombre',
                'empleado_sueldos.nombre',
                'supervisor_sueldos.nombre',
                'concepto_perdida.nombre',
                'imputacion_perdida.nombre',
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
        });
    }

    private static function usaCoincidenciaFlexibleEnColumna(string $column): bool
    {
        return in_array($column, self::COLUMNAS_COINCIDENCIA_FLEXIBLE, true);
    }

    /**
     * @param  Builder<\App\Models\Caja\PerdidaPersonal>  $query
     */
    private static function aplicarEnCampo(Builder $query, string $campoKey, string $operador, string $valor, string $valorHasta): void
    {
        $def = self::CAMPOS[$campoKey] ?? self::CAMPOS['numero'];
        $type = $def['type'];

        if ($type === 'entero') {
            self::aplicarEntero($query, (string) $def['column'], $operador, $valor);

            return;
        }

        self::aplicarTexto($query, (string) $def['column'], $operador, $valor);
    }

    /**
     * @param  Builder<\App\Models\Caja\PerdidaPersonal>  $query
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
     * @param  Builder<\App\Models\Caja\PerdidaPersonal>  $query
     */
    private static function aplicarEntero(Builder $query, string $column, string $operador, string $valor): void
    {
        $id = filter_var($valor, FILTER_VALIDATE_INT);
        if ($id === false) {
            // importe puede venir con decimales
            if (is_numeric($valor) && $column === 'perdida_personal.importe') {
                $num = (float) $valor;
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
