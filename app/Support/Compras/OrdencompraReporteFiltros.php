<?php

namespace App\Support\Compras;

use Carbon\Carbon;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;

/**
 * Filtros del informe de órdenes de compra (Anita l-pedprov).
 */
final class OrdencompraReporteFiltros
{
    public const ESTADO_ACTIVOS = 'activos';

    public const ESTADO_SUSPENDIDOS = 'suspendidos';

    public const ESTADO_ACTIVOS_SUSPENDIDOS = 'activos_suspendidos';

    public const ESTADO_ACTIVOS_CERRADOS = 'activos_cerrados';

    public const ESTADO_CERRADOS = 'cerrados';

    public const ESTADO_TODOS = 'todos';

    public const PENDIENTE_PENDIENTES = 'pendientes';

    public const PENDIENTE_PENDIENTES_EXCEDIDOS = 'pendientes_excedidos';

    public const PENDIENTE_RECEPCIONADAS = 'recepcionadas';

    public const PENDIENTE_TODOS = 'todos';

    public const ANTICIPADA_TODAS = 'todas';

    public const ANTICIPADA_SI = 'anticipadas';

    public const ANTICIPADA_NO = 'no_anticipadas';

    public const AGRUPACION_ARTICULO = 'articulo';

    public const AGRUPACION_PROVEEDOR = 'proveedor';

    public const AGRUPACION_PROVEEDOR_PEDIDO = 'proveedor_pedido';

    public const AGRUPACION_PEDIDO = 'pedido';

    public const AGRUPACION_REQUISICION = 'requisicion';

    public const AGRUPACION_PARTIDA = 'partida';

    public const AGRUPACION_CAPEX = 'capex';

    public const AGRUPACION_AGRUPACION = 'agrupacion';

    public const MODO_MOVIMIENTOS = 'movimientos';

    public const MODO_TOTALES = 'totales';

    /** @var list<array{valor: string, etiqueta: string}> */
    public const OPCIONES_ESTADO = [
        ['valor' => self::ESTADO_ACTIVOS, 'etiqueta' => 'Activos'],
        ['valor' => self::ESTADO_SUSPENDIDOS, 'etiqueta' => 'Suspendidos'],
        ['valor' => self::ESTADO_ACTIVOS_SUSPENDIDOS, 'etiqueta' => 'Activos y suspendidos'],
        ['valor' => self::ESTADO_ACTIVOS_CERRADOS, 'etiqueta' => 'Activos y cerrados'],
        ['valor' => self::ESTADO_CERRADOS, 'etiqueta' => 'Cerrados'],
        ['valor' => self::ESTADO_TODOS, 'etiqueta' => 'Todos los estados'],
    ];

    /** @var list<array{valor: string, etiqueta: string}> */
    public const OPCIONES_PENDIENTE = [
        ['valor' => self::PENDIENTE_PENDIENTES, 'etiqueta' => 'Pendientes'],
        ['valor' => self::PENDIENTE_PENDIENTES_EXCEDIDOS, 'etiqueta' => 'Pendientes y excedidos'],
        ['valor' => self::PENDIENTE_RECEPCIONADAS, 'etiqueta' => 'Recepcionadas'],
        ['valor' => self::PENDIENTE_TODOS, 'etiqueta' => 'Todos'],
    ];

    /** @var list<array{valor: string, etiqueta: string}> */
    public const OPCIONES_ANTICIPADA = [
        ['valor' => self::ANTICIPADA_TODAS, 'etiqueta' => 'Anticipadas y no anticipadas'],
        ['valor' => self::ANTICIPADA_SI, 'etiqueta' => 'Anticipadas'],
        ['valor' => self::ANTICIPADA_NO, 'etiqueta' => 'No anticipadas'],
    ];

    /** @var list<array{valor: string, etiqueta: string}> */
    public const OPCIONES_AGRUPACION = [
        ['valor' => self::AGRUPACION_PEDIDO, 'etiqueta' => 'Por nro. de pedido (OC)'],
        ['valor' => self::AGRUPACION_PROVEEDOR, 'etiqueta' => 'Por proveedor'],
        ['valor' => self::AGRUPACION_PROVEEDOR_PEDIDO, 'etiqueta' => 'Por proveedor x pedido'],
        ['valor' => self::AGRUPACION_ARTICULO, 'etiqueta' => 'Por artículo'],
        ['valor' => self::AGRUPACION_REQUISICION, 'etiqueta' => 'Por requisición'],
        ['valor' => self::AGRUPACION_PARTIDA, 'etiqueta' => 'Por partida de gasto'],
        ['valor' => self::AGRUPACION_CAPEX, 'etiqueta' => 'Por CAPEX'],
        ['valor' => self::AGRUPACION_AGRUPACION, 'etiqueta' => 'Por agrupación (categoría)'],
    ];

    /** @var list<array{valor: string, etiqueta: string}> */
    public const OPCIONES_MODO_LISTADO = [
        ['valor' => self::MODO_MOVIMIENTOS, 'etiqueta' => 'Movimientos (detalle)'],
        ['valor' => self::MODO_TOTALES, 'etiqueta' => 'Totales solamente'],
    ];

    /**
     * @return array<string, mixed>
     */
    public static function resolverDesdeRequest(Request $request): array
    {
        $empresaIds = collect($request->input('empresa_ids', []))
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => $id > 0)
            ->unique()
            ->values()
            ->all();

        $estado = trim((string) $request->input('estado_oc', self::ESTADO_ACTIVOS));
        if (! self::estadoValido($estado)) {
            $estado = self::ESTADO_ACTIVOS;
        }

        $pendiente = trim((string) $request->input('pendiente', self::PENDIENTE_PENDIENTES));
        if (! self::pendienteValido($pendiente)) {
            $pendiente = self::PENDIENTE_PENDIENTES;
        }

        $anticipada = trim((string) $request->input('anticipada', self::ANTICIPADA_TODAS));
        if (! self::anticipadaValida($anticipada)) {
            $anticipada = self::ANTICIPADA_TODAS;
        }

        $agrupacion = trim((string) $request->input('agrupacion', self::AGRUPACION_PEDIDO));
        if (! self::agrupacionValida($agrupacion)) {
            $agrupacion = self::AGRUPACION_PEDIDO;
        }

        $modoListado = trim((string) $request->input('modo_listado', self::MODO_MOVIMIENTOS));
        if (! self::modoListadoValido($modoListado)) {
            $modoListado = self::MODO_MOVIMIENTOS;
        }

        [$ocDesde, $ocHasta] = RequisicionReporteCriteriosSupport::normalizarRangoNumeros(
            trim((string) $request->input('ordencompra_desde', '')),
            trim((string) $request->input('ordencompra_hasta', '')),
        );

        return [
            'empresa_ids' => $empresaIds,
            'consolidar_empresas' => $request->boolean('consolidar_empresas', true),
            'fecha_desde' => self::fechaOpcional($request->input('fecha_desde')),
            'fecha_hasta' => self::fechaOpcional($request->input('fecha_hasta')),
            'ordencompra_desde' => $ocDesde,
            'ordencompra_hasta' => $ocHasta,
            'proveedores' => trim((string) $request->input('proveedores', '')),
            'usuarios' => trim((string) $request->input('usuarios', '')),
            'centrocostos_codigo' => trim((string) ($request->input('centrocostos_codigo', $request->input('centrocostos', '')))),
            'estado_oc' => $estado,
            'pendiente' => $pendiente,
            'anticipada' => $anticipada,
            'agrupacion' => $agrupacion,
            'modo_listado' => $modoListado,
        ];
    }

    /** @return array<string, mixed> */
    public static function paraQueryString(array $filtros): array
    {
        $query = array_filter([
            'fecha_desde' => $filtros['fecha_desde'] ?? null,
            'fecha_hasta' => $filtros['fecha_hasta'] ?? null,
            'ordencompra_desde' => ($filtros['ordencompra_desde'] ?? '') !== ''
                ? ($filtros['ordencompra_desde'] ?? null)
                : null,
            'ordencompra_hasta' => ($filtros['ordencompra_hasta'] ?? '') !== ''
                ? ($filtros['ordencompra_hasta'] ?? null)
                : null,
            'proveedores' => ($filtros['proveedores'] ?? '') !== ''
                ? ($filtros['proveedores'] ?? null)
                : null,
            'usuarios' => ($filtros['usuarios'] ?? '') !== ''
                ? ($filtros['usuarios'] ?? null)
                : null,
            'centrocostos_codigo' => ($filtros['centrocostos_codigo'] ?? '') !== ''
                ? ($filtros['centrocostos_codigo'] ?? null)
                : null,
            'estado_oc' => ($filtros['estado_oc'] ?? self::ESTADO_ACTIVOS) !== self::ESTADO_ACTIVOS
                ? ($filtros['estado_oc'] ?? null)
                : null,
            'pendiente' => ($filtros['pendiente'] ?? self::PENDIENTE_PENDIENTES) !== self::PENDIENTE_PENDIENTES
                ? ($filtros['pendiente'] ?? null)
                : null,
            'anticipada' => ($filtros['anticipada'] ?? self::ANTICIPADA_TODAS) !== self::ANTICIPADA_TODAS
                ? ($filtros['anticipada'] ?? null)
                : null,
            'agrupacion' => ($filtros['agrupacion'] ?? self::AGRUPACION_PEDIDO) !== self::AGRUPACION_PEDIDO
                ? ($filtros['agrupacion'] ?? null)
                : null,
            'modo_listado' => ($filtros['modo_listado'] ?? self::MODO_MOVIMIENTOS) !== self::MODO_MOVIMIENTOS
                ? ($filtros['modo_listado'] ?? null)
                : null,
            'consultar' => 1,
        ], fn ($v) => $v !== null && $v !== '');

        if (($filtros['empresa_ids'] ?? []) !== []) {
            $query['empresa_ids'] = array_values(array_map(
                'intval',
                $filtros['empresa_ids'],
            ));
        }

        if (empty($filtros['consolidar_empresas'])) {
            $query['consolidar_empresas'] = 0;
        }

        return $query;
    }

    public static function tieneCriteriosAplicados(array $filtros): bool
    {
        return ($filtros['empresa_ids'] ?? []) !== []
            && ! empty($filtros['fecha_desde']);
    }

    /** @return array<string, mixed> */
    public static function defaults(): array
    {
        $hoy = Carbon::now();

        return [
            'empresa_ids' => [],
            'consolidar_empresas' => true,
            'fecha_desde' => $hoy->copy()->startOfMonth()->format('Y-m-d'),
            'fecha_hasta' => $hoy->copy()->endOfMonth()->format('Y-m-d'),
            'ordencompra_desde' => '',
            'ordencompra_hasta' => '',
            'proveedores' => '',
            'usuarios' => '',
            'centrocostos_codigo' => '',
            'estado_oc' => self::ESTADO_ACTIVOS,
            'pendiente' => self::PENDIENTE_PENDIENTES,
            'anticipada' => self::ANTICIPADA_TODAS,
            'agrupacion' => self::AGRUPACION_PEDIDO,
            'modo_listado' => self::MODO_MOVIMIENTOS,
        ];
    }

    public static function etiquetaAgrupacion(string $agrupacion): string
    {
        foreach (self::OPCIONES_AGRUPACION as $opcion) {
            if ($opcion['valor'] === $agrupacion) {
                return $opcion['etiqueta'];
            }
        }

        return self::OPCIONES_AGRUPACION[0]['etiqueta'];
    }

    public static function etiquetaModoListado(string $modoListado): string
    {
        foreach (self::OPCIONES_MODO_LISTADO as $opcion) {
            if ($opcion['valor'] === $modoListado) {
                return $opcion['etiqueta'];
            }
        }

        return self::OPCIONES_MODO_LISTADO[0]['etiqueta'];
    }

    public static function subtituloEstado(string $estado): string
    {
        return match ($estado) {
            self::ESTADO_ACTIVOS => 'Listando OC activas',
            self::ESTADO_SUSPENDIDOS => 'Listando OC suspendidas',
            self::ESTADO_ACTIVOS_SUSPENDIDOS => 'Listando OC activas y suspendidas',
            self::ESTADO_ACTIVOS_CERRADOS => 'Listando OC activas y cerradas',
            self::ESTADO_CERRADOS => 'Listando OC cerradas',
            default => 'Listando todas las OC',
        };
    }

    public static function subtituloPendiente(string $pendiente): string
    {
        return match ($pendiente) {
            self::PENDIENTE_PENDIENTES => 'Solo pendientes de recepción',
            self::PENDIENTE_PENDIENTES_EXCEDIDOS => 'Pendientes y excedidos',
            self::PENDIENTE_RECEPCIONADAS => 'Solo recepcionadas',
            default => 'Pendientes y recepcionadas',
        };
    }

    public static function aplicarEstadoOc(Builder $query, string $estado): void
    {
        switch ($estado) {
            case self::ESTADO_ACTIVOS:
                $query->whereIn('oc.estadoordencompra', [
                    OrdencompraEstados::PENDIENTE,
                    OrdencompraEstados::APROBADA,
                    OrdencompraEstados::CUMPLIDA,
                ]);
                break;
            case self::ESTADO_SUSPENDIDOS:
                $query->where('oc.estadoordencompra', OrdencompraEstados::SUSPENDIDA);
                break;
            case self::ESTADO_ACTIVOS_SUSPENDIDOS:
                $query->whereIn('oc.estadoordencompra', [
                    OrdencompraEstados::PENDIENTE,
                    OrdencompraEstados::APROBADA,
                    OrdencompraEstados::CUMPLIDA,
                    OrdencompraEstados::SUSPENDIDA,
                ]);
                break;
            case self::ESTADO_ACTIVOS_CERRADOS:
                $query->whereIn('oc.estadoordencompra', [
                    OrdencompraEstados::PENDIENTE,
                    OrdencompraEstados::APROBADA,
                    OrdencompraEstados::CUMPLIDA,
                    OrdencompraEstados::CERRADA,
                ]);
                break;
            case self::ESTADO_CERRADOS:
                $query->where('oc.estadoordencompra', OrdencompraEstados::CERRADA);
                break;
        }
    }

    public static function aplicarAnticipada(Builder $query, string $anticipada): void
    {
        switch ($anticipada) {
            case self::ANTICIPADA_SI:
                $query->where(function (Builder $sub) {
                    $sub->where('oc.tratamiento', 'ANTICIPADA')
                        ->orWhere('oc.tratamiento', '2')
                        ->orWhere('oc.tratamiento', 'S');
                });
                break;
            case self::ANTICIPADA_NO:
                $query->where(function (Builder $sub) {
                    $sub->whereNull('oc.tratamiento')
                        ->orWhere(function (Builder $inner) {
                            $inner->where('oc.tratamiento', '!=', 'ANTICIPADA')
                                ->where('oc.tratamiento', '!=', '2')
                                ->where('oc.tratamiento', '!=', 'S');
                        });
                });
                break;
        }
    }

    public static function formatearPeriodoTexto(array $filtros): string
    {
        $desde = self::formatearFechaPantalla($filtros['fecha_desde'] ?? null);
        $hasta = self::formatearFechaPantalla($filtros['fecha_hasta'] ?? null);

        if ($desde !== '' && $hasta !== '') {
            return $desde.' — '.$hasta;
        }

        if ($desde !== '') {
            return 'Desde '.$desde.' hasta último mov.';
        }

        return '—';
    }

    public static function formatearFechaPantalla(?string $fecha): string
    {
        $fecha = trim((string) $fecha);
        if ($fecha === '') {
            return '';
        }

        try {
            return Carbon::parse($fecha)->format('d/m/y');
        } catch (\Throwable) {
            return $fecha;
        }
    }

    private static function estadoValido(string $estado): bool
    {
        foreach (self::OPCIONES_ESTADO as $opcion) {
            if ($opcion['valor'] === $estado) {
                return true;
            }
        }

        return false;
    }

    private static function pendienteValido(string $pendiente): bool
    {
        foreach (self::OPCIONES_PENDIENTE as $opcion) {
            if ($opcion['valor'] === $pendiente) {
                return true;
            }
        }

        return false;
    }

    private static function anticipadaValida(string $anticipada): bool
    {
        foreach (self::OPCIONES_ANTICIPADA as $opcion) {
            if ($opcion['valor'] === $anticipada) {
                return true;
            }
        }

        return false;
    }

    private static function agrupacionValida(string $agrupacion): bool
    {
        foreach (self::OPCIONES_AGRUPACION as $opcion) {
            if ($opcion['valor'] === $agrupacion) {
                return true;
            }
        }

        return false;
    }

    private static function modoListadoValido(string $modoListado): bool
    {
        foreach (self::OPCIONES_MODO_LISTADO as $opcion) {
            if ($opcion['valor'] === $modoListado) {
                return true;
            }
        }

        return false;
    }

    private static function fechaOpcional($valor): ?string
    {
        $valor = trim((string) $valor);

        return $valor !== '' ? substr($valor, 0, 10) : null;
    }
}
