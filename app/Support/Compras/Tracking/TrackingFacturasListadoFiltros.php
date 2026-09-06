<?php

namespace App\Support\Compras\Tracking;

use App\Support\Compras\ComprobanteProveedorEstados;
use App\Support\Listado\CoincidenciaFlexibleTexto;
use App\Support\Listado\FiltrosListadoRequest;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * Filtros del tracking de facturas (index y exportaciones).
 *
 * Sobre el buscador por campo habitual se agregan dos ejes propios del módulo:
 *
 * - el **segmento**, que reproduce las tres búsquedas del informe viejo
 *   (sin contabilizar, cargados entre fechas, sin pagar);
 * - el **eje de fecha**, porque «fecha del comprobante» y «fecha de carga» son
 *   preguntas distintas y el informe viejo respondía la segunda.
 */
class TrackingFacturasListadoFiltros
{
    public const MODO_TODOS = 'todos';

    public const MODO_CAMPO = 'campo';

    // --- Segmentos (búsquedas guardadas) ---

    public const SEGMENTO_TODOS = 'todos';

    public const SEGMENTO_SIN_CONTABILIZAR = 'sin_contabilizar';

    public const SEGMENTO_CARGADOS_ENTRE_FECHAS = 'cargados_entre_fechas';

    public const SEGMENTO_SIN_PAGAR = 'sin_pagar';

    public const SEGMENTO_SIN_PDF = 'sin_pdf';

    /** Deuda con más de 90 días desde la fecha del comprobante. */
    public const SEGMENTO_DEUDA_ANTIGUA = 'deuda_antigua';

    // --- Ejes de fecha ---

    /** Fecha que trae el comprobante impreso. */
    public const EJE_FECHA_COMPROBANTE = 'comprobante';

    /** Fecha en que el comprobante entró al circuito (índice de tracking). */
    public const EJE_FECHA_CARGA = 'carga';

    public const EJE_FECHA_IVA = 'iva';

    /** Fecha del asiento: cuándo se contabilizó de verdad. */
    public const EJE_FECHA_CONTABILIZACION = 'contabilizacion';

    /** @var array<string, array{column: string, type: string, label: string}> */
    public const CAMPOS = [
        'id' => ['column' => 'comprobante_proveedor.id', 'type' => 'entero', 'label' => 'ID'],
        'nombreempresa' => ['column' => 'empresa.nombre', 'type' => 'texto', 'label' => 'Empresa'],
        'nombreproveedor' => ['column' => 'proveedor.nombre', 'type' => 'texto', 'label' => 'Proveedor'],
        'cuitproveedor' => ['column' => 'proveedor.nroinscripcion', 'type' => 'texto', 'label' => 'CUIT proveedor'],
        'nombretipotransaccion' => ['column' => 'tipotransaccion_compra.nombre', 'type' => 'texto', 'label' => 'Tipo de comprobante'],
        'abreviaturatipotransaccion' => ['column' => 'tipotransaccion_compra.abreviatura', 'type' => 'texto', 'label' => 'Abreviatura tipo'],
        'letra' => ['column' => 'comprobante_proveedor.letra', 'type' => 'texto', 'label' => 'Letra'],
        'sucursal' => ['column' => 'comprobante_proveedor.sucursal', 'type' => 'entero', 'label' => 'Sucursal'],
        'numerocomprobante' => ['column' => 'comprobante_proveedor.numerocomprobante', 'type' => 'entero', 'label' => 'Número comprobante'],
        'fechacomprobante' => ['column' => 'comprobante_proveedor.fechacomprobante', 'type' => 'fecha', 'label' => 'Fecha comprobante'],
        'fechacarga' => ['column' => 'comprobante_tracking_indice.fechacarga_efectiva', 'type' => 'fecha', 'label' => 'Fecha de carga'],
        'fechacontabilizacion' => ['column' => 'asiento.fecha', 'type' => 'fecha', 'label' => 'Fecha de contabilización'],
        'numeroordencompra' => ['column' => 'ordencompra.numeroordencompra', 'type' => 'texto', 'label' => 'Número de OC'],
        'numeroasiento' => ['column' => 'asiento.numeroasiento', 'type' => 'entero', 'label' => 'Número de asiento'],
        'total' => ['column' => 'comprobante_proveedor.total', 'type' => 'texto', 'label' => 'Total'],
        'estado' => ['column' => 'comprobante_proveedor.estado', 'type' => 'texto', 'label' => 'Estado'],
        'pago_estado' => ['column' => 'comprobante_tracking_indice.pago_estado', 'type' => 'texto', 'label' => 'Estado de pago'],
    ];

    /** @var list<string> */
    private const COLUMNAS_COINCIDENCIA_FLEXIBLE = [
        'empresa.nombre',
        'proveedor.nombre',
        'tipotransaccion_compra.nombre',
        'tipotransaccion_compra.abreviatura',
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

    /**
     * @return array<string, array{label: string, ayuda: string, icono: string}>
     */
    public static function segmentos(): array
    {
        return [
            self::SEGMENTO_TODOS => [
                'label' => 'Todos',
                'ayuda' => 'Todos los comprobantes del período',
                'icono' => 'fa-list',
            ],
            self::SEGMENTO_SIN_CONTABILIZAR => [
                'label' => 'Sin contabilizar',
                'ayuda' => 'Cargados pero sin asiento contable',
                'icono' => 'fa-hourglass-half',
            ],
            self::SEGMENTO_CARGADOS_ENTRE_FECHAS => [
                'label' => 'Cargados entre fechas',
                'ayuda' => 'Por fecha real de carga, no por fecha del comprobante',
                'icono' => 'fa-calendar-check',
            ],
            self::SEGMENTO_SIN_PAGAR => [
                'label' => 'Sin pagar',
                'ayuda' => 'Con saldo pendiente en cuenta corriente',
                'icono' => 'fa-exclamation-triangle',
            ],
            self::SEGMENTO_SIN_PDF => [
                'label' => 'Sin PDF',
                'ayuda' => 'No se encontró el comprobante escaneado',
                'icono' => 'fa-file-excel',
            ],
            self::SEGMENTO_DEUDA_ANTIGUA => [
                'label' => 'Deuda +90 días',
                'ayuda' => 'Vencida hace más de 90 días (por vencimiento, o por fecha del comprobante si no hay vencimiento)',
                'icono' => 'fa-clock-o',
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function ejesFecha(): array
    {
        return [
            self::EJE_FECHA_COMPROBANTE => 'Fecha del comprobante',
            self::EJE_FECHA_CARGA => 'Fecha de carga',
            self::EJE_FECHA_CONTABILIZACION => 'Fecha de contabilización',
            self::EJE_FECHA_IVA => 'Fecha de IVA',
        ];
    }

    public static function columnaEjeFecha(string $eje): string
    {
        return match ($eje) {
            self::EJE_FECHA_CARGA => 'comprobante_tracking_indice.fechacarga_efectiva',
            self::EJE_FECHA_CONTABILIZACION => 'asiento.fecha',
            self::EJE_FECHA_IVA => 'comprobante_proveedor.fechaiva',
            default => 'comprobante_proveedor.fechacomprobante',
        };
    }

    /**
     * @return array<string, mixed>
     */
    public static function resolverDesdeRequest(
        Request $request,
        ?string $busquedaRuta = null,
        ?int $empresaDefault = null
    ): array {
        [$empresaId, $empresaScope] = self::resolverEmpresaExterna($request, $empresaDefault);
        $externos = self::resolverFiltrosExternos($request);
        $externos['empresa_id'] = $empresaId;
        $externos['empresa_scope'] = $empresaScope;

        if (FiltrosListadoRequest::solicitudLimpiaFiltros($request)) {
            return array_merge(self::filtrosVacios(), $externos);
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

        return array_merge([
            'modo' => $modo,
            'campo' => $campo,
            'operador' => $operador,
            'valor' => $valor,
            'valor_hasta' => trim((string) $request->input('filtro_valor_hasta', '')),
            'busqueda' => $valor,
            'busqueda_rapida' => $busquedaRapida,
        ], $externos);
    }

    /**
     * Filtro externo de empresa: default primera asignada, o todas (`empresa_todas=1`).
     *
     * @return array{0:?int,1:string}
     */
    private static function resolverEmpresaExterna(Request $request, ?int $empresaDefault): array
    {
        if ($request->boolean('empresa_todas') || $request->input('empresa_scope') === 'todas') {
            return [null, 'todas'];
        }
        if ($request->filled('empresa_id')) {
            return [(int) $request->input('empresa_id'), 'una'];
        }
        if ($empresaDefault !== null && $empresaDefault > 0) {
            return [$empresaDefault, 'una'];
        }

        return [null, 'todas'];
    }

    /**
     * Segmento, rango de fechas, eje, familia y proveedor: viven fuera del
     * buscador y sobreviven al botón de limpiar filtros (junto con empresa).
     *
     * @return array<string, mixed>
     */
    public static function resolverFiltrosExternos(Request $request): array
    {
        $segmento = (string) $request->input('segmento', self::SEGMENTO_TODOS);
        if (! isset(self::segmentos()[$segmento])) {
            $segmento = self::SEGMENTO_TODOS;
        }

        $eje = (string) $request->input('eje_fecha', '');
        if (! isset(self::ejesFecha()[$eje])) {
            // El segmento «cargados entre fechas» sólo tiene sentido sobre la
            // fecha de carga: se fuerza el eje para que el chip haga lo que dice.
            $eje = $segmento === self::SEGMENTO_CARGADOS_ENTRE_FECHAS
                ? self::EJE_FECHA_CARGA
                : self::EJE_FECHA_COMPROBANTE;
        }

        $familia = strtoupper(trim((string) $request->input('familia', '')));
        if (! TrackingComprobanteFamilia::esFamiliaValida($familia)) {
            $familia = '';
        }

        $tramo = (string) $request->input('tramo_antiguedad', '');
        if (! TrackingAntiguedadDeuda::esTramoValido($tramo)) {
            $tramo = '';
        }

        return [
            'segmento' => $segmento,
            'eje_fecha' => $eje,
            'fecha_desde' => self::parsearFecha((string) $request->input('fecha_desde', '')) ?? '',
            'fecha_hasta' => self::parsearFecha((string) $request->input('fecha_hasta', '')) ?? '',
            'familia' => $familia,
            'proveedor_id' => max(0, (int) $request->input('proveedor_id', 0)),
            'tramo_antiguedad' => $tramo,
        ];
    }

    /**
     * @return array<string, mixed>
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
            'busqueda_rapida' => false,
            'segmento' => self::SEGMENTO_TODOS,
            'eje_fecha' => self::EJE_FECHA_COMPROBANTE,
            'fecha_desde' => '',
            'fecha_hasta' => '',
            'familia' => '',
            'empresa_id' => null,
            'empresa_scope' => 'una',
            'proveedor_id' => 0,
            'tramo_antiguedad' => '',
        ];
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    public static function tieneCriteriosAplicados(array $filtros): bool
    {
        if (($filtros['operador'] ?? '') === 'vacio') {
            return true;
        }

        foreach (['valor', 'valor_hasta'] as $clave) {
            if (trim((string) ($filtros[$clave] ?? '')) !== '') {
                return true;
            }
        }

        if (($filtros['modo'] ?? self::MODO_TODOS) === self::MODO_CAMPO) {
            return true;
        }

        return ($filtros['operador'] ?? 'contiene') !== 'contiene';
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    public static function tieneFiltrosExternosAplicados(array $filtros): bool
    {
        if (($filtros['segmento'] ?? self::SEGMENTO_TODOS) !== self::SEGMENTO_TODOS) {
            return true;
        }

        foreach (['fecha_desde', 'fecha_hasta', 'familia', 'tramo_antiguedad'] as $clave) {
            if (trim((string) ($filtros[$clave] ?? '')) !== '') {
                return true;
            }
        }

        if (($filtros['empresa_scope'] ?? 'una') === 'todas') {
            return true;
        }

        return (int) ($filtros['proveedor_id'] ?? 0) > 0;
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return array<string, string|int|bool>
     */
    public static function paraQueryString(array $filtros): array
    {
        $params = self::paraQueryStringExternos($filtros);

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
     * Solo el filtro externo de empresa (para Limpiar texto sin perder empresa).
     *
     * @param  array<string, mixed>  $filtros
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

    /**
     * @param  array<string, mixed>  $filtros
     * @return array<string, string|int>
     */
    public static function paraQueryStringExternos(array $filtros): array
    {
        $params = self::paraQueryStringEmpresa($filtros);

        if (($filtros['segmento'] ?? self::SEGMENTO_TODOS) !== self::SEGMENTO_TODOS) {
            $params['segmento'] = (string) $filtros['segmento'];
        }
        if (($filtros['eje_fecha'] ?? self::EJE_FECHA_COMPROBANTE) !== self::EJE_FECHA_COMPROBANTE) {
            $params['eje_fecha'] = (string) $filtros['eje_fecha'];
        }
        foreach (['fecha_desde', 'fecha_hasta', 'familia', 'tramo_antiguedad'] as $clave) {
            if (trim((string) ($filtros[$clave] ?? '')) !== '') {
                $params[$clave] = (string) $filtros[$clave];
            }
        }
        if ((int) ($filtros['proveedor_id'] ?? 0) > 0) {
            $params['proveedor_id'] = (int) $filtros['proveedor_id'];
        }

        return $params;
    }

    /**
     * @param  Builder<\App\Models\Compras\Comprobante_Proveedor>  $query
     * @param  array<string, mixed>  $filtros
     */
    public static function aplicar(Builder $query, array $filtros): void
    {
        self::aplicarFiltrosExternos($query, $filtros);

        $valor = trim((string) ($filtros['valor'] ?? ''));
        if ($valor === '' && ($filtros['operador'] ?? '') !== 'vacio' && trim((string) ($filtros['valor_hasta'] ?? '')) === '') {
            return;
        }

        if (($filtros['modo'] ?? self::MODO_TODOS) === self::MODO_CAMPO) {
            self::aplicarEnCampo(
                $query,
                (string) ($filtros['campo'] ?? 'nombreproveedor'),
                (string) ($filtros['operador'] ?? 'contiene'),
                $valor,
                (string) ($filtros['valor_hasta'] ?? '')
            );

            return;
        }

        self::aplicarBusquedaGlobal($query, (string) ($filtros['operador'] ?? 'contiene'), $valor);
    }

    /**
     * @param  Builder<\App\Models\Compras\Comprobante_Proveedor>  $query
     * @param  array<string, mixed>  $filtros
     */
    public static function aplicarFiltrosExternos(Builder $query, array $filtros): void
    {
        self::aplicarSegmento($query, (string) ($filtros['segmento'] ?? self::SEGMENTO_TODOS));
        self::aplicarRangoFechas($query, $filtros);
        self::aplicarFamilia($query, (string) ($filtros['familia'] ?? ''));
        self::aplicarTramoAntiguedad($query, (string) ($filtros['tramo_antiguedad'] ?? ''));

        if ((int) ($filtros['empresa_id'] ?? 0) > 0 && ($filtros['empresa_scope'] ?? 'una') !== 'todas') {
            $query->where('comprobante_proveedor.empresa_id', (int) $filtros['empresa_id']);
        }
        if ((int) ($filtros['proveedor_id'] ?? 0) > 0) {
            $query->where('comprobante_proveedor.proveedor_id', (int) $filtros['proveedor_id']);
        }
    }

    /**
     * Filtro del dashboard de aging: un tramo a la vez.
     *
     * @param  Builder<\App\Models\Compras\Comprobante_Proveedor>  $query
     */
    public static function aplicarTramoAntiguedad(Builder $query, string $tramo): void
    {
        if (! TrackingAntiguedadDeuda::esTramoValido($tramo)) {
            return;
        }

        $query->whereIn('comprobante_tracking_indice.pago_estado', TrackingPagoEstado::conDeuda())
            ->where('comprobante_proveedor.estado', '!=', ComprobanteProveedorEstados::ANULADO);

        [$sql, $bindings] = TrackingAntiguedadDeuda::sqlPredicadoTramo($tramo);
        $query->whereRaw($sql, $bindings);
    }

    /**
     * @param  Builder<\App\Models\Compras\Comprobante_Proveedor>  $query
     */
    public static function aplicarSegmento(Builder $query, string $segmento): void
    {
        switch ($segmento) {
            case self::SEGMENTO_SIN_CONTABILIZAR:
                // Contabilizado es estado y asiento a la vez: un comprobante con
                // estado CONTABILIZADO pero sin asiento sigue estando pendiente.
                $query->where(function ($q) {
                    $q->where('comprobante_proveedor.estado', '!=', ComprobanteProveedorEstados::CONTABILIZADO)
                        ->orWhereNull('comprobante_proveedor.asiento_id');
                })->where('comprobante_proveedor.estado', '!=', ComprobanteProveedorEstados::ANULADO);
                break;

            case self::SEGMENTO_SIN_PAGAR:
                $query->whereIn('comprobante_tracking_indice.pago_estado', TrackingPagoEstado::conDeuda())
                    ->where('comprobante_proveedor.estado', '!=', ComprobanteProveedorEstados::ANULADO);
                break;

            case self::SEGMENTO_SIN_PDF:
                // Faltante confirmado: el comprobante se resolvió y no apareció
                // el PDF en ninguna fuente. Los todavía no indexados quedan
                // afuera porque no son un faltante, son un pendiente, y
                // contarlos infla el chip con miles de casos sin averiguar.
                $query->whereNotNull('comprobante_tracking_indice.sincronizado_at')
                    ->where('comprobante_tracking_indice.pdf_disponible', false);
                break;

            case self::SEGMENTO_DEUDA_ANTIGUA:
                // +90 días de atraso desde el vencimiento (o fecha del
                // comprobante si no hay vencimiento usable).
                $query->whereIn('comprobante_tracking_indice.pago_estado', TrackingPagoEstado::conDeuda())
                    ->where('comprobante_proveedor.estado', '!=', ComprobanteProveedorEstados::ANULADO);
                [$sql, $bindings] = TrackingAntiguedadDeuda::sqlPredicadoTramo(
                    TrackingAntiguedadDeuda::MAS_DE_90
                );
                $query->whereRaw($sql, $bindings);
                break;

            case self::SEGMENTO_CARGADOS_ENTRE_FECHAS:
                $query->whereNotNull('comprobante_tracking_indice.fechacarga_efectiva');
                break;
        }
    }

    /**
     * @param  Builder<\App\Models\Compras\Comprobante_Proveedor>  $query
     * @param  array<string, mixed>  $filtros
     */
    private static function aplicarRangoFechas(Builder $query, array $filtros): void
    {
        $columna = self::columnaEjeFecha((string) ($filtros['eje_fecha'] ?? self::EJE_FECHA_COMPROBANTE));
        $desde = self::parsearFecha((string) ($filtros['fecha_desde'] ?? ''));
        $hasta = self::parsearFecha((string) ($filtros['fecha_hasta'] ?? ''));

        if ($desde !== null) {
            $query->whereDate($columna, '>=', $desde);
        }
        if ($hasta !== null) {
            $query->whereDate($columna, '<=', $hasta);
        }
    }

    /**
     * @param  Builder<\App\Models\Compras\Comprobante_Proveedor>  $query
     */
    private static function aplicarFamilia(Builder $query, string $familia): void
    {
        if (! TrackingComprobanteFamilia::esFamiliaValida($familia)) {
            return;
        }

        $codigos = TrackingComprobanteFamilia::codigosAfipDeFamilia($familia);
        $abreviaturas = TrackingComprobanteFamilia::abreviaturasDeFamilia($familia);

        $query->where(function ($q) use ($codigos, $abreviaturas, $familia) {
            if ($abreviaturas !== []) {
                $q->whereIn('tipotransaccion_compra.abreviatura', $abreviaturas);
            }
            if ($codigos !== []) {
                $q->orWhere(function ($q2) use ($codigos) {
                    $q2->whereIn('tipotransaccion_compra.codigoafip', $codigos);
                });
            }
            // El recibo comparte el código de familia con la factura, así que
            // pedir facturas no puede devolver los recibos.
            if ($familia !== TrackingComprobanteFamilia::RECIBO && $codigos !== []) {
                $q->whereNotIn(
                    'tipotransaccion_compra.abreviatura',
                    [TrackingComprobanteFamilia::ABREVIATURA_RECIBO]
                );
            }
        });
    }

    /**
     * @param  Builder<\App\Models\Compras\Comprobante_Proveedor>  $query
     */
    private static function aplicarBusquedaGlobal(Builder $query, string $operador, string $valor): void
    {
        if ($operador === 'vacio' || $valor === '') {
            return;
        }

        $entero = filter_var($valor, FILTER_VALIDATE_INT);
        $like = self::patronLike($operador, $valor);

        $query->where(function ($q) use ($valor, $like, $entero, $operador) {
            if ($entero !== false) {
                $q->orWhere('comprobante_proveedor.id', (int) $entero)
                    ->orWhere('comprobante_proveedor.sucursal', (int) $entero)
                    ->orWhere('comprobante_proveedor.numerocomprobante', (int) $entero)
                    ->orWhere('comprobante_proveedor.anita_nro_interno', (int) $entero);
            }

            foreach (self::columnasTextoBusquedaGlobal() as $col) {
                $q->orWhere($col, 'like', $like);
                if ($operador === 'contiene' && in_array($col, self::COLUMNAS_COINCIDENCIA_FLEXIBLE, true)) {
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
            if ($fecha !== null) {
                $q->orWhereDate('comprobante_proveedor.fechacomprobante', '=', $fecha)
                    ->orWhereDate('comprobante_tracking_indice.fechacarga_efectiva', '=', $fecha);
            }
        });
    }

    /** @return list<string> */
    private static function columnasTextoBusquedaGlobal(): array
    {
        return [
            'empresa.nombre',
            'proveedor.nombre',
            'proveedor.nroinscripcion',
            'tipotransaccion_compra.nombre',
            'tipotransaccion_compra.abreviatura',
            'comprobante_proveedor.letra',
            'comprobante_proveedor.estado',
            'ordencompra.numeroordencompra',
        ];
    }

    /**
     * @param  Builder<\App\Models\Compras\Comprobante_Proveedor>  $query
     */
    private static function aplicarEnCampo(
        Builder $query,
        string $campoKey,
        string $operador,
        string $valor,
        string $valorHasta,
    ): void {
        $def = self::CAMPOS[$campoKey] ?? self::CAMPOS['nombreproveedor'];

        match ($def['type']) {
            'entero' => self::aplicarEntero($query, $def['column'], $operador, $valor),
            'fecha' => self::aplicarFechaColumna($query, $def['column'], $operador, $valor, $valorHasta),
            default => self::aplicarTexto($query, $def['column'], $operador, $valor),
        };
    }

    /**
     * @param  Builder<\App\Models\Compras\Comprobante_Proveedor>  $query
     */
    private static function aplicarTexto(Builder $query, string $column, string $operador, string $valor): void
    {
        if ($operador === 'vacio') {
            $query->where(fn ($q) => $q->whereNull($column)->orWhere($column, ''));

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
            default:
                $query->where(function ($q) use ($column, $valor) {
                    $q->where($column, 'like', '%'.self::escapeLike($valor).'%');
                    if (in_array($column, self::COLUMNAS_COINCIDENCIA_FLEXIBLE, true)) {
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
     * @param  Builder<\App\Models\Compras\Comprobante_Proveedor>  $query
     */
    private static function aplicarEntero(Builder $query, string $column, string $operador, string $valor): void
    {
        $entero = filter_var($valor, FILTER_VALIDATE_INT);
        if ($entero === false) {
            return;
        }

        match ($operador) {
            'mayor' => $query->where($column, '>', (int) $entero),
            'menor' => $query->where($column, '<', (int) $entero),
            default => $query->where($column, '=', (int) $entero),
        };
    }

    /**
     * @param  Builder<\App\Models\Compras\Comprobante_Proveedor>  $query
     */
    private static function aplicarFechaColumna(
        Builder $query,
        string $column,
        string $operador,
        string $valor,
        string $valorHasta,
    ): void {
        if ($operador === 'vacio') {
            $query->whereNull($column);

            return;
        }

        $desde = self::parsearFecha($valor);
        $hasta = self::parsearFecha($valorHasta);

        switch ($operador) {
            case 'desde':
                if ($desde !== null) {
                    $query->whereDate($column, '>=', $desde);
                }
                break;
            case 'hasta':
                if ($desde !== null) {
                    $query->whereDate($column, '<=', $desde);
                }
                break;
            case 'entre':
                if ($desde !== null) {
                    $query->whereDate($column, '>=', $desde);
                }
                if ($hasta !== null) {
                    $query->whereDate($column, '<=', $hasta);
                }
                break;
            default:
                if ($desde !== null) {
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

        foreach (['Y-m-d', 'd/m/Y', 'd-m-Y'] as $formato) {
            try {
                return Carbon::createFromFormat($formato, $valor)->format('Y-m-d');
            } catch (\Throwable) {
                continue;
            }
        }

        return null;
    }

    private static function patronLike(string $operador, string $valor): string
    {
        $escapado = self::escapeLike($valor);

        return match ($operador) {
            'empieza' => $escapado.'%',
            'termina' => '%'.$escapado,
            'igual' => $escapado,
            default => '%'.$escapado.'%',
        };
    }

    private static function escapeLike(string $value): string
    {
        return addcslashes($value, '%_\\');
    }

    private static function normalizarOperador(string $operador, string $campoKey): string
    {
        $permitidos = array_keys(self::operadoresParaCampo($campoKey));

        return in_array($operador, $permitidos, true) ? $operador : ($permitidos[0] ?? 'contiene');
    }

    /**
     * @return array<string, string>
     */
    public static function operadoresParaCampo(string $campoKey): array
    {
        return match (self::CAMPOS[$campoKey]['type'] ?? 'texto') {
            'entero' => self::OPERADORES_ENTERO,
            'fecha' => self::OPERADORES_FECHA,
            default => self::OPERADORES_TEXTO,
        };
    }
}
