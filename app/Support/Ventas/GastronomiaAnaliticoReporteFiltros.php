<?php

namespace App\Support\Ventas;

use App\Support\Listado\FiltrosListadoRequest;
use Carbon\Carbon;
use Illuminate\Http\Request;

/**
 * Filtros del reporte analítico de gastronomía (líneas facturadas en ERP).
 */
final class GastronomiaAnaliticoReporteFiltros
{
    public const MODO_TODOS = 'todos';

    public const MODO_CAMPO = 'campo';

    public const PERIODO_RANGO = 'rango';

    public const PERIODO_MES = 'mes';

    public const TIPO_VENTA_TODOS = '';

    public const TIPO_VENTA_VENTA = 'venta';

    public const TIPO_VENTA_INVITACION = 'invitacion';

    /** @var array<string, array{prop: string, type: string, label: string}> */
    public const CAMPOS = [
        'codigo_articulo' => ['prop' => 'codigo_articulo', 'type' => 'texto', 'label' => 'Código artículo'],
        'descripcion_articulo' => ['prop' => 'descripcion_articulo', 'type' => 'texto', 'label' => 'Descripción artículo'],
        'categoria_articulo' => ['prop' => 'categoria_articulo', 'type' => 'texto', 'label' => 'Categoría artículo'],
        'nombre_mozo' => ['prop' => 'nombre_mozo', 'type' => 'texto', 'label' => 'Mozo'],
        'legajo_mozo' => ['prop' => 'legajo_mozo', 'type' => 'texto', 'label' => 'Legajo/código mozo'],
        'tipo_comprobante' => ['prop' => 'tipo_comprobante', 'type' => 'texto', 'label' => 'Tipo comprobante'],
        'numero_comprobante' => ['prop' => 'numero_comprobante', 'type' => 'texto', 'label' => 'Nº comprobante'],
        'punto_venta' => ['prop' => 'punto_venta', 'type' => 'texto', 'label' => 'Punto de venta'],
        'cliente' => ['prop' => 'cliente', 'type' => 'texto', 'label' => 'Cliente'],
        'tipo_descuento' => ['prop' => 'tipo_descuento', 'type' => 'texto', 'label' => 'Tipo descuento'],
        'tipo_venta' => ['prop' => 'tipo_venta', 'type' => 'texto', 'label' => 'Tipo venta'],
        'sala' => ['prop' => 'sala', 'type' => 'texto', 'label' => 'Sala (empresa)'],
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

    /** @var list<string> */
    public const COLUMNAS_COINCIDENCIA_FLEXIBLE = [
        'codigo_articulo',
        'descripcion_articulo',
        'categoria_articulo',
        'nombre_mozo',
        'cliente',
        'tipo_descuento',
        'sala',
    ];

    /**
     * @return array<string, mixed>
     */
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

        $campo = (string) $request->input('filtro_campo', 'descripcion_articulo');
        if (! isset(self::CAMPOS[$campo])) {
            $campo = 'descripcion_articulo';
        }

        $operador = (string) $request->input('filtro_operador', 'contiene');
        if ($busquedaRapida) {
            $modo = self::MODO_TODOS;
            $operador = 'contiene';
        }
        $operador = self::normalizarOperador($operador);

        $modoPeriodo = (string) $request->input('modo_periodo', self::PERIODO_RANGO);
        if (! in_array($modoPeriodo, [self::PERIODO_RANGO, self::PERIODO_MES], true)) {
            $modoPeriodo = self::PERIODO_RANGO;
        }

        $anio = (int) $request->input('anio', 0);
        $mes = (int) $request->input('mes', 0);
        $fechaDesde = trim((string) $request->input('fecha_desde', ''));
        $fechaHasta = trim((string) $request->input('fecha_hasta', ''));

        if ($modoPeriodo === self::PERIODO_MES) {
            if ($anio < 2000 || $anio > 2100) {
                $anio = (int) date('Y');
            }
            if ($mes < 1 || $mes > 12) {
                $mes = (int) date('n');
            }
            $inicio = Carbon::create($anio, $mes, 1)->startOfMonth();
            $fechaDesde = $inicio->format('Y-m-d');
            $fechaHasta = $inicio->copy()->endOfMonth()->format('Y-m-d');
        } else {
            [$fechaDesde, $fechaHasta] = self::normalizarRangoFechas($fechaDesde, $fechaHasta);
            if ($fechaDesde !== '') {
                try {
                    $d = Carbon::parse($fechaDesde);
                    $anio = (int) $d->format('Y');
                    $mes = (int) $d->format('n');
                } catch (\Throwable) {
                    // keep
                }
            }
        }

        $tipoVenta = trim((string) $request->input('tipo_venta', self::TIPO_VENTA_TODOS));
        if (! in_array($tipoVenta, [self::TIPO_VENTA_TODOS, self::TIPO_VENTA_VENTA, self::TIPO_VENTA_INVITACION], true)) {
            $tipoVenta = self::TIPO_VENTA_TODOS;
        }

        return [
            'modo' => $modo,
            'campo' => $campo,
            'operador' => $operador,
            'valor' => $valor,
            'valor_hasta' => trim((string) $request->input('filtro_valor_hasta', '')),
            'busqueda' => $valor,
            'busqueda_rapida' => $busquedaRapida,
            'empresa_id' => (int) $request->input('empresa_id', 0),
            'modo_periodo' => $modoPeriodo,
            'anio' => $anio,
            'mes' => $mes,
            'fecha_desde' => $fechaDesde,
            'fecha_hasta' => $fechaHasta,
            'tipo_venta' => $tipoVenta,
        ];
    }

    /**
     * @return array{0:string,1:string}
     */
    public static function normalizarRangoFechas(string $desde, string $hasta): array
    {
        $desde = trim($desde);
        $hasta = trim($hasta);

        if ($desde === '' && $hasta === '') {
            return ['', ''];
        }
        if ($desde === '') {
            $desde = $hasta;
        }
        if ($hasta === '') {
            $hasta = $desde;
        }

        try {
            $d = Carbon::parse($desde)->format('Y-m-d');
            $h = Carbon::parse($hasta)->format('Y-m-d');
            if ($d > $h) {
                [$d, $h] = [$h, $d];
            }

            return [$d, $h];
        } catch (\Throwable) {
            return ['', ''];
        }
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    public static function tieneCriteriosAplicados(array $filtros): bool
    {
        if ((int) ($filtros['empresa_id'] ?? 0) <= 0) {
            return false;
        }

        return ($filtros['fecha_desde'] ?? '') !== '' && ($filtros['fecha_hasta'] ?? '') !== '';
    }

    /**
     * Criterios de texto/panel (borde amarillo), además de empresa/fechas.
     *
     * @param  array<string, mixed>  $filtros
     */
    public static function tieneFiltrosTextoAplicados(array $filtros): bool
    {
        if (trim((string) ($filtros['valor'] ?? '')) !== '') {
            return true;
        }
        if (($filtros['operador'] ?? '') === 'vacio') {
            return true;
        }
        if (trim((string) ($filtros['tipo_venta'] ?? '')) !== '') {
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
            'campo' => 'descripcion_articulo',
            'operador' => 'contiene',
            'valor' => '',
            'valor_hasta' => '',
            'busqueda' => '',
            'busqueda_rapida' => false,
            'empresa_id' => 0,
            'modo_periodo' => self::PERIODO_RANGO,
            'anio' => (int) date('Y'),
            'mes' => (int) date('n'),
            'fecha_desde' => '',
            'fecha_hasta' => '',
            'tipo_venta' => self::TIPO_VENTA_TODOS,
        ];
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return array<string, mixed>
     */
    public static function paraQueryString(array $filtros): array
    {
        $out = [];

        if ((int) ($filtros['empresa_id'] ?? 0) > 0) {
            $out['empresa_id'] = (int) $filtros['empresa_id'];
        }

        $modoPeriodo = (string) ($filtros['modo_periodo'] ?? self::PERIODO_RANGO);
        $out['modo_periodo'] = $modoPeriodo;

        if ($modoPeriodo === self::PERIODO_MES) {
            if ((int) ($filtros['anio'] ?? 0) > 0) {
                $out['anio'] = (int) $filtros['anio'];
            }
            if ((int) ($filtros['mes'] ?? 0) > 0) {
                $out['mes'] = (int) $filtros['mes'];
            }
        } else {
            if (($filtros['fecha_desde'] ?? '') !== '') {
                $out['fecha_desde'] = $filtros['fecha_desde'];
            }
            if (($filtros['fecha_hasta'] ?? '') !== '') {
                $out['fecha_hasta'] = $filtros['fecha_hasta'];
            }
        }

        if (trim((string) ($filtros['tipo_venta'] ?? '')) !== '') {
            $out['tipo_venta'] = trim((string) $filtros['tipo_venta']);
        }

        $valor = trim((string) ($filtros['valor'] ?? ''));
        $operador = (string) ($filtros['operador'] ?? 'contiene');
        if ($valor !== '' || $operador === 'vacio') {
            $out['filtro_modo'] = $filtros['modo'] ?? self::MODO_TODOS;
            $out['filtro_campo'] = $filtros['campo'] ?? 'descripcion_articulo';
            $out['filtro_operador'] = $operador;
            $out['filtro_valor'] = $valor;
            if (trim((string) ($filtros['valor_hasta'] ?? '')) !== '') {
                $out['filtro_valor_hasta'] = trim((string) $filtros['valor_hasta']);
            }
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    public static function formatearPeriodoTexto(array $filtros): string
    {
        if (($filtros['modo_periodo'] ?? '') === self::PERIODO_MES) {
            $mes = (int) ($filtros['mes'] ?? 0);
            $anio = (int) ($filtros['anio'] ?? 0);
            $nombres = [
                1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril',
                5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto',
                9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre',
            ];

            return ($nombres[$mes] ?? (string) $mes).' '.$anio;
        }

        $desde = trim((string) ($filtros['fecha_desde'] ?? ''));
        $hasta = trim((string) ($filtros['fecha_hasta'] ?? ''));
        if ($desde === '' && $hasta === '') {
            return '';
        }

        $fmt = static fn (string $ymd) => $ymd !== '' ? Carbon::parse($ymd)->format('d/m/Y') : '—';

        return 'Desde '.$fmt($desde).' hasta '.$fmt($hasta);
    }

    /**
     * @return array<string, string>
     */
    public static function operadoresParaCampo(string $campo): array
    {
        return self::OPERADORES_TEXTO;
    }

    public static function normalizarOperador(string $operador): string
    {
        if (! isset(self::OPERADORES_TEXTO[$operador])) {
            return 'contiene';
        }

        return $operador;
    }

    /**
     * Firma de cache de resultado (consulta costosa).
     *
     * @param  array<string, mixed>  $filtros
     */
    public static function firma(array $filtros): string
    {
        return sha1(json_encode(self::paraQueryString($filtros), JSON_UNESCAPED_UNICODE) ?: '');
    }
}
