<?php

namespace App\Support\Compras;

use App\Support\Listado\CoincidenciaFlexibleTexto;
use App\Support\Listado\FiltrosListadoRequest;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

/**
 * Filtros del listado de requisiciones (index / exportaciones).
 */
class RequisicionListadoFiltros
{
    public const MODO_TODOS = 'todos';

    public const MODO_CAMPO = 'campo';

    /** @var array<string, array{column: string, type: string, label: string}> */
    public const CAMPOS = [
        'id' => ['column' => 'requisicion.id', 'type' => 'entero', 'label' => 'ID'],
        'numerorequisicion' => ['column' => 'requisicion.numerorequisicion', 'type' => 'entero', 'label' => 'Número'],
        'nombreusuario' => ['column' => 'usuario.nombre', 'type' => 'texto', 'label' => 'Solicitante'],
        'usuario' => ['column' => 'usuario.usuario', 'type' => 'texto', 'label' => 'Usuario (login)'],
        'fecha' => ['column' => 'requisicion.fecha', 'type' => 'fecha', 'label' => 'Fecha'],
        'fechaentrega' => ['column' => 'requisicion.fechaentrega', 'type' => 'fecha', 'label' => 'Fecha entrega'],
        'nombreempresa' => ['column' => 'empresa.nombre', 'type' => 'texto', 'label' => 'Empresa'],
        'nombrecentrocosto' => ['column' => 'centrocosto.nombre', 'type' => 'texto', 'label' => 'Centro costo'],
        'codigocentrocosto' => ['column' => 'centrocosto.codigo', 'type' => 'texto', 'label' => 'Cód. centro costo'],
        'nombreproveedor' => ['column' => 'proveedor.nombre', 'type' => 'texto', 'label' => 'Proveedor'],
        'codigoproveedor' => ['column' => 'proveedor.codigo', 'type' => 'texto', 'label' => 'Cód. proveedor'],
        'nombreoficinacompra' => ['column' => 'oficinacompra.nombre', 'type' => 'texto', 'label' => 'Oficina compra'],
        'nombreformapago' => ['column' => 'formapago.nombre', 'type' => 'texto', 'label' => 'Forma de pago'],
        'tratamiento' => ['column' => 'requisicion.tratamiento', 'type' => 'texto', 'label' => 'Tratamiento'],
        'motivotratamiento' => ['column' => 'requisicion.motivotratamiento', 'type' => 'texto', 'label' => 'Motivo tratamiento'],
        'contrataciondirecta' => ['column' => 'requisicion.contrataciondirecta', 'type' => 'texto', 'label' => 'Contratación directa'],
        'estado' => ['column' => 'requisicion.estado', 'type' => 'texto', 'label' => 'Estado'],
        'comentario' => ['column' => 'requisicion.comentario', 'type' => 'texto', 'label' => 'Comentario'],
        'detalle' => ['column' => 'requisicion.detalle', 'type' => 'texto', 'label' => 'Detalle cabecera'],
        'nroinscripcion' => ['column' => 'requisicion.nroinscripcion', 'type' => 'texto', 'label' => 'Nro inscripción'],
    ];

    /** @var list<string> */
    private const COLUMNAS_COINCIDENCIA_FLEXIBLE = [
        'empresa.nombre',
        'centrocosto.nombre',
        'centrocosto.codigo',
        'proveedor.nombre',
        'proveedor.codigo',
        'usuario.nombre',
        'oficinacompra.nombre',
        'formapago.nombre',
        'requisicion.comentario',
        'requisicion.detalle',
        'requisicion.motivotratamiento',
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
    public const OPERADORES_FECHA = [
        'igual' => 'Igual a',
        'desde' => 'Desde (≥)',
        'hasta' => 'Hasta (≤)',
        'entre' => 'Entre',
        'vacio' => 'Sin fecha',
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

        $campo = (string) $request->input('filtro_campo', 'numerorequisicion');
        if (! isset(self::CAMPOS[$campo])) {
            $campo = 'numerorequisicion';
        }

        $operador = (string) $request->input('filtro_operador', 'contiene');

        if ($busquedaRapida) {
            $modo = self::MODO_TODOS;
            $operador = 'contiene';
        }

        $operador = self::normalizarOperador($operador, $modo === self::MODO_CAMPO ? $campo : 'numerorequisicion');

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
            'campo' => 'numerorequisicion',
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
            $params['filtro_campo'] = $filtros['campo'] ?? 'numerorequisicion';
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
     * @param  Builder<\App\Models\Compras\Requisicion>  $query
     */
    public static function aplicar(Builder $query, array $filtros): void
    {
        $valor = trim((string) ($filtros['valor'] ?? ''));
        if ($valor === '' && ($filtros['operador'] ?? '') !== 'vacio' && trim((string) ($filtros['valor_hasta'] ?? '')) === '') {
            return;
        }

        $modo = $filtros['modo'] ?? self::MODO_TODOS;
        $operador = $filtros['operador'] ?? 'contiene';

        if ($modo === self::MODO_CAMPO) {
            self::aplicarEnCampo($query, $filtros['campo'] ?? 'numerorequisicion', $operador, $valor, $filtros['valor_hasta'] ?? '');

            return;
        }

        self::aplicarBusquedaGlobal($query, $operador, $valor);
    }

    /**
     * @param  Builder<\App\Models\Compras\Requisicion>  $query
     */
    private static function aplicarBusquedaGlobal(Builder $query, string $operador, string $valor): void
    {
        if ($operador === 'vacio') {
            return;
        }

        if ($valor === '') {
            return;
        }

        $id = filter_var($valor, FILTER_VALIDATE_INT);
        $like = self::patronLike($operador, $valor);

        $query->where(function ($q) use ($valor, $like, $id, $operador) {
            if ($id !== false) {
                $q->orWhere('requisicion.id', (int) $id)
                    ->orWhere('requisicion.numerorequisicion', (int) $id);
            }

            foreach (self::columnasTextoBusquedaGlobal() as $col) {
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

            $fecha = self::parsearFecha($valor);
            if ($fecha) {
                $q->orWhereDate('requisicion.fecha', '=', $fecha)
                    ->orWhereDate('requisicion.fechaentrega', '=', $fecha);
            }
        });
    }

    /** @return list<string> */
    private static function columnasTextoBusquedaGlobal(): array
    {
        $cols = [
            'requisicion.numerorequisicion',
            'empresa.nombre',
            'requisicion.tratamiento',
            'requisicion.motivotratamiento',
            'requisicion.contrataciondirecta',
            'centrocosto.nombre',
            'centrocosto.codigo',
            'requisicion.comentario',
            'requisicion.detalle',
            'requisicion.estado',
            'usuario.nombre',
            'usuario.usuario',
            'proveedor.nombre',
            'proveedor.codigo',
            'oficinacompra.nombre',
            'formapago.nombre',
        ];

        if (Schema::hasColumn('requisicion', 'nroinscripcion')) {
            $cols[] = 'requisicion.nroinscripcion';
        }

        return $cols;
    }

    private static function usaCoincidenciaFlexibleEnColumna(string $column): bool
    {
        return in_array($column, self::COLUMNAS_COINCIDENCIA_FLEXIBLE, true);
    }

    /**
     * @param  Builder<\App\Models\Compras\Requisicion>  $query
     */
    private static function aplicarEnCampo(Builder $query, string $campoKey, string $operador, string $valor, string $valorHasta): void
    {
        if ($campoKey === 'nroinscripcion' && ! Schema::hasColumn('requisicion', 'nroinscripcion')) {
            return;
        }

        $def = self::CAMPOS[$campoKey] ?? self::CAMPOS['numerorequisicion'];
        $type = $def['type'];

        if ($type === 'entero') {
            self::aplicarEntero($query, (string) $def['column'], $operador, $valor);

            return;
        }

        if ($type === 'fecha') {
            self::aplicarFechaColumna($query, (string) $def['column'], $operador, $valor, $valorHasta);

            return;
        }

        self::aplicarTexto($query, (string) $def['column'], $operador, $valor);
    }

    /**
     * @param  Builder<\App\Models\Compras\Requisicion>  $query
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
     * @param  Builder<\App\Models\Compras\Requisicion>  $query
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

    /**
     * @param  Builder<\App\Models\Compras\Requisicion>  $query
     */
    private static function aplicarFechaColumna(Builder $query, string $column, string $operador, string $valor, string $valorHasta): void
    {
        if ($operador === 'vacio') {
            $query->whereNull($column);

            return;
        }

        $desde = self::parsearFecha($valor);
        $hasta = self::parsearFecha($valorHasta);

        switch ($operador) {
            case 'desde':
                if ($desde) {
                    $query->whereDate($column, '>=', $desde);
                }
                break;
            case 'hasta':
                if ($desde) {
                    $query->whereDate($column, '<=', $desde);
                }
                break;
            case 'entre':
                if ($desde && $hasta) {
                    $query->whereDate($column, '>=', $desde)->whereDate($column, '<=', $hasta);
                } elseif ($desde) {
                    $query->whereDate($column, '>=', $desde);
                } elseif ($hasta) {
                    $query->whereDate($column, '<=', $hasta);
                }
                break;
            case 'igual':
            default:
                if ($desde) {
                    $query->whereDate($column, '=', $desde);
                }
                break;
        }
    }

    private static function parsearFecha(string $valor): ?string
    {
        $valor = trim($valor);
        if ($valor === '') {
            return null;
        }

        foreach (['Y-m-d', 'd/m/Y', 'd-m-Y'] as $fmt) {
            try {
                return Carbon::createFromFormat($fmt, $valor)->format('Y-m-d');
            } catch (\Throwable $e) {
                continue;
            }
        }

        return null;
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
            'fecha' => array_keys(self::OPERADORES_FECHA),
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
            'fecha' => self::OPERADORES_FECHA,
            default => self::OPERADORES_TEXTO,
        };
    }
}
