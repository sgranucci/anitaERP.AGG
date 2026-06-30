<?php

namespace App\Support\Compras;

use Carbon\Carbon;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;

final class RequisicionReporteFiltros
{
    public const ESTADO_TODOS = 'todos';

    public const ESTADO_PENDIENTE = 'pendiente';

    public const ESTADO_CUMPLIDO = 'cumplido';

    public const ESTADO_EN_COMPRAS = 'en_compras';

    public const ESTADO_GENERO_OC = 'genero_oc';

    public const ESTADO_ARBOL = 'arbol';

    public const ESTADO_SUSPENDIDO = 'suspendido';

    public const ESTADO_APROBADA = 'aprobada';

    public const AGRUPACION_USUARIO = 'usuario';

    public const AGRUPACION_ARTICULO = 'articulo';

    public const AGRUPACION_CENTROCOSTO = 'centrocosto';

    public const AGRUPACION_REQUISICION = 'requisicion';

    public const MODO_MOVIMIENTOS = 'movimientos';

    public const MODO_TOTALES = 'totales';

    public const URGENTE_TODAS = 'todas';

    public const URGENTE_SI = 'urgente';

    public const URGENTE_NO = 'no_urgente';

    public const CONTRATACION_TODAS = 'todas';

    public const CONTRATACION_DIRECTAS = 'directas';

    public const CONTRATACION_NO_DIRECTAS = 'no_directas';

    /** @var list<array{valor: string, etiqueta: string}> */
    public const OPCIONES_ESTADO = [
        ['valor' => self::ESTADO_TODOS, 'etiqueta' => 'Todos los estados'],
        ['valor' => self::ESTADO_EN_COMPRAS, 'etiqueta' => 'En compras'],
        ['valor' => self::ESTADO_PENDIENTE, 'etiqueta' => 'Pendientes'],
        ['valor' => self::ESTADO_CUMPLIDO, 'etiqueta' => 'Cumplidos'],
        ['valor' => self::ESTADO_GENERO_OC, 'etiqueta' => 'Con OC generada'],
        ['valor' => self::ESTADO_APROBADA, 'etiqueta' => 'Aprobadas'],
        ['valor' => self::ESTADO_ARBOL, 'etiqueta' => 'En árbol de aprobación'],
        ['valor' => self::ESTADO_SUSPENDIDO, 'etiqueta' => 'Suspendidas'],
    ];

    /** @var list<array{valor: string, etiqueta: string}> */
    public const OPCIONES_AGRUPACION = [
        ['valor' => self::AGRUPACION_USUARIO, 'etiqueta' => 'Por usuario'],
        ['valor' => self::AGRUPACION_ARTICULO, 'etiqueta' => 'Por artículo'],
        ['valor' => self::AGRUPACION_CENTROCOSTO, 'etiqueta' => 'Por centro de costo'],
        ['valor' => self::AGRUPACION_REQUISICION, 'etiqueta' => 'Por requisición'],
    ];

    /** @var list<array{valor: string, etiqueta: string}> */
    public const OPCIONES_MODO_LISTADO = [
        ['valor' => self::MODO_MOVIMIENTOS, 'etiqueta' => 'Movimientos (detalle)'],
        ['valor' => self::MODO_TOTALES, 'etiqueta' => 'Totales solamente'],
    ];

    /** @var list<array{valor: string, etiqueta: string}> */
    public const OPCIONES_URGENTE = [
        ['valor' => self::URGENTE_TODAS, 'etiqueta' => 'Urgentes y no urgentes'],
        ['valor' => self::URGENTE_SI, 'etiqueta' => 'Solo urgentes'],
        ['valor' => self::URGENTE_NO, 'etiqueta' => 'Solo no urgentes'],
    ];

    /** @var list<array{valor: string, etiqueta: string}> */
    public const OPCIONES_CONTRATACION = [
        ['valor' => self::CONTRATACION_TODAS, 'etiqueta' => 'Todas las contrataciones'],
        ['valor' => self::CONTRATACION_NO_DIRECTAS, 'etiqueta' => 'Sin contratación directa'],
        ['valor' => self::CONTRATACION_DIRECTAS, 'etiqueta' => 'Contratación directa'],
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

        $estado = trim((string) $request->input('estado_requisicion', self::ESTADO_EN_COMPRAS));
        if (! self::estadoValido($estado)) {
            $estado = self::ESTADO_EN_COMPRAS;
        }

        $agrupacion = trim((string) $request->input('agrupacion', self::AGRUPACION_USUARIO));
        if (! self::agrupacionValida($agrupacion)) {
            $agrupacion = self::AGRUPACION_USUARIO;
        }

        $modoListado = trim((string) $request->input('modo_listado', self::MODO_MOVIMIENTOS));
        if (! self::modoListadoValido($modoListado)) {
            $modoListado = self::MODO_MOVIMIENTOS;
        }

        $urgente = trim((string) $request->input('urgente', self::URGENTE_TODAS));
        if (! self::urgenteValido($urgente)) {
            $urgente = self::URGENTE_TODAS;
        }

        $contratacion = trim((string) $request->input('contratacion', self::CONTRATACION_TODAS));
        if (! self::contratacionValida($contratacion)) {
            $contratacion = self::CONTRATACION_TODAS;
        }

        [$requisicionDesde, $requisicionHasta] = RequisicionReporteCriteriosSupport::normalizarRangoNumeros(
            trim((string) $request->input('requisicion_desde', '')),
            trim((string) $request->input('requisicion_hasta', '')),
        );

        return [
            'empresa_ids' => $empresaIds,
            'fecha_desde' => self::fechaOpcional($request->input('fecha_desde')),
            'fecha_hasta' => self::fechaOpcional($request->input('fecha_hasta')),
            'requisicion_desde' => $requisicionDesde,
            'requisicion_hasta' => $requisicionHasta,
            'usuarios' => trim((string) $request->input('usuarios', '')),
            'centrocostos_codigo' => trim((string) ($request->input('centrocostos_codigo', $request->input('centrocostos', '')))),
            'estado_requisicion' => $estado,
            'agrupacion' => $agrupacion,
            'modo_listado' => $modoListado,
            'urgente' => $urgente,
            'contratacion' => $contratacion,
        ];
    }

    /** @return array<string, mixed> */
    public static function paraQueryString(array $filtros): array
    {
        $query = array_filter([
            'fecha_desde' => $filtros['fecha_desde'] ?? null,
            'fecha_hasta' => $filtros['fecha_hasta'] ?? null,
            'requisicion_desde' => ($filtros['requisicion_desde'] ?? '') !== ''
                ? ($filtros['requisicion_desde'] ?? null)
                : null,
            'requisicion_hasta' => ($filtros['requisicion_hasta'] ?? '') !== ''
                ? ($filtros['requisicion_hasta'] ?? null)
                : null,
            'usuarios' => ($filtros['usuarios'] ?? '') !== ''
                ? ($filtros['usuarios'] ?? null)
                : null,
            'centrocostos_codigo' => ($filtros['centrocostos_codigo'] ?? '') !== ''
                ? ($filtros['centrocostos_codigo'] ?? null)
                : null,
            'estado_requisicion' => ($filtros['estado_requisicion'] ?? self::ESTADO_EN_COMPRAS) !== self::ESTADO_EN_COMPRAS
                ? ($filtros['estado_requisicion'] ?? null)
                : null,
            'agrupacion' => ($filtros['agrupacion'] ?? self::AGRUPACION_USUARIO) !== self::AGRUPACION_USUARIO
                ? ($filtros['agrupacion'] ?? null)
                : null,
            'modo_listado' => ($filtros['modo_listado'] ?? self::MODO_MOVIMIENTOS) !== self::MODO_MOVIMIENTOS
                ? ($filtros['modo_listado'] ?? null)
                : null,
            'urgente' => ($filtros['urgente'] ?? self::URGENTE_TODAS) !== self::URGENTE_TODAS
                ? ($filtros['urgente'] ?? null)
                : null,
            'contratacion' => ($filtros['contratacion'] ?? self::CONTRATACION_TODAS) !== self::CONTRATACION_TODAS
                ? ($filtros['contratacion'] ?? null)
                : null,
            'consultar' => 1,
        ], fn ($v) => $v !== null && $v !== '');

        if (($filtros['empresa_ids'] ?? []) !== []) {
            $query['empresa_ids'] = array_values(array_map(
                'intval',
                $filtros['empresa_ids'],
            ));
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
            'fecha_desde' => $hoy->copy()->startOfMonth()->format('Y-m-d'),
            'fecha_hasta' => $hoy->copy()->endOfMonth()->format('Y-m-d'),
            'requisicion_desde' => '',
            'requisicion_hasta' => '',
            'usuarios' => '',
            'centrocostos_codigo' => '',
            'estado_requisicion' => self::ESTADO_EN_COMPRAS,
            'agrupacion' => self::AGRUPACION_USUARIO,
            'modo_listado' => self::MODO_MOVIMIENTOS,
            'urgente' => self::URGENTE_TODAS,
            'contratacion' => self::CONTRATACION_TODAS,
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
            self::ESTADO_PENDIENTE => 'Listando req. pendientes',
            self::ESTADO_CUMPLIDO => 'Listando req. cumplidos',
            self::ESTADO_EN_COMPRAS => 'Listando req. en compras',
            self::ESTADO_GENERO_OC => 'Listando req. con OC generada',
            self::ESTADO_ARBOL => 'Listando req. en árbol de aprobación',
            self::ESTADO_SUSPENDIDO => 'Listando req. suspendidos',
            self::ESTADO_APROBADA => 'Listando req. aprobadas',
            default => 'Listando todos los movimientos',
        };
    }

    public static function aplicarEstadoRequisicion(Builder $query, string $estado): void
    {
        switch ($estado) {
            case self::ESTADO_PENDIENTE:
                $query->where('r.estado', 'PENDIENTE');
                break;
            case self::ESTADO_CUMPLIDO:
                $query->where(function (Builder $sub) {
                    $sub->where('r.estado', 'CUMPLIDA')
                        ->orWhereRaw('(ra.cantidad - COALESCE(ent.cantidad_entregada, 0)) <= 0.009');
                });
                break;
            case self::ESTADO_EN_COMPRAS:
                $query->where('r.estado', 'EN COMPRAS');
                break;
            case self::ESTADO_GENERO_OC:
                $query->where('r.estado', 'GENERO ORDEN COMPRA');
                break;
            case self::ESTADO_ARBOL:
                $query->where('r.estado', 'EN ARBOL APROBACION');
                break;
            case self::ESTADO_SUSPENDIDO:
                $query->where('r.estado', 'SUSPENDIDA');
                break;
            case self::ESTADO_APROBADA:
                $query->where('r.estado', 'APROBADA');
                break;
        }
    }

    public static function aplicarUrgente(Builder $query, string $urgente): void
    {
        switch ($urgente) {
            case self::URGENTE_SI:
                $query->where('r.tratamiento', 'U');
                break;
            case self::URGENTE_NO:
                $query->where('r.tratamiento', '!=', 'U');
                break;
        }
    }

    public static function aplicarContratacion(Builder $query, string $contratacion): void
    {
        switch ($contratacion) {
            case self::CONTRATACION_DIRECTAS:
                $query->where('r.contrataciondirecta', 'S');
                break;
            case self::CONTRATACION_NO_DIRECTAS:
                $query->where('r.contrataciondirecta', '!=', 'S');
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

    private static function urgenteValido(string $urgente): bool
    {
        foreach (self::OPCIONES_URGENTE as $opcion) {
            if ($opcion['valor'] === $urgente) {
                return true;
            }
        }

        return false;
    }

    private static function contratacionValida(string $contratacion): bool
    {
        foreach (self::OPCIONES_CONTRATACION as $opcion) {
            if ($opcion['valor'] === $contratacion) {
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
