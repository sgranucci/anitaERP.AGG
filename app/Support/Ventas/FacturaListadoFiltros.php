<?php

namespace App\Support\Ventas;

use App\Support\Listado\CoincidenciaFlexibleTexto;
use App\Support\Listado\FiltrosListadoRequest;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * Filtros del listado de comprobantes de venta (index / exportaciones).
 *
 * Combina:
 *  - Búsqueda inteligente de texto (modo todos / campo determinado, operadores, coincidencia flexible).
 *  - Filtros básicos: empresa (vía punto de venta) y rango de fechas.
 *
 * Regla del rango de fechas:
 *  - Por defecto se presenta el mes actual: desde el día 1 del mes hasta hoy.
 *  - Si el usuario carga "desde" y deja "hasta" vacío, "hasta" toma el día de hoy.
 *  - Si carga solo "hasta", "desde" toma el día 1 del mes de esa fecha.
 */
class FacturaListadoFiltros
{
    public const MODO_TODOS = 'todos';

    public const MODO_CAMPO = 'campo';

    /** @var array<string, array{type: string, label: string}> */
    public const CAMPOS = [
        'id' => ['type' => 'entero', 'label' => 'ID'],
        'numerocomprobante' => ['type' => 'texto', 'label' => 'N&ordm; comprobante'],
        'cliente' => ['type' => 'texto', 'label' => 'Cliente'],
        'tipotransaccion' => ['type' => 'texto', 'label' => 'Comprobante (tipo)'],
        'puntoventa' => ['type' => 'texto', 'label' => 'Punto de venta (código)'],
        'empresa' => ['type' => 'texto', 'label' => 'Empresa'],
    ];

    /** @var array<string, string> */
    public const OPERADORES_TEXTO = [
        'contiene' => 'Contiene (en cualquier parte)',
        'empieza' => 'Empieza con',
        'termina' => 'Termina con',
        'igual' => 'Igual a',
        'distinto' => 'Distinto de',
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

        $campo = (string) $request->input('filtro_campo', 'cliente');
        if (! isset(self::CAMPOS[$campo])) {
            $campo = 'cliente';
        }

        $operador = (string) $request->input('filtro_operador', 'contiene');

        if ($busquedaRapida) {
            $modo = self::MODO_TODOS;
            $operador = 'contiene';
        }

        $operador = self::normalizarOperador($operador, $modo === self::MODO_CAMPO ? $campo : 'cliente');

        [$fechaDesde, $fechaHasta] = self::resolverRangoFechas($request);

        return [
            'modo' => $modo,
            'campo' => $campo,
            'operador' => $operador,
            'valor' => $valor,
            'busqueda' => $valor,
            'busqueda_rapida' => $busquedaRapida,
            'empresa_id' => (int) $request->input('empresa_id', 0),
            'fecha_desde' => $fechaDesde,
            'fecha_hasta' => $fechaHasta,
            'solo_sin_remito' => $request->boolean('solo_sin_remito'),
        ];
    }

    /**
     * Resuelve el rango de fechas respetando la regla de negocio.
     *
     * @return array{0: string, 1: string} [fecha_desde, fecha_hasta]
     */
    private static function resolverRangoFechas(Request $request): array
    {
        $tieneParametros = $request->has('fecha_desde') || $request->has('fecha_hasta');

        $desde = trim((string) $request->input('fecha_desde', ''));
        $hasta = trim((string) $request->input('fecha_hasta', ''));

        // Primera carga (sin parámetros de fecha en la request): mes actual.
        if (! $tieneParametros) {
            return [date('Y-m-01'), date('Y-m-d')];
        }

        if ($desde === '' && $hasta === '') {
            return [date('Y-m-01'), date('Y-m-d')];
        }

        if ($desde !== '' && $hasta === '') {
            // Arrancó por "desde": "hasta" toma el día de hoy.
            return [$desde, date('Y-m-d')];
        }

        if ($desde === '' && $hasta !== '') {
            // Solo "hasta": "desde" toma el día 1 del mes de esa fecha.
            $ts = strtotime($hasta) ?: time();

            return [date('Y-m-01', $ts), $hasta];
        }

        return [$desde, $hasta];
    }

    public static function tieneCriteriosAplicados(array $filtros): bool
    {
        if (trim((string) ($filtros['valor'] ?? '')) !== '') {
            return true;
        }

        if (($filtros['modo'] ?? self::MODO_TODOS) === self::MODO_CAMPO) {
            return true;
        }

        if (($filtros['operador'] ?? 'contiene') !== 'contiene') {
            return true;
        }

        if ((int) ($filtros['empresa_id'] ?? 0) > 0) {
            return true;
        }

        if (! empty($filtros['solo_sin_remito'])) {
            return true;
        }

        // Rango de fechas distinto del mes actual por defecto.
        $default = self::rangoFechasPorDefecto();
        if (($filtros['fecha_desde'] ?? '') !== $default['fecha_desde']
            || ($filtros['fecha_hasta'] ?? '') !== $default['fecha_hasta']) {
            return true;
        }

        return false;
    }

    /**
     * @return array{fecha_desde: string, fecha_hasta: string}
     */
    public static function rangoFechasPorDefecto(): array
    {
        return [
            'fecha_desde' => date('Y-m-01'),
            'fecha_hasta' => date('Y-m-d'),
        ];
    }

    public static function filtrosVacios(): array
    {
        $rango = self::rangoFechasPorDefecto();

        return [
            'modo' => self::MODO_TODOS,
            'campo' => 'cliente',
            'operador' => 'contiene',
            'valor' => '',
            'busqueda' => '',
            'busqueda_rapida' => false,
            'empresa_id' => 0,
            'fecha_desde' => $rango['fecha_desde'],
            'fecha_hasta' => $rango['fecha_hasta'],
            'solo_sin_remito' => false,
        ];
    }

    /**
     * @return array<string, string|int>
     */
    public static function paraQueryString(array $filtros): array
    {
        $params = [];

        if (($filtros['modo'] ?? self::MODO_TODOS) !== self::MODO_TODOS) {
            $params['filtro_modo'] = $filtros['modo'];
        }
        if (($filtros['modo'] ?? '') === self::MODO_CAMPO) {
            $params['filtro_campo'] = $filtros['campo'] ?? 'cliente';
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
        if (! empty($filtros['fecha_desde'])) {
            $params['fecha_desde'] = $filtros['fecha_desde'];
        }
        if (! empty($filtros['fecha_hasta'])) {
            $params['fecha_hasta'] = $filtros['fecha_hasta'];
        }
        if (! empty($filtros['solo_sin_remito'])) {
            $params['solo_sin_remito'] = 1;
        }

        return $params;
    }

    public static function formatearPeriodoTexto(array $filtros): string
    {
        $desde = $filtros['fecha_desde'] ?? '';
        $hasta = $filtros['fecha_hasta'] ?? '';
        if ($desde === '' || $hasta === '') {
            return '';
        }

        return date('d/m/Y', strtotime($desde)).' — '.date('d/m/Y', strtotime($hasta));
    }

    /**
     * Aplica todos los filtros a un query de comprobantes de venta.
     *
     * @param  Builder<\App\Models\Ventas\Venta>  $query
     */
    public static function aplicar(Builder $query, array $filtros): void
    {
        self::aplicarEmpresa($query, (int) ($filtros['empresa_id'] ?? 0));
        self::aplicarRangoFechas($query, (string) ($filtros['fecha_desde'] ?? ''), (string) ($filtros['fecha_hasta'] ?? ''));

        if (! empty($filtros['solo_sin_remito'])) {
            $query->whereNull('venta.remito_id');
        }

        $valor = trim((string) ($filtros['valor'] ?? ''));
        if ($valor === '') {
            return;
        }

        $modo = $filtros['modo'] ?? self::MODO_TODOS;
        $operador = $filtros['operador'] ?? 'contiene';

        if ($modo === self::MODO_CAMPO) {
            self::aplicarEnCampo($query, $filtros['campo'] ?? 'cliente', $operador, $valor);

            return;
        }

        self::aplicarBusquedaGlobal($query, $operador, $valor);
    }

    /**
     * @param  Builder<\App\Models\Ventas\Venta>  $query
     */
    private static function aplicarEmpresa(Builder $query, int $empresaId): void
    {
        if ($empresaId <= 0) {
            return;
        }

        $query->whereHas('puntoventas', static function ($q) use ($empresaId): void {
            $q->where('empresa_id', $empresaId);
        });
    }

    /**
     * @param  Builder<\App\Models\Ventas\Venta>  $query
     */
    private static function aplicarRangoFechas(Builder $query, string $desde, string $hasta): void
    {
        if ($desde !== '') {
            $query->whereDate('venta.fecha', '>=', $desde);
        }
        if ($hasta !== '') {
            $query->whereDate('venta.fecha', '<=', $hasta);
        }
    }

    /**
     * @param  Builder<\App\Models\Ventas\Venta>  $query
     */
    private static function aplicarBusquedaGlobal(Builder $query, string $operador, string $valor): void
    {
        $like = self::patronLike($operador, $valor);
        $id = filter_var($valor, FILTER_VALIDATE_INT);

        $query->where(function ($q) use ($valor, $like, $id, $operador) {
            if ($id !== false) {
                $q->orWhere('venta.id', (int) $id);
            }

            $q->orWhere('venta.numerocomprobante', 'like', $like);

            $q->orWhereHas('clientes', function ($c) use ($operador, $valor, $like) {
                $c->where('nombre', 'like', $like);
                if ($operador === 'contiene') {
                    CoincidenciaFlexibleTexto::aplicar($c, 'nombre', $valor, true, CoincidenciaFlexibleTexto::LONGITUD_MINIMA_ARTICULO);
                }
            });

            $q->orWhereHas('tipotransacciones', function ($t) use ($like) {
                $t->where('nombre', 'like', $like);
            });

            $q->orWhereHas('puntoventas', function ($p) use ($operador, $valor, $like) {
                $p->where('codigo', 'like', $like)
                    ->orWhereHas('empresas', function ($e) use ($operador, $valor, $like) {
                        $e->where('nombre', 'like', $like);
                        if ($operador === 'contiene') {
                            CoincidenciaFlexibleTexto::aplicar($e, 'nombre', $valor, true, CoincidenciaFlexibleTexto::LONGITUD_MINIMA_ARTICULO);
                        }
                    });
            });
        });
    }

    /**
     * @param  Builder<\App\Models\Ventas\Venta>  $query
     */
    private static function aplicarEnCampo(Builder $query, string $campoKey, string $operador, string $valor): void
    {
        $def = self::CAMPOS[$campoKey] ?? self::CAMPOS['cliente'];

        if ($def['type'] === 'entero') {
            self::aplicarEntero($query, 'venta.id', $operador, $valor);

            return;
        }

        switch ($campoKey) {
            case 'numerocomprobante':
                self::aplicarTexto($query, 'venta.numerocomprobante', $operador, $valor);
                break;
            case 'cliente':
                $query->whereHas('clientes', function ($c) use ($operador, $valor) {
                    self::aplicarTexto($c, 'nombre', $operador, $valor, true);
                });
                break;
            case 'tipotransaccion':
                $query->whereHas('tipotransacciones', function ($t) use ($operador, $valor) {
                    self::aplicarTexto($t, 'nombre', $operador, $valor);
                });
                break;
            case 'puntoventa':
                $query->whereHas('puntoventas', function ($p) use ($operador, $valor) {
                    self::aplicarTexto($p, 'codigo', $operador, $valor);
                });
                break;
            case 'empresa':
                $query->whereHas('puntoventas', function ($p) use ($operador, $valor) {
                    $p->whereHas('empresas', function ($e) use ($operador, $valor) {
                        self::aplicarTexto($e, 'nombre', $operador, $valor, true);
                    });
                });
                break;
        }
    }

    /**
     * @param  Builder<*>  $query
     */
    private static function aplicarTexto(Builder $query, string $column, string $operador, string $valor, bool $flexible = false): void
    {
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
                $query->where(function ($q) use ($column, $valor, $flexible) {
                    $q->where($column, 'like', '%'.self::escapeLike($valor).'%');
                    if ($flexible) {
                        CoincidenciaFlexibleTexto::aplicar($q, $column, $valor, false, CoincidenciaFlexibleTexto::LONGITUD_MINIMA_ARTICULO);
                    }
                });
                break;
        }
    }

    /**
     * @param  Builder<*>  $query
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
