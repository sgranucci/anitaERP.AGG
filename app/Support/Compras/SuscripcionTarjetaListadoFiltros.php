<?php

namespace App\Support\Compras;

use App\Support\Listado\CoincidenciaFlexibleTexto;
use App\Support\Listado\FiltrosListadoRequest;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * Filtros del listado de tarjetas corporativas de suscripciones (index / exportaciones).
 *
 * Empresa es eje externo (botones). El resto usa el panel de filtros inteligentes.
 */
class SuscripcionTarjetaListadoFiltros
{
    public const MODO_TODOS = 'todos';

    public const MODO_CAMPO = 'campo';

    /** @var array<string, array{column: string, type: string, label: string}> */
    public const CAMPOS = [
        'id' => ['column' => 'suscripcion_tarjeta.id', 'type' => 'entero', 'label' => 'ID'],
        'etiqueta' => ['column' => 'suscripcion_tarjeta.etiqueta', 'type' => 'texto', 'label' => 'Etiqueta'],
        'ult4' => ['column' => 'suscripcion_tarjeta.ult4', 'type' => 'texto', 'label' => 'Últimos 4'],
        'emisor' => ['column' => 'suscripcion_tarjeta.emisor', 'type' => 'texto', 'label' => 'Emisor'],
        'area' => ['column' => 'suscripcion_tarjeta.area', 'type' => 'texto', 'label' => 'Área'],
        'empresa' => ['column' => 'empresa.nombre', 'type' => 'texto', 'label' => 'Empresa'],
        'centrocosto' => ['column' => 'centrocosto.nombre', 'type' => 'texto', 'label' => 'Centro de costo'],
        'responsable' => ['column' => 'usuario.nombre', 'type' => 'texto', 'label' => 'Responsable'],
        'observacion' => ['column' => 'suscripcion_tarjeta.observacion', 'type' => 'texto', 'label' => 'Observación'],
        'estado' => ['column' => 'suscripcion_tarjeta.activo', 'type' => 'estado', 'label' => 'Estado'],
        'imputacion' => ['column' => 'suscripcion_tarjeta.cuentacaja_id', 'type' => 'imputacion', 'label' => 'Imputación'],
    ];

    /** @var list<string> */
    private const COLUMNAS_COINCIDENCIA_FLEXIBLE = [
        'suscripcion_tarjeta.etiqueta',
        'suscripcion_tarjeta.emisor',
        'suscripcion_tarjeta.area',
        'suscripcion_tarjeta.observacion',
        'empresa.nombre',
        'centrocosto.nombre',
        'usuario.nombre',
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
    public const OPERADORES_ESTADO = [
        'igual' => 'Igual a',
    ];

    /** @var array<string, string> */
    public const OPERADORES_IMPUTACION = [
        'igual' => 'Igual a',
    ];

    public static function resolverDesdeRequest(Request $request, ?string $busquedaRuta = null): array
    {
        $empresaId = $request->filled('empresa_id') ? (int) $request->input('empresa_id') : null;

        if (FiltrosListadoRequest::solicitudLimpiaFiltros($request)) {
            return array_merge(self::filtrosVacios(), [
                'empresa_id' => $empresaId && $empresaId > 0 ? $empresaId : null,
            ]);
        }

        $valor = FiltrosListadoRequest::valorBusqueda($request, $busquedaRuta);
        $busquedaRapida = $request->boolean('filtro_busqueda_rapida');

        $modo = (string) $request->input('filtro_modo', self::MODO_TODOS);
        if (! in_array($modo, [self::MODO_TODOS, self::MODO_CAMPO], true)) {
            $modo = self::MODO_TODOS;
        }

        $campo = (string) $request->input('filtro_campo', 'etiqueta');
        if (! isset(self::CAMPOS[$campo])) {
            $campo = 'etiqueta';
        }

        $operador = (string) $request->input('filtro_operador', 'contiene');

        if ($busquedaRapida) {
            $modo = self::MODO_TODOS;
            $operador = 'contiene';
        }

        $operador = self::normalizarOperador($operador, $modo === self::MODO_CAMPO ? $campo : 'etiqueta');

        return [
            'modo' => $modo,
            'campo' => $campo,
            'operador' => $operador,
            'valor' => $valor,
            'valor_hasta' => trim((string) $request->input('filtro_valor_hasta', '')),
            'busqueda' => $valor,
            'busqueda_rapida' => $busquedaRapida,
            'empresa_id' => $empresaId && $empresaId > 0 ? $empresaId : null,
        ];
    }

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

    public static function tieneCriteriosAplicados(array $filtros): bool
    {
        return self::tieneCriteriosTexto($filtros);
    }

    /**
     * @return array{
     *     modo: string, campo: string, operador: string, valor: string, valor_hasta: string,
     *     busqueda: string, empresa_id: ?int
     * }
     */
    public static function filtrosVacios(): array
    {
        return [
            'modo' => self::MODO_TODOS,
            'campo' => 'etiqueta',
            'operador' => 'contiene',
            'valor' => '',
            'valor_hasta' => '',
            'busqueda' => '',
            'empresa_id' => null,
        ];
    }

    /**
     * @return array<string, string|int>
     */
    public static function paraQueryString(array $filtros): array
    {
        $params = self::paraQueryStringEmpresa($filtros);

        if (($filtros['modo'] ?? self::MODO_TODOS) !== self::MODO_TODOS) {
            $params['filtro_modo'] = $filtros['modo'];
        }
        if (($filtros['modo'] ?? '') === self::MODO_CAMPO) {
            $params['filtro_campo'] = $filtros['campo'] ?? 'etiqueta';
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
     * Solo el filtro externo de empresa (para Limpiar texto sin perder empresa).
     *
     * @return array<string, int>
     */
    public static function paraQueryStringEmpresa(array $filtros): array
    {
        if (! empty($filtros['empresa_id'])) {
            return ['empresa_id' => (int) $filtros['empresa_id']];
        }

        return [];
    }

    /** Alias usado por QueryRetornoListado / patrones del ERP. */
    public static function paraQueryStringExternos(array $filtros): array
    {
        return self::paraQueryStringEmpresa($filtros);
    }

    /**
     * @param  Builder<\App\Models\Compras\Suscripcion_Tarjeta>  $query
     */
    public static function aplicar(Builder $query, array $filtros): void
    {
        if (! empty($filtros['empresa_id'])) {
            $query->where('suscripcion_tarjeta.empresa_id', (int) $filtros['empresa_id']);
        }

        if (! self::tieneCriteriosTexto($filtros)) {
            return;
        }

        $valor = trim((string) ($filtros['valor'] ?? ''));
        $modo = $filtros['modo'] ?? self::MODO_TODOS;
        $operador = $filtros['operador'] ?? 'contiene';

        if ($modo === self::MODO_CAMPO) {
            self::aplicarEnCampo($query, $filtros['campo'] ?? 'etiqueta', $operador, $valor);

            return;
        }

        self::aplicarBusquedaGlobal($query, $operador, $valor);
    }

    /**
     * @param  Builder<\App\Models\Compras\Suscripcion_Tarjeta>  $query
     */
    private static function aplicarBusquedaGlobal(Builder $query, string $operador, string $valor): void
    {
        if ($operador === 'vacio' || $valor === '') {
            return;
        }

        $id = filter_var($valor, FILTER_VALIDATE_INT);
        $like = self::patronLike($operador, $valor);
        $valorNorm = mb_strtolower($valor);

        $query->where(function ($q) use ($valor, $like, $id, $operador, $valorNorm) {
            if ($id !== false) {
                $q->orWhere('suscripcion_tarjeta.id', (int) $id)
                    ->orWhere('suscripcion_tarjeta.ult4', 'like', $like);
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

            if (str_contains($valorNorm, 'activ') && ! str_contains($valorNorm, 'inactiv')) {
                $q->orWhere('suscripcion_tarjeta.activo', true);
            }
            if (str_contains($valorNorm, 'inactiv')) {
                $q->orWhere('suscripcion_tarjeta.activo', false);
            }
            if (str_contains($valorNorm, 'incompleta')) {
                $q->orWhere(function ($w) {
                    $w->whereNull('suscripcion_tarjeta.cuentacaja_id')
                        ->orWhere('suscripcion_tarjeta.cuentacaja_id', 0)
                        ->orWhereNull('suscripcion_tarjeta.tipotransaccion_caja_id')
                        ->orWhere('suscripcion_tarjeta.tipotransaccion_caja_id', 0);
                });
            } elseif (str_contains($valorNorm, 'lista') || str_contains($valorNorm, 'completa')) {
                $q->orWhere(function ($w) {
                    $w->whereNotNull('suscripcion_tarjeta.cuentacaja_id')
                        ->where('suscripcion_tarjeta.cuentacaja_id', '>', 0)
                        ->whereNotNull('suscripcion_tarjeta.tipotransaccion_caja_id')
                        ->where('suscripcion_tarjeta.tipotransaccion_caja_id', '>', 0);
                });
            }
        });
    }

    /** @return list<string> */
    private static function columnasTextoBusquedaGlobal(): array
    {
        return [
            'suscripcion_tarjeta.etiqueta',
            'suscripcion_tarjeta.ult4',
            'suscripcion_tarjeta.emisor',
            'suscripcion_tarjeta.area',
            'suscripcion_tarjeta.observacion',
            'empresa.nombre',
            'centrocosto.nombre',
            'centrocosto.codigo',
            'usuario.nombre',
        ];
    }

    private static function usaCoincidenciaFlexibleEnColumna(string $column): bool
    {
        return in_array($column, self::COLUMNAS_COINCIDENCIA_FLEXIBLE, true);
    }

    /**
     * @param  Builder<\App\Models\Compras\Suscripcion_Tarjeta>  $query
     */
    private static function aplicarEnCampo(Builder $query, string $campoKey, string $operador, string $valor): void
    {
        $def = self::CAMPOS[$campoKey] ?? self::CAMPOS['etiqueta'];
        $type = $def['type'];

        if ($type === 'entero') {
            self::aplicarEntero($query, (string) $def['column'], $operador, $valor);

            return;
        }

        if ($type === 'estado') {
            self::aplicarEstado($query, $operador, $valor);

            return;
        }

        if ($type === 'imputacion') {
            self::aplicarImputacion($query, $operador, $valor);

            return;
        }

        self::aplicarTexto($query, (string) $def['column'], $operador, $valor);
    }

    /**
     * @param  Builder<\App\Models\Compras\Suscripcion_Tarjeta>  $query
     */
    private static function aplicarEstado(Builder $query, string $operador, string $valor): void
    {
        if ($valor === '') {
            return;
        }

        $valorNorm = mb_strtolower(trim($valor));
        $activo = match (true) {
            in_array($valorNorm, ['1', 'si', 'sí', 'true', 'activa', 'activo', 'a'], true) => true,
            str_contains($valorNorm, 'activ') && ! str_contains($valorNorm, 'inactiv') => true,
            in_array($valorNorm, ['0', 'no', 'false', 'inactiva', 'inactivo', 'i'], true) => false,
            str_contains($valorNorm, 'inactiv') => false,
            default => null,
        };

        if ($activo === null) {
            return;
        }

        if ($operador === 'distinto') {
            $query->where('suscripcion_tarjeta.activo', ! $activo);

            return;
        }

        $query->where('suscripcion_tarjeta.activo', $activo);
    }

    /**
     * @param  Builder<\App\Models\Compras\Suscripcion_Tarjeta>  $query
     */
    private static function aplicarImputacion(Builder $query, string $operador, string $valor): void
    {
        if ($valor === '') {
            return;
        }

        $valorNorm = mb_strtolower(trim($valor));
        $lista = match (true) {
            str_contains($valorNorm, 'incompleta') => false,
            str_contains($valorNorm, 'lista') || str_contains($valorNorm, 'completa') => true,
            default => null,
        };

        if ($lista === null) {
            return;
        }

        $quiereLista = $operador === 'distinto' ? ! $lista : $lista;

        if ($quiereLista) {
            $query->whereNotNull('suscripcion_tarjeta.cuentacaja_id')
                ->where('suscripcion_tarjeta.cuentacaja_id', '>', 0)
                ->whereNotNull('suscripcion_tarjeta.tipotransaccion_caja_id')
                ->where('suscripcion_tarjeta.tipotransaccion_caja_id', '>', 0);

            return;
        }

        $query->where(function ($w) {
            $w->whereNull('suscripcion_tarjeta.cuentacaja_id')
                ->orWhere('suscripcion_tarjeta.cuentacaja_id', 0)
                ->orWhereNull('suscripcion_tarjeta.tipotransaccion_caja_id')
                ->orWhere('suscripcion_tarjeta.tipotransaccion_caja_id', 0);
        });
    }

    /**
     * @param  Builder<\App\Models\Compras\Suscripcion_Tarjeta>  $query
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
     * @param  Builder<\App\Models\Compras\Suscripcion_Tarjeta>  $query
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
            'estado' => array_keys(self::OPERADORES_ESTADO),
            'imputacion' => array_keys(self::OPERADORES_IMPUTACION),
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
            'estado' => self::OPERADORES_ESTADO,
            'imputacion' => self::OPERADORES_IMPUTACION,
            default => self::OPERADORES_TEXTO,
        };
    }
}
