<?php

namespace App\Support\Ventas;

use App\Support\Listado\FiltrosListadoRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Filtros del listado de cierres de turno gastronomía (index / exportaciones).
 */
class GastronomiaCierresTurnoListadoFiltros
{
    public const MODO_TODOS = 'todos';

    public const MODO_CAMPO = 'campo';

    /** @var array<string, array{prop: string, type: string, label: string}> */
    public const CAMPOS = [
        'referencia' => ['prop' => 'referencia', 'type' => 'texto', 'label' => 'Referencia'],
        'tipo' => ['prop' => 'tipo_etiqueta', 'type' => 'texto', 'label' => 'Tipo'],
        'empresa' => ['prop' => 'nombreempresa', 'type' => 'texto', 'label' => 'Empresa'],
        'pc' => ['prop' => 'identificador_pc', 'type' => 'texto', 'label' => 'PC'],
        'puntoventa' => ['prop' => 'puntoventa_etiqueta', 'type' => 'texto', 'label' => 'Punto de venta'],
        'turno' => ['prop' => 'turno_nombre', 'type' => 'texto', 'label' => 'Turno'],
        'jornada' => ['prop' => 'fecha_jornada', 'type' => 'texto', 'label' => 'Jornada'],
        'usuario' => ['prop' => 'usuario', 'type' => 'texto', 'label' => 'Usuario'],
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

        $campo = (string) $request->input('filtro_campo', 'referencia');
        if (! isset(self::CAMPOS[$campo])) {
            $campo = 'referencia';
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
            'valor_hasta' => '',
            'busqueda' => $valor,
            'busqueda_rapida' => $busquedaRapida,
            'empresa_id' => (int) $request->input('empresa_id', 0),
            'identificador_pc' => trim((string) $request->input('identificador_pc', '')),
            'fecha_desde' => trim((string) $request->input('fecha_desde', '')),
            'fecha_hasta' => trim((string) $request->input('fecha_hasta', '')),
            'tipo' => trim((string) $request->input('tipo', '')),
            'todas_terminales' => $request->boolean('todas_terminales'),
        ];
    }

    /**
     * Aplica alcance por terminal: operadores solo ven su PC; encargado/supervisor pueden incluir todas.
     *
     * @param  array<string, mixed>  $filtros
     * @return array<string, mixed>
     */
    public static function aplicarAlcanceTerminal(array $filtros, string $pcSesion, bool $puedeVerTodasTerminales): array
    {
        $pcSesion = trim($pcSesion);

        if ($puedeVerTodasTerminales && ! empty($filtros['todas_terminales'])) {
            $filtros['identificador_pc'] = '';

            return $filtros;
        }

        $pcFiltro = trim((string) ($filtros['identificador_pc'] ?? ''));
        if ($pcFiltro === '') {
            $filtros['identificador_pc'] = $pcSesion;
        }

        $filtros['todas_terminales'] = false;

        return $filtros;
    }

    public static function tieneCriteriosAplicados(array $filtros): bool
    {
        if ((int) ($filtros['empresa_id'] ?? 0) > 0) {
            return true;
        }
        if (trim((string) ($filtros['identificador_pc'] ?? '')) !== '') {
            return true;
        }
        if (trim((string) ($filtros['tipo'] ?? '')) !== '') {
            return true;
        }
        if (! empty($filtros['todas_terminales'])) {
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
            'campo' => 'referencia',
            'operador' => 'contiene',
            'valor' => '',
            'valor_hasta' => '',
            'busqueda' => '',
            'empresa_id' => 0,
            'identificador_pc' => '',
            'fecha_desde' => '',
            'fecha_hasta' => '',
            'tipo' => '',
            'todas_terminales' => false,
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
            $params['filtro_campo'] = $filtros['campo'] ?? 'referencia';
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
        if (! empty($filtros['identificador_pc'])) {
            $params['identificador_pc'] = $filtros['identificador_pc'];
        }
        if (! empty($filtros['fecha_desde'])) {
            $params['fecha_desde'] = $filtros['fecha_desde'];
        }
        if (! empty($filtros['fecha_hasta'])) {
            $params['fecha_hasta'] = $filtros['fecha_hasta'];
        }
        if (! empty($filtros['tipo'])) {
            $params['tipo'] = $filtros['tipo'];
        }
        if (! empty($filtros['todas_terminales'])) {
            $params['todas_terminales'] = '1';
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
     * @param  Collection<int, object>  $filas
     * @return Collection<int, object>
     */
    public static function filtrarFilas(Collection $filas, array $filtros): Collection
    {
        $valor = trim((string) ($filtros['valor'] ?? ''));
        $operador = (string) ($filtros['operador'] ?? 'contiene');
        $modo = (string) ($filtros['modo'] ?? self::MODO_TODOS);

        if ($valor === '' && $operador !== 'vacio') {
            return $filas;
        }

        return $filas->filter(function ($fila) use ($filtros, $valor, $operador, $modo) {
            if ($modo === self::MODO_CAMPO) {
                $campo = (string) ($filtros['campo'] ?? 'referencia');
                $prop = self::CAMPOS[$campo]['prop'] ?? 'referencia';

                return self::coincidePropiedad($fila, $prop, $operador, $valor);
            }

            foreach (self::CAMPOS as $meta) {
                if (self::coincidePropiedad($fila, $meta['prop'], $operador, $valor)) {
                    return true;
                }
            }

            return false;
        })->values();
    }

    private static function coincidePropiedad(object $fila, string $prop, string $operador, string $valor): bool
    {
        $texto = trim((string) ($fila->{$prop} ?? ''));

        if ($operador === 'vacio') {
            return $texto === '';
        }

        if ($valor === '') {
            return true;
        }

        $hay = Str::lower($texto);
        $bus = Str::lower($valor);

        return match ($operador) {
            'empieza' => str_starts_with($hay, $bus),
            'termina' => str_ends_with($hay, $bus),
            'igual' => $hay === $bus,
            'distinto' => $hay !== $bus,
            default => str_contains($hay, $bus),
        };
    }

    private static function normalizarOperador(string $operador): string
    {
        if (in_array($operador, array_keys(self::OPERADORES_TEXTO), true)) {
            return $operador;
        }

        return 'contiene';
    }

    /**
     * @return array<string, string>
     */
    public static function operadoresParaCampo(string $campoKey): array
    {
        return self::OPERADORES_TEXTO;
    }
}
