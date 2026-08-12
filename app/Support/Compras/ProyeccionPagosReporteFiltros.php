<?php

namespace App\Support\Compras;

use Carbon\Carbon;
use Illuminate\Http\Request;

/**
 * Filtros del informe de proyección de pagos a proveedores (equivalente Anita l-proy.c).
 */
final class ProyeccionPagosReporteFiltros
{
    public const INFORME_A_VENCER = 'a_vencer';

    public const INFORME_VENCIDOS = 'vencidos';

    public const VENCIMIENTO_DIAS = 'dias';

    public const VENCIMIENTO_MES = 'mes';

    public const MONEDA_ORIGEN = 'origen';

    public const MONEDA_TODAS = 'todas';

    public const MONEDA_TODAS_HIST = 'todas_hist';

    public const SALIDA_DETALLE = 'detalle';

    public const SALIDA_RESUMEN = 'resumen';

    public const APROBACION_TODOS = 'todos';

    public const APROBACION_APROBADOS = 'aprobados';

    public const APROBACION_PENDIENTES = 'pendientes';

    public const AGRUPACION_PROVEEDOR = 'proveedor';

    public const AGRUPACION_EMPRESA = 'empresa';

    public const AGRUPACION_MONEDA = 'moneda';

    public const AGRUPACION_MEDIO_PAGO = 'medio_pago';

    public const AGRUPACION_CONDICION_PAGO = 'condicion_pago';

    public const AGRUPACION_CONCEPTO = 'concepto';

    public const AGRUPACION_TRAMO = 'tramo';

    public const AGRUPACION_SIN = 'sin';

    public const ORDEN_CODIGO = 'codigo';

    public const ORDEN_NOMBRE = 'nombre';

    public const ORDEN_TOTAL_DESC = 'total_desc';

    public const ORDEN_TOTAL_ASC = 'total_asc';

    public const ORDEN_VENCIMIENTO = 'vencimiento';

    public const ORDEN_DIAS = 'dias';

    public const MAX_TRAMOS = 8;

    public const TRAMOS_DIAS_DEFAULT = '7,15,30,60,90,120';

    public const TRAMOS_MESES_DEFAULT = '';

    /** @var list<array{valor: string, etiqueta: string}> */
    public const OPCIONES_INFORME = [
        ['valor' => self::INFORME_A_VENCER, 'etiqueta' => 'A vencer (proyección hacia adelante)'],
        ['valor' => self::INFORME_VENCIDOS, 'etiqueta' => 'Vencidos (antigüedad hacia atrás)'],
    ];

    /** @var list<array{valor: string, etiqueta: string}> */
    public const OPCIONES_VENCIMIENTO = [
        ['valor' => self::VENCIMIENTO_DIAS, 'etiqueta' => 'Tramos por días'],
        ['valor' => self::VENCIMIENTO_MES, 'etiqueta' => 'Tramos por mes'],
    ];

    /** @var list<array{valor: string, etiqueta: string}> */
    public const OPCIONES_MONEDA = [
        ['valor' => self::MONEDA_ORIGEN, 'etiqueta' => 'Moneda de origen'],
        ['valor' => self::MONEDA_TODAS, 'etiqueta' => 'Todas las monedas (cotización a la fecha base)'],
        ['valor' => self::MONEDA_TODAS_HIST, 'etiqueta' => 'Todas las monedas (cotización histórica)'],
    ];

    /** @var list<array{valor: string, etiqueta: string}> */
    public const OPCIONES_SALIDA = [
        ['valor' => self::SALIDA_DETALLE, 'etiqueta' => 'Abre por comprobante (detalle)'],
        ['valor' => self::SALIDA_RESUMEN, 'etiqueta' => 'Solo totales por grupo'],
    ];

    /** @var list<array{valor: string, etiqueta: string}> */
    public const OPCIONES_APROBACION = [
        ['valor' => self::APROBACION_TODOS, 'etiqueta' => 'Aprobados y pendientes'],
        ['valor' => self::APROBACION_APROBADOS, 'etiqueta' => 'Solo aprobados'],
        ['valor' => self::APROBACION_PENDIENTES, 'etiqueta' => 'Solo pendientes de aprobación'],
    ];

    /** @var list<array{valor: string, etiqueta: string}> */
    public const OPCIONES_AGRUPACION = [
        ['valor' => self::AGRUPACION_PROVEEDOR, 'etiqueta' => 'Por proveedor'],
        ['valor' => self::AGRUPACION_EMPRESA, 'etiqueta' => 'Por empresa'],
        ['valor' => self::AGRUPACION_MONEDA, 'etiqueta' => 'Por moneda'],
        ['valor' => self::AGRUPACION_MEDIO_PAGO, 'etiqueta' => 'Por medio de pago'],
        ['valor' => self::AGRUPACION_CONDICION_PAGO, 'etiqueta' => 'Por condición de pago'],
        ['valor' => self::AGRUPACION_CONCEPTO, 'etiqueta' => 'Por concepto de cash flow'],
        ['valor' => self::AGRUPACION_TRAMO, 'etiqueta' => 'Por tramo de vencimiento'],
        ['valor' => self::AGRUPACION_SIN, 'etiqueta' => 'Sin agrupar'],
    ];

    /** @var list<array{valor: string, etiqueta: string}> */
    public const OPCIONES_ORDEN = [
        ['valor' => self::ORDEN_CODIGO, 'etiqueta' => 'Código de proveedor'],
        ['valor' => self::ORDEN_NOMBRE, 'etiqueta' => 'Alfabético por nombre'],
        ['valor' => self::ORDEN_TOTAL_DESC, 'etiqueta' => 'Mayor deuda primero'],
        ['valor' => self::ORDEN_TOTAL_ASC, 'etiqueta' => 'Menor deuda primero'],
        ['valor' => self::ORDEN_VENCIMIENTO, 'etiqueta' => 'Fecha de vencimiento'],
        ['valor' => self::ORDEN_DIAS, 'etiqueta' => 'Días al vencimiento'],
    ];

    /**
     * @return array<string, mixed>
     */
    public static function resolverDesdeRequest(Request $request): array
    {
        $defaults = self::defaults();

        $empresaIds = collect($request->input('empresa_ids', []))
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => $id > 0)
            ->unique()
            ->values()
            ->all();

        $tipoInforme = self::valorValido(
            (string) $request->input('tipo_informe', $defaults['tipo_informe']),
            self::OPCIONES_INFORME,
            $defaults['tipo_informe'],
        );

        $tipoVencimiento = self::valorValido(
            (string) $request->input('tipo_vencimiento', $defaults['tipo_vencimiento']),
            self::OPCIONES_VENCIMIENTO,
            $defaults['tipo_vencimiento'],
        );

        $modoMoneda = self::valorValido(
            (string) $request->input('modo_moneda', $defaults['modo_moneda']),
            self::OPCIONES_MONEDA,
            $defaults['modo_moneda'],
        );

        $salida = self::valorValido(
            (string) $request->input('salida', $defaults['salida']),
            self::OPCIONES_SALIDA,
            $defaults['salida'],
        );

        $aprobacion = self::valorValido(
            (string) $request->input('estado_aprobacion', $defaults['estado_aprobacion']),
            self::OPCIONES_APROBACION,
            $defaults['estado_aprobacion'],
        );

        $agrupacion = self::valorValido(
            (string) $request->input('agrupacion', $defaults['agrupacion']),
            self::OPCIONES_AGRUPACION,
            $defaults['agrupacion'],
        );

        $orden = self::valorValido(
            (string) $request->input('orden', $defaults['orden']),
            self::OPCIONES_ORDEN,
            $defaults['orden'],
        );

        $tramosDias = self::normalizarListaNumeros((string) $request->input('tramos_dias', ''), 1, 999);
        $tramosMeses = self::normalizarListaNumeros((string) $request->input('tramos_meses', ''), 1, 12);

        if ($tipoVencimiento === self::VENCIMIENTO_DIAS && $tramosDias === []) {
            $tramosDias = self::normalizarListaNumeros($defaults['tramos_dias'], 1, 999);
        }

        return [
            'empresa_ids' => $empresaIds,
            'consolidar_empresas' => $request->boolean('consolidar_empresas', true),
            'tipo_informe' => $tipoInforme,
            'fecha_base' => self::fechaOpcional($request->input('fecha_base')) ?? $defaults['fecha_base'],
            'tipo_vencimiento' => $tipoVencimiento,
            'tramos_dias' => implode(',', $tramosDias),
            'tramos_meses' => implode(',', $tramosMeses),
            'abre_anterior' => $request->boolean('abre_anterior'),
            'dias_anterior' => max(0, min(999, (int) $request->input('dias_anterior', $defaults['dias_anterior']))),
            'moneda_id' => (int) $request->input('moneda_id', $defaults['moneda_id']),
            'modo_moneda' => $modoMoneda,
            'proveedores_codigo' => trim((string) $request->input('proveedores_codigo', '')),
            'proveedor_nombre' => trim((string) $request->input('proveedor_nombre', '')),
            'tipotransaccion_ids' => collect($request->input('tipotransaccion_ids', []))
                ->map(fn ($id) => (int) $id)
                ->filter(fn (int $id) => $id > 0)
                ->unique()
                ->values()
                ->all(),
            'condiciones_compensar' => trim((string) $request->input('condiciones_compensar', '')),
            'incluir_adelantos' => $request->boolean('incluir_adelantos', ! $request->has('consultar')),
            'estado_aprobacion' => $aprobacion,
            'fecha_carga_desde' => self::fechaOpcional($request->input('fecha_carga_desde')),
            'hora_carga_desde' => self::horaOpcional($request->input('hora_carga_desde')),
            'salida' => $salida,
            'agrupacion' => $agrupacion,
            'orden' => $orden,
            'columnas' => trim((string) $request->input('columnas', '')),
        ];
    }

    /** @return array<string, mixed> */
    public static function defaults(): array
    {
        return [
            'empresa_ids' => [],
            'consolidar_empresas' => true,
            'tipo_informe' => self::INFORME_A_VENCER,
            'fecha_base' => Carbon::now()->format('Y-m-d'),
            'tipo_vencimiento' => self::VENCIMIENTO_DIAS,
            'tramos_dias' => self::TRAMOS_DIAS_DEFAULT,
            'tramos_meses' => self::TRAMOS_MESES_DEFAULT,
            'abre_anterior' => false,
            'dias_anterior' => 30,
            'moneda_id' => (int) config('cotizacion.ID_MONEDA_DEFAULT', 1),
            'modo_moneda' => self::MONEDA_TODAS,
            'proveedores_codigo' => '',
            'proveedor_nombre' => '',
            'tipotransaccion_ids' => [],
            'condiciones_compensar' => '',
            'incluir_adelantos' => true,
            'estado_aprobacion' => self::APROBACION_TODOS,
            'fecha_carga_desde' => null,
            'hora_carga_desde' => null,
            'salida' => self::SALIDA_DETALLE,
            'agrupacion' => self::AGRUPACION_PROVEEDOR,
            'orden' => self::ORDEN_CODIGO,
            'columnas' => '',
        ];
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return array<string, mixed>
     */
    public static function paraQueryString(array $filtros): array
    {
        $defaults = self::defaults();
        $query = [
            'tipo_informe' => $filtros['tipo_informe'] ?? null,
            'fecha_base' => $filtros['fecha_base'] ?? null,
            'tipo_vencimiento' => $filtros['tipo_vencimiento'] ?? null,
            'tramos_dias' => $filtros['tramos_dias'] ?? null,
            'tramos_meses' => $filtros['tramos_meses'] ?? null,
            'dias_anterior' => ! empty($filtros['abre_anterior']) ? ($filtros['dias_anterior'] ?? null) : null,
            'moneda_id' => (int) ($filtros['moneda_id'] ?? 0) > 0 ? (int) $filtros['moneda_id'] : null,
            'modo_moneda' => $filtros['modo_moneda'] ?? null,
            'proveedores_codigo' => ($filtros['proveedores_codigo'] ?? '') !== '' ? $filtros['proveedores_codigo'] : null,
            'proveedor_nombre' => ($filtros['proveedor_nombre'] ?? '') !== '' ? $filtros['proveedor_nombre'] : null,
            'condiciones_compensar' => ($filtros['condiciones_compensar'] ?? '') !== '' ? $filtros['condiciones_compensar'] : null,
            'estado_aprobacion' => ($filtros['estado_aprobacion'] ?? '') !== $defaults['estado_aprobacion']
                ? ($filtros['estado_aprobacion'] ?? null)
                : null,
            'fecha_carga_desde' => $filtros['fecha_carga_desde'] ?? null,
            'hora_carga_desde' => $filtros['hora_carga_desde'] ?? null,
            'salida' => $filtros['salida'] ?? null,
            'agrupacion' => $filtros['agrupacion'] ?? null,
            'orden' => $filtros['orden'] ?? null,
            'columnas' => ($filtros['columnas'] ?? '') !== '' ? $filtros['columnas'] : null,
            'consultar' => 1,
        ];

        $query = array_filter($query, fn ($valor) => $valor !== null && $valor !== '');

        if (($filtros['empresa_ids'] ?? []) !== []) {
            $query['empresa_ids'] = array_values(array_map('intval', $filtros['empresa_ids']));
        }

        if (($filtros['tipotransaccion_ids'] ?? []) !== []) {
            $query['tipotransaccion_ids'] = array_values(array_map('intval', $filtros['tipotransaccion_ids']));
        }

        if (empty($filtros['consolidar_empresas'])) {
            $query['consolidar_empresas'] = 0;
        }

        if (! empty($filtros['abre_anterior'])) {
            $query['abre_anterior'] = 1;
        }

        if (empty($filtros['incluir_adelantos'])) {
            $query['incluir_adelantos'] = 0;
        }

        return $query;
    }

    /** @param array<string, mixed> $filtros */
    public static function tieneCriteriosAplicados(array $filtros): bool
    {
        return ($filtros['empresa_ids'] ?? []) !== []
            && ! empty($filtros['fecha_base'])
            && self::tramos($filtros) !== [];
    }

    /** @param array<string, mixed> $filtros */
    public static function firma(array $filtros): string
    {
        return md5(json_encode(self::paraQueryString($filtros), JSON_UNESCAPED_UNICODE) ?: '');
    }

    /**
     * Tramos configurados (días o meses según tipo de vencimiento).
     *
     * @param  array<string, mixed>  $filtros
     * @return list<int>
     */
    public static function tramos(array $filtros): array
    {
        $porMes = ($filtros['tipo_vencimiento'] ?? self::VENCIMIENTO_DIAS) === self::VENCIMIENTO_MES;

        return $porMes
            ? self::normalizarListaNumeros((string) ($filtros['tramos_meses'] ?? ''), 1, 12)
            : self::normalizarListaNumeros((string) ($filtros['tramos_dias'] ?? ''), 1, 999);
    }

    /**
     * @return list<int>
     */
    public static function normalizarListaNumeros(string $valor, int $min, int $max): array
    {
        $partes = preg_split('/[^0-9]+/', $valor) ?: [];

        return collect($partes)
            ->map(fn ($v) => (int) $v)
            ->filter(fn (int $v) => $v >= $min && $v <= $max)
            ->unique()
            ->values()
            ->take(self::MAX_TRAMOS)
            ->all();
    }

    /**
     * Códigos sueltos y rangos (`100,105` / `100/110`).
     *
     * @return array{codigos: list<string>, desde: string, hasta: string}
     */
    public static function interpretarCodigos(string $valor): array
    {
        $valor = trim($valor);
        if ($valor === '') {
            return ['codigos' => [], 'desde' => '', 'hasta' => ''];
        }

        if (str_contains($valor, '/')) {
            $partes = explode('/', $valor, 2);

            return [
                'codigos' => [],
                'desde' => trim($partes[0]),
                'hasta' => trim($partes[1] ?? ''),
            ];
        }

        $codigos = collect(preg_split('/[,;]+/', $valor) ?: [])
            ->map(fn ($v) => trim((string) $v))
            ->filter(fn (string $v) => $v !== '')
            ->unique()
            ->values()
            ->all();

        return ['codigos' => $codigos, 'desde' => '', 'hasta' => ''];
    }

    public static function etiqueta(string $valor, array $opciones, string $default = ''): string
    {
        foreach ($opciones as $opcion) {
            if ($opcion['valor'] === $valor) {
                return $opcion['etiqueta'];
            }
        }

        return $default !== '' ? $default : ($opciones[0]['etiqueta'] ?? '');
    }

    public static function formatearFechaPantalla(?string $fecha): string
    {
        $fecha = trim((string) $fecha);
        if ($fecha === '') {
            return '';
        }

        try {
            return Carbon::parse($fecha)->format('d/m/Y');
        } catch (\Throwable) {
            return $fecha;
        }
    }

    /** @param list<array{valor: string, etiqueta: string}> $opciones */
    private static function valorValido(string $valor, array $opciones, string $default): string
    {
        $valor = trim($valor);
        foreach ($opciones as $opcion) {
            if ($opcion['valor'] === $valor) {
                return $valor;
            }
        }

        return $default;
    }

    private static function fechaOpcional($valor): ?string
    {
        $valor = trim((string) $valor);

        return $valor !== '' ? substr($valor, 0, 10) : null;
    }

    private static function horaOpcional($valor): ?string
    {
        $valor = trim((string) $valor);
        if ($valor === '') {
            return null;
        }

        return preg_match('/^([01]\d|2[0-3]):([0-5]\d)$/', $valor) === 1 ? $valor : null;
    }
}
