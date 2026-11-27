<?php

namespace App\Support\Compras;

use Carbon\Carbon;
use Illuminate\Http\Request;

/**
 * Filtros del tablero de compras (réplica del Power BI de Compras).
 *
 * Los filtros valen para todas las solapas: período (año / mes / día), empresa,
 * comprador, proveedor, estado de la OC, vencimiento y si la OC tiene COM.
 */
final class ComprasTableroOcFiltros
{
    public const ESTADO_TODOS = 'todos';

    public const ESTADO_PENDIENTE = 'pendiente';

    public const ESTADO_PARCIAL = 'parcial';

    public const ESTADO_FINALIZADA = 'finalizada';

    public const VENCIMIENTO_TODOS = 'todos';

    public const VENCIMIENTO_A_TIEMPO = 'a_tiempo';

    public const VENCIMIENTO_VENCIDA = 'vencida';

    public const COM_TODAS = 'todas';

    public const COM_CON = 'con';

    public const COM_SIN = 'sin';

    public const TAB_COMPRAS = 'compras';

    public const TAB_DETALLE = 'detalle';

    public const TAB_COMPRADORES = 'compradores';

    public const TAB_PROVEEDORES = 'proveedores';

    public const TAB_PRECIOS = 'precios';

    /** @var list<array{valor: string, etiqueta: string}> */
    public const OPCIONES_ESTADO = [
        ['valor' => self::ESTADO_TODOS, 'etiqueta' => 'Todas'],
        ['valor' => self::ESTADO_PENDIENTE, 'etiqueta' => 'Pendiente'],
        ['valor' => self::ESTADO_PARCIAL, 'etiqueta' => 'Entrega parcial'],
        ['valor' => self::ESTADO_FINALIZADA, 'etiqueta' => 'Finalizada'],
    ];

    /** @var list<array{valor: string, etiqueta: string}> */
    public const OPCIONES_VENCIMIENTO = [
        ['valor' => self::VENCIMIENTO_TODOS, 'etiqueta' => 'Todos'],
        ['valor' => self::VENCIMIENTO_A_TIEMPO, 'etiqueta' => 'A tiempo'],
        ['valor' => self::VENCIMIENTO_VENCIDA, 'etiqueta' => 'Vencida'],
    ];

    /** @var list<array{valor: string, etiqueta: string}> */
    public const OPCIONES_COM = [
        ['valor' => self::COM_TODAS, 'etiqueta' => 'Todas'],
        ['valor' => self::COM_CON, 'etiqueta' => 'Con COM'],
        ['valor' => self::COM_SIN, 'etiqueta' => 'Sin COM'],
    ];

    /** @var array<int, string> número de mes => nombre */
    public const MESES = [
        1 => 'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio',
        'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre',
    ];

    /**
     * @return array<string, mixed>
     */
    public static function resolverDesdeRequest(Request $request): array
    {
        $hoy = Carbon::now();

        $anio = (int) $request->input('anio', $hoy->year);
        if ($anio < 2000 || $anio > $hoy->year + 1) {
            $anio = $hoy->year;
        }

        $mes = (int) $request->input('mes', 0);
        if ($mes < 0 || $mes > 12) {
            $mes = 0;
        }

        $dia = (int) $request->input('dia', 0);
        if ($mes === 0 || $dia < 0 || $dia > Carbon::create($anio, max(1, $mes), 1)->daysInMonth) {
            $dia = 0;
        }

        $empresaIds = collect($request->input('empresa_ids', []))
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => $id > 0)
            ->unique()
            ->values()
            ->all();

        $estado = trim((string) $request->input('estado', self::ESTADO_TODOS));
        if (! in_array($estado, array_column(self::OPCIONES_ESTADO, 'valor'), true)) {
            $estado = self::ESTADO_TODOS;
        }

        $vencimiento = trim((string) $request->input('vencimiento', self::VENCIMIENTO_TODOS));
        if (! in_array($vencimiento, array_column(self::OPCIONES_VENCIMIENTO, 'valor'), true)) {
            $vencimiento = self::VENCIMIENTO_TODOS;
        }

        $com = trim((string) $request->input('com', self::COM_TODAS));
        if (! in_array($com, array_column(self::OPCIONES_COM, 'valor'), true)) {
            $com = self::COM_TODAS;
        }

        $tab = trim((string) $request->input('tab', self::TAB_COMPRAS));
        if (! in_array($tab, self::tabs(), true)) {
            $tab = self::TAB_COMPRAS;
        }

        return [
            'anio' => $anio,
            'mes' => $mes,
            'dia' => $dia,
            'empresa_ids' => $empresaIds,
            'usuarios' => trim((string) $request->input('usuarios', '')),
            'proveedores' => trim((string) $request->input('proveedores', '')),
            'estado' => $estado,
            'vencimiento' => $vencimiento,
            'com' => $com,
            'articulo_id' => max(0, (int) $request->input('articulo_id', 0)),
            'buscar_articulo' => trim((string) $request->input('buscar_articulo', '')),
            'tab' => $tab,
        ];
    }

    /** @return list<string> */
    public static function tabs(): array
    {
        return [
            self::TAB_COMPRAS,
            self::TAB_DETALLE,
            self::TAB_COMPRADORES,
            self::TAB_PROVEEDORES,
            self::TAB_PRECIOS,
        ];
    }

    /**
     * Rango de fechas (Y-m-d) del período elegido.
     *
     * @param  array<string, mixed>  $filtros
     * @return array{0: string, 1: string}
     */
    public static function rangoFechas(array $filtros): array
    {
        $anio = (int) $filtros['anio'];
        $mes = (int) $filtros['mes'];
        $dia = (int) $filtros['dia'];

        if ($mes === 0) {
            return [sprintf('%04d-01-01', $anio), sprintf('%04d-12-31', $anio)];
        }

        $primero = Carbon::create($anio, $mes, 1);
        if ($dia === 0) {
            return [$primero->format('Y-m-d'), $primero->copy()->endOfMonth()->format('Y-m-d')];
        }

        $fecha = Carbon::create($anio, $mes, $dia)->format('Y-m-d');

        return [$fecha, $fecha];
    }

    /** @param  array<string, mixed>  $filtros */
    public static function textoPeriodo(array $filtros): string
    {
        $anio = (int) $filtros['anio'];
        $mes = (int) $filtros['mes'];
        $dia = (int) $filtros['dia'];

        if ($mes === 0) {
            return 'Año '.$anio;
        }
        if ($dia === 0) {
            return self::MESES[$mes].' '.$anio;
        }

        return sprintf('%02d/%02d/%04d', $dia, $mes, $anio);
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return array<string, mixed>
     */
    public static function paraQueryString(array $filtros): array
    {
        $query = [
            'anio' => (int) $filtros['anio'],
            'consultar' => 1,
        ];

        foreach (['mes', 'dia'] as $clave) {
            if ((int) ($filtros[$clave] ?? 0) > 0) {
                $query[$clave] = (int) $filtros[$clave];
            }
        }

        foreach (['usuarios', 'proveedores', 'buscar_articulo'] as $clave) {
            if (($filtros[$clave] ?? '') !== '') {
                $query[$clave] = $filtros[$clave];
            }
        }

        if ((int) ($filtros['articulo_id'] ?? 0) > 0) {
            $query['articulo_id'] = (int) $filtros['articulo_id'];
        }

        if (($filtros['estado'] ?? self::ESTADO_TODOS) !== self::ESTADO_TODOS) {
            $query['estado'] = $filtros['estado'];
        }
        if (($filtros['vencimiento'] ?? self::VENCIMIENTO_TODOS) !== self::VENCIMIENTO_TODOS) {
            $query['vencimiento'] = $filtros['vencimiento'];
        }
        if (($filtros['com'] ?? self::COM_TODAS) !== self::COM_TODAS) {
            $query['com'] = $filtros['com'];
        }

        if (($filtros['empresa_ids'] ?? []) !== []) {
            $query['empresa_ids'] = array_values(array_map('intval', $filtros['empresa_ids']));
        }

        return $query;
    }

    /** @param  array<string, mixed>  $filtros */
    public static function tieneCriteriosAplicados(array $filtros): bool
    {
        return ($filtros['empresa_ids'] ?? []) !== [];
    }

    /** @return list<int> */
    public static function aniosDisponibles(): array
    {
        $actual = (int) date('Y');
        $anios = [];
        for ($a = $actual + 1; $a >= $actual - 8; $a--) {
            $anios[] = $a;
        }

        return $anios;
    }
}
