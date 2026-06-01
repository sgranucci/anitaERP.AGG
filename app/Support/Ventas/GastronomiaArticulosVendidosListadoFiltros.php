<?php

namespace App\Support\Ventas;

use App\Support\Listado\FiltrosListadoRequest;
use Illuminate\Http\Request;

/**
 * Filtros del listado de artículos vendidos gastronomía (index / exportaciones).
 */
class GastronomiaArticulosVendidosListadoFiltros
{
    public const MODO_TODOS = 'todos';

    public const MODO_CAMPO = 'campo';

    /** @var array<string, array{prop: string, type: string, label: string}> */
    public const CAMPOS = [
        'sku' => ['prop' => 'sku', 'type' => 'texto', 'label' => 'SKU'],
        'descripcion' => ['prop' => 'descripcion', 'type' => 'texto', 'label' => 'Descripción'],
        'deposito' => ['prop' => 'deposito_etiqueta', 'type' => 'texto', 'label' => 'Depósito'],
        'puntoventa' => ['prop' => 'puntoventa_etiqueta', 'type' => 'texto', 'label' => 'Punto de venta'],
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

        $campo = (string) $request->input('filtro_campo', 'descripcion');
        if (! isset(self::CAMPOS[$campo])) {
            $campo = 'descripcion';
        }

        $operador = (string) $request->input('filtro_operador', 'contiene');
        if ($busquedaRapida) {
            $modo = self::MODO_TODOS;
            $operador = 'contiene';
        }

        $operador = self::normalizarOperador($operador);

        return [
            'modo' => $modo,
            'campo' => $campo,
            'operador' => $operador,
            'valor' => $valor,
            'valor_hasta' => trim((string) $request->input('filtro_valor_hasta', '')),
            'busqueda' => $valor,
            'busqueda_rapida' => $busquedaRapida,
            'empresa_id' => (int) $request->input('empresa_id', 0),
            'puntoventa_id' => (int) $request->input('puntoventa_id', 0),
            'deposito_id' => (int) $request->input('deposito_id', 0),
            'jornada_id' => (int) $request->input('jornada_id', 0),
            'fecha_desde' => trim((string) $request->input('fecha_desde', '')),
            'fecha_hasta' => trim((string) $request->input('fecha_hasta', '')),
        ];
    }

    public static function tieneCriteriosAplicados(array $filtros): bool
    {
        if ((int) ($filtros['empresa_id'] ?? 0) > 0) {
            return true;
        }
        if ((int) ($filtros['puntoventa_id'] ?? 0) > 0) {
            return true;
        }
        if ((int) ($filtros['deposito_id'] ?? 0) > 0) {
            return true;
        }
        if ((int) ($filtros['jornada_id'] ?? 0) > 0) {
            return true;
        }
        if (trim((string) ($filtros['fecha_desde'] ?? '')) !== ''
            || trim((string) ($filtros['fecha_hasta'] ?? '')) !== '') {
            return true;
        }
        if (($filtros['operador'] ?? '') === 'vacio') {
            return true;
        }
        if (trim((string) ($filtros['valor'] ?? '')) !== '') {
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
     * @return array<string, mixed>
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
            'empresa_id' => 0,
            'puntoventa_id' => 0,
            'deposito_id' => 0,
            'jornada_id' => 0,
            'fecha_desde' => '',
            'fecha_hasta' => '',
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
            $params['filtro_campo'] = $filtros['campo'] ?? 'descripcion';
            $params['filtro_operador'] = $filtros['operador'] ?? 'contiene';
        } elseif (($filtros['operador'] ?? 'contiene') !== 'contiene') {
            $params['filtro_operador'] = $filtros['operador'];
        }
        if (! empty($filtros['valor'])) {
            $params['filtro_valor'] = $filtros['valor'];
        }
        if ((int) ($filtros['empresa_id'] ?? 0) > 0) {
            $params['empresa_id'] = (int) $filtros['empresa_id'];
        }
        if ((int) ($filtros['puntoventa_id'] ?? 0) > 0) {
            $params['puntoventa_id'] = (int) $filtros['puntoventa_id'];
        }
        if ((int) ($filtros['deposito_id'] ?? 0) > 0) {
            $params['deposito_id'] = (int) $filtros['deposito_id'];
        }
        if ((int) ($filtros['jornada_id'] ?? 0) > 0) {
            $params['jornada_id'] = (int) $filtros['jornada_id'];
        }
        if (! empty($filtros['fecha_desde'])) {
            $params['fecha_desde'] = $filtros['fecha_desde'];
        }
        if (! empty($filtros['fecha_hasta'])) {
            $params['fecha_hasta'] = $filtros['fecha_hasta'];
        }

        return $params;
    }

    /**
     * @return array{0: string, 1: string}
     */
    public static function normalizarRangoFechas(string $fechaDesde, string $fechaHasta): array
    {
        $desde = trim($fechaDesde);
        $hasta = trim($fechaHasta);

        if ($desde !== '' && $hasta === '') {
            $hasta = $desde;
        } elseif ($hasta !== '' && $desde === '') {
            $desde = $hasta;
        }

        return [$desde, $hasta];
    }

    /**
     * @return array<string, string>
     */
    public static function operadoresParaCampo(string $campoKey): array
    {
        return self::OPERADORES_TEXTO;
    }

    private static function normalizarOperador(string $operador): string
    {
        if (in_array($operador, array_keys(self::OPERADORES_TEXTO), true)) {
            return $operador;
        }

        return 'contiene';
    }
}
