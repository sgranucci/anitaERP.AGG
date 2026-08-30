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
 *  - Filtros básicos: empresa (vía punto de venta), rango de fechas y número de reparto.
 *
 * Regla del rango de fechas:
 *  - Por defecto siempre el día de hoy (desde = hasta = hoy), en cualquier orden.
 *  - Si el usuario carga "desde" y deja "hasta" vacío, "hasta" toma el día de hoy.
 *  - Si carga solo "hasta", "desde" toma el día 1 del mes de esa fecha.
 */
class FacturaListadoFiltros
{
    public const MODO_TODOS = 'todos';

    public const MODO_CAMPO = 'campo';

    /** Orden operativo: agrupa por código de reparto (mayor a menor) y luego ID. */
    public const ORDEN_REPARTO = 'reparto';

    /** Orden por ID de venta de mayor a menor (últimas facturas primero). */
    public const ORDEN_ID = 'id';

    /** Empresa por defecto del filtro externo del index. */
    public const EMPRESA_ID_DEFAULT = 1;

    /** @var array<string, array{type: string, label: string}> */
    public const CAMPOS = [
        'id' => ['type' => 'entero', 'label' => 'ID'],
        'numerocomprobante' => ['type' => 'texto', 'label' => 'N&ordm; comprobante'],
        'cliente' => ['type' => 'texto', 'label' => 'Cliente'],
        'tipotransaccion' => ['type' => 'texto', 'label' => 'Comprobante (tipo)'],
        'puntoventa' => ['type' => 'texto', 'label' => 'Punto de venta (código)'],
        'empresa' => ['type' => 'texto', 'label' => 'Empresa'],
        'reparto' => ['type' => 'texto', 'label' => 'Reparto (n&uacute;mero)'],
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

    public static function resolverDesdeRequest(Request $request, ?string $busquedaRuta = null, ?int $empresaDefault = null): array
    {
        [$empresaId, $empresaScope] = self::resolverEmpresaExterna($request, $empresaDefault);

        if (FiltrosListadoRequest::solicitudLimpiaFiltros($request)) {
            return array_merge(self::filtrosVacios(), [
                'empresa_id' => $empresaId ?? 0,
                'empresa_scope' => $empresaScope,
            ]);
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

        $orden = self::normalizarOrden($request->input('filtro_orden'));
        [$fechaDesde, $fechaHasta] = self::resolverRangoFechas($request);

        return [
            'modo' => $modo,
            'campo' => $campo,
            'operador' => $operador,
            'valor' => $valor,
            'busqueda' => $valor,
            'busqueda_rapida' => $busquedaRapida,
            'empresa_id' => $empresaId ?? 0,
            'empresa_scope' => $empresaScope,
            'fecha_desde' => $fechaDesde,
            'fecha_hasta' => $fechaHasta,
            'solo_sin_remito' => $request->boolean('solo_sin_remito'),
            'filtro_reparto' => trim((string) $request->input('filtro_reparto', '')),
            'orden' => $orden,
        ];
    }

    /**
     * Filtro externo del index: empresa 1 por defecto, o todas (`empresa_todas=1`).
     *
     * @return array{0:?int,1:string} [empresa_id, empresa_scope]
     */
    private static function resolverEmpresaExterna(Request $request, ?int $empresaDefault): array
    {
        if ($request->boolean('empresa_todas') || $request->input('empresa_scope') === 'todas') {
            return [null, 'todas'];
        }
        if ($request->filled('empresa_id')) {
            return [(int) $request->input('empresa_id'), 'una'];
        }
        $default = $empresaDefault ?? self::EMPRESA_ID_DEFAULT;
        if ($default > 0) {
            return [$default, 'una'];
        }

        return [null, 'todas'];
    }

    /**
     * Resuelve el rango de fechas respetando la regla de negocio.
     *
     * @return array{0: string, 1: string} [fecha_desde, fecha_hasta]
     */
    private static function resolverRangoFechas(Request $request): array
    {
        $default = self::rangoFechasPorDefecto();
        $tieneParametros = $request->has('fecha_desde') || $request->has('fecha_hasta');

        $desde = trim((string) $request->input('fecha_desde', ''));
        $hasta = trim((string) $request->input('fecha_hasta', ''));

        // Primera carga (sin parámetros de fecha): default según el orden.
        if (! $tieneParametros) {
            return [$default['fecha_desde'], $default['fecha_hasta']];
        }

        if ($desde === '' && $hasta === '') {
            return [$default['fecha_desde'], $default['fecha_hasta']];
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

        if (! empty($filtros['solo_sin_remito'])) {
            return true;
        }

        if (trim((string) ($filtros['filtro_reparto'] ?? '')) !== '') {
            return true;
        }

        if (! self::esRangoFechasPorDefecto($filtros)) {
            return true;
        }

        return false;
    }

    /**
     * @return array{fecha_desde: string, fecha_hasta: string}
     */
    public static function rangoFechasPorDefecto(?string $orden = null): array
    {
        $hoy = date('Y-m-d');

        return [
            'fecha_desde' => $hoy,
            'fecha_hasta' => $hoy,
        ];
    }

    public static function esRangoFechasPorDefecto(array $filtros): bool
    {
        $default = self::rangoFechasPorDefecto();

        return ($filtros['fecha_desde'] ?? '') === $default['fecha_desde']
            && ($filtros['fecha_hasta'] ?? '') === $default['fecha_hasta'];
    }

    /**
     * Cambia el orden conservando el rango de fechas (custom o default de hoy).
     *
     * @param  array<string, mixed>  $filtros
     * @return array<string, mixed>
     */
    public static function conOrden(array $filtros, string $orden): array
    {
        $siguiente = $filtros;
        $siguiente['orden'] = self::normalizarOrden($orden);

        return $siguiente;
    }

    /**
     * Query del enlace de impresión por reparto (mismos filtros del index).
     *
     * @param  array<string, mixed>  $filtros
     * @return array<string, mixed>
     */
    public static function paraImpresionReparto(array $filtros, int $transporteId, bool $soloCopias = false): array
    {
        $params = ['transporteId' => $transporteId] + self::paraQueryString($filtros);
        if ($soloCopias) {
            $params['solo_copias'] = 1;
        }

        return $params;
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
            'empresa_id' => self::EMPRESA_ID_DEFAULT,
            'empresa_scope' => 'una',
            'fecha_desde' => $rango['fecha_desde'],
            'fecha_hasta' => $rango['fecha_hasta'],
            'solo_sin_remito' => false,
            'filtro_reparto' => '',
            'orden' => self::ORDEN_REPARTO,
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
        $params = array_merge($params, self::paraQueryStringEmpresa($filtros));
        if (! empty($filtros['fecha_desde'])) {
            $params['fecha_desde'] = $filtros['fecha_desde'];
        }
        if (! empty($filtros['fecha_hasta'])) {
            $params['fecha_hasta'] = $filtros['fecha_hasta'];
        }
        if (! empty($filtros['solo_sin_remito'])) {
            $params['solo_sin_remito'] = 1;
        }
        $filtroReparto = trim((string) ($filtros['filtro_reparto'] ?? ''));
        if ($filtroReparto !== '') {
            $params['filtro_reparto'] = $filtroReparto;
        }
        if (self::esOrdenId($filtros)) {
            $params['filtro_orden'] = self::ORDEN_ID;
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
        if (($filtros['empresa_scope'] ?? 'una') === 'todas') {
            return ['empresa_todas' => 1];
        }
        if (! empty($filtros['empresa_id'])) {
            return ['empresa_id' => (int) $filtros['empresa_id']];
        }

        return [];
    }

    public static function formatearPeriodoTexto(array $filtros): string
    {
        $desde = $filtros['fecha_desde'] ?? '';
        $hasta = $filtros['fecha_hasta'] ?? '';
        if ($desde === '' || $hasta === '') {
            return '';
        }

        $desdeTxt = date('d/m/Y', strtotime($desde));
        $hastaTxt = date('d/m/Y', strtotime($hasta));
        if ($desde === $hasta) {
            return $desdeTxt;
        }

        return $desdeTxt.' — '.$hastaTxt;
    }

    public static function formatearRepartoTexto(array $filtros): string
    {
        $reparto = trim((string) ($filtros['filtro_reparto'] ?? ''));
        if ($reparto === '') {
            return '';
        }

        [$desde, $hasta] = KiloPedidoListadoFiltros::normalizarRangoRepartos($reparto, '');

        return KiloPedidoListadoFiltros::formatearRepartoTexto([
            'reparto_desde' => $desde,
            'reparto_hasta' => $hasta,
        ]);
    }

    public static function normalizarOrden(mixed $orden): string
    {
        return trim((string) $orden) === self::ORDEN_ID
            ? self::ORDEN_ID
            : self::ORDEN_REPARTO;
    }

    public static function esOrdenId(array $filtros): bool
    {
        return self::normalizarOrden($filtros['orden'] ?? null) === self::ORDEN_ID;
    }

    public static function esOrdenReparto(array $filtros): bool
    {
        return ! self::esOrdenId($filtros);
    }

    public static function formatearOrdenTexto(array $filtros): string
    {
        return self::esOrdenId($filtros)
            ? 'ID (mayor a menor)'
            : 'Reparto (código mayor a menor)';
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

        self::aplicarFiltroReparto($query, (string) ($filtros['filtro_reparto'] ?? ''));

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

            $q->orWhereHas('transportes', function ($t) use ($like) {
                $t->where('codigo', 'like', $like)
                    ->orWhere('nombre', 'like', $like);
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
            case 'reparto':
                $query->whereHas('transportes', function ($t) use ($operador, $valor) {
                    self::aplicarTexto($t, 'codigo', $operador, $valor);
                });
                break;
        }
    }

    /**
     * @param  Builder<\App\Models\Ventas\Venta>  $query
     */
    private static function aplicarFiltroReparto(Builder $query, string $filtroReparto): void
    {
        $filtroReparto = trim($filtroReparto);
        if ($filtroReparto === '') {
            return;
        }

        [$desde, $hasta] = KiloPedidoListadoFiltros::normalizarRangoRepartos($filtroReparto, '');
        $query->whereHas('transportes', static function ($q) use ($desde, $hasta): void {
            KiloPedidoListadoFiltros::aplicarFiltroRepartoEnQuery($q, $desde, $hasta);
        });
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
