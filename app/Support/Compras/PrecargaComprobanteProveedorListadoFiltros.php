<?php

namespace App\Support\Compras;

use App\Support\Compras\PrecargaComprobanteEstados;
use App\Support\Listado\CoincidenciaFlexibleTexto;
use App\Support\Listado\FiltrosListadoRequest;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * Filtros del listado de precarga de comprobantes de proveedor (index / exportaciones).
 */
class PrecargaComprobanteProveedorListadoFiltros
{
    public const MODO_TODOS = 'todos';

    public const MODO_CAMPO = 'campo';

    /** @var array<string, array{column: string, type: string, label: string}> */
    public const CAMPOS = [
        'id' => ['column' => 'precarga_comprobante_proveedor.id', 'type' => 'entero', 'label' => 'ID'],
        'nombreempresa' => ['column' => 'empresa.nombre', 'type' => 'texto', 'label' => 'Empresa'],
        'nombreproveedor' => ['column' => 'proveedor.nombre', 'type' => 'texto', 'label' => 'Proveedor'],
        'nombretipotransaccion' => ['column' => 'tipotransaccion_compra.nombre', 'type' => 'texto', 'label' => 'Tipo de comprobante'],
        'letra' => ['column' => 'precarga_comprobante_proveedor.letra', 'type' => 'texto', 'label' => 'Letra'],
        'sucursal' => ['column' => 'precarga_comprobante_proveedor.sucursal', 'type' => 'entero', 'label' => 'Sucursal'],
        'numerocomprobante' => ['column' => 'precarga_comprobante_proveedor.numerocomprobante', 'type' => 'entero', 'label' => 'Número comprobante'],
        'fechafactura' => ['column' => 'precarga_comprobante_proveedor.fechafactura', 'type' => 'fecha', 'label' => 'Fecha factura'],
        'fecharecepcionemail' => ['column' => 'precarga_comprobante_proveedor.fecharecepcionemail', 'type' => 'fecha', 'label' => 'Fecha email'],
        'numeroordencompra' => ['column' => 'precarga_comprobante_proveedor.numeroordencompra', 'type' => 'texto', 'label' => 'Número OC'],
        'total' => ['column' => 'precarga_comprobante_proveedor.total', 'type' => 'texto', 'label' => 'Total'],
        'estado' => ['column' => 'precarga_comprobante_proveedor.estado', 'type' => 'texto', 'label' => 'Estado'],
        'origen_entrada' => ['column' => 'precarga_comprobante_proveedor.origen_entrada', 'type' => 'texto', 'label' => 'Origen'],
    ];

    /** @var list<string> */
    private const COLUMNAS_COINCIDENCIA_FLEXIBLE = [
        'empresa.nombre',
        'proveedor.nombre',
        'tipotransaccion_compra.nombre',
        'precarga_comprobante_proveedor.numeroordencompra',
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
        $estadoScope = self::resolverEstadoExterno($request);

        if (FiltrosListadoRequest::solicitudLimpiaFiltros($request)) {
            return array_merge(self::filtrosVacios(), [
                'estado_scope' => $estadoScope,
            ]);
        }

        $valor = FiltrosListadoRequest::valorBusqueda($request, $busquedaRuta);
        $busquedaRapida = $request->boolean('filtro_busqueda_rapida');

        $modo = (string) $request->input('filtro_modo', self::MODO_TODOS);
        if (! in_array($modo, [self::MODO_TODOS, self::MODO_CAMPO], true)) {
            $modo = self::MODO_TODOS;
        }

        $campo = (string) $request->input('filtro_campo', 'nombreproveedor');
        if (! isset(self::CAMPOS[$campo])) {
            $campo = 'nombreproveedor';
        }

        $operador = (string) $request->input('filtro_operador', 'contiene');

        if ($busquedaRapida) {
            $modo = self::MODO_TODOS;
            $operador = 'contiene';
        }

        $operador = self::normalizarOperador($operador, $modo === self::MODO_CAMPO ? $campo : 'nombreproveedor');

        return [
            'modo' => $modo,
            'campo' => $campo,
            'operador' => $operador,
            'valor' => $valor,
            'valor_hasta' => trim((string) $request->input('filtro_valor_hasta', '')),
            'busqueda' => $valor,
            'busqueda_rapida' => $busquedaRapida,
            'estado_scope' => $estadoScope,
        ];
    }

    /**
     * Por defecto: solo pendientes. Query `estado=GENERADA|CARGADA_ANITA|PENDIENTE|todas`.
     */
    public static function resolverEstadoExterno(Request $request): string
    {
        $raw = strtoupper(trim((string) $request->input('estado', '')));
        if ($raw === 'TODAS' || $request->boolean('estado_todas')) {
            return 'todas';
        }
        if ($raw === PrecargaComprobanteEstados::GENERADA) {
            return PrecargaComprobanteEstados::GENERADA;
        }
        if ($raw === PrecargaComprobanteEstados::CARGADA_ANITA) {
            return PrecargaComprobanteEstados::CARGADA_ANITA;
        }

        return PrecargaComprobanteEstados::PENDIENTE;
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
     * @return array{modo: string, campo: string, operador: string, valor: string, valor_hasta: string, busqueda: string, estado_scope: string}
     */
    public static function filtrosVacios(): array
    {
        return [
            'modo' => self::MODO_TODOS,
            'campo' => 'nombreproveedor',
            'operador' => 'contiene',
            'valor' => '',
            'valor_hasta' => '',
            'busqueda' => '',
            'estado_scope' => PrecargaComprobanteEstados::PENDIENTE,
        ];
    }

    /**
     * @return array<string, string|int|bool>
     */
    public static function paraQueryString(array $filtros): array
    {
        $params = self::paraQueryStringEstado($filtros);
        if (($filtros['modo'] ?? self::MODO_TODOS) !== self::MODO_TODOS) {
            $params['filtro_modo'] = $filtros['modo'];
        }
        if (($filtros['modo'] ?? '') === self::MODO_CAMPO) {
            $params['filtro_campo'] = $filtros['campo'] ?? 'nombreproveedor';
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
     * @return array<string, string|int>
     */
    public static function paraQueryStringEstado(array $filtros): array
    {
        $scope = (string) ($filtros['estado_scope'] ?? PrecargaComprobanteEstados::PENDIENTE);
        if ($scope === 'todas') {
            return ['estado_todas' => 1];
        }
        if ($scope === PrecargaComprobanteEstados::GENERADA) {
            return ['estado' => PrecargaComprobanteEstados::GENERADA];
        }
        if ($scope === PrecargaComprobanteEstados::CARGADA_ANITA) {
            return ['estado' => PrecargaComprobanteEstados::CARGADA_ANITA];
        }

        // Default pendiente: no hace falta query param (limpiar vuelve a pendientes).
        return [];
    }

    /**
     * @param  Builder<\App\Models\Compras\Precarga_Comprobante_Proveedor>  $query
     */
    public static function aplicar(Builder $query, array $filtros): void
    {
        self::aplicarEstadoExterno($query, $filtros);

        $valor = trim((string) ($filtros['valor'] ?? ''));
        if ($valor === '' && ($filtros['operador'] ?? '') !== 'vacio' && trim((string) ($filtros['valor_hasta'] ?? '')) === '') {
            return;
        }

        $modo = $filtros['modo'] ?? self::MODO_TODOS;
        $operador = $filtros['operador'] ?? 'contiene';

        if ($modo === self::MODO_CAMPO) {
            self::aplicarEnCampo($query, $filtros['campo'] ?? 'nombreproveedor', $operador, $valor, $filtros['valor_hasta'] ?? '');

            return;
        }

        self::aplicarBusquedaGlobal($query, $operador, $valor);
    }

    /**
     * @param  Builder<\App\Models\Compras\Precarga_Comprobante_Proveedor>  $query
     */
    public static function aplicarEstadoExterno(Builder $query, array $filtros): void
    {
        $scope = (string) ($filtros['estado_scope'] ?? PrecargaComprobanteEstados::PENDIENTE);
        if ($scope === 'todas') {
            return;
        }
        if ($scope === PrecargaComprobanteEstados::GENERADA) {
            $query->where('precarga_comprobante_proveedor.estado', PrecargaComprobanteEstados::GENERADA);

            return;
        }
        if ($scope === PrecargaComprobanteEstados::CARGADA_ANITA) {
            $query->where('precarga_comprobante_proveedor.estado', PrecargaComprobanteEstados::CARGADA_ANITA);

            return;
        }

        $query->where('precarga_comprobante_proveedor.estado', PrecargaComprobanteEstados::PENDIENTE);
    }

    /**
     * @param  Builder<\App\Models\Compras\Precarga_Comprobante_Proveedor>  $query
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
                $q->orWhere('precarga_comprobante_proveedor.id', (int) $id)
                    ->orWhere('precarga_comprobante_proveedor.sucursal', (int) $id)
                    ->orWhere('precarga_comprobante_proveedor.numerocomprobante', (int) $id);
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
                $q->orWhereDate('precarga_comprobante_proveedor.fechafactura', '=', $fecha)
                    ->orWhereDate('precarga_comprobante_proveedor.fecharecepcionemail', '=', $fecha);
            }
        });
    }

    /** @return list<string> */
    private static function columnasTextoBusquedaGlobal(): array
    {
        return [
            'empresa.nombre',
            'proveedor.nombre',
            'tipotransaccion_compra.nombre',
            'precarga_comprobante_proveedor.letra',
            'precarga_comprobante_proveedor.numeroordencompra',
            'precarga_comprobante_proveedor.total',
            'precarga_comprobante_proveedor.estado',
            'precarga_comprobante_proveedor.origen_entrada',
        ];
    }

    private static function usaCoincidenciaFlexibleEnColumna(string $column): bool
    {
        return in_array($column, self::COLUMNAS_COINCIDENCIA_FLEXIBLE, true);
    }

    /**
     * @param  Builder<\App\Models\Compras\Precarga_Comprobante_Proveedor>  $query
     */
    private static function aplicarEnCampo(Builder $query, string $campoKey, string $operador, string $valor, string $valorHasta): void
    {
        $def = self::CAMPOS[$campoKey] ?? self::CAMPOS['nombreproveedor'];
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
     * @param  Builder<\App\Models\Compras\Precarga_Comprobante_Proveedor>  $query
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
     * @param  Builder<\App\Models\Compras\Precarga_Comprobante_Proveedor>  $query
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
     * @param  Builder<\App\Models\Compras\Precarga_Comprobante_Proveedor>  $query
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
