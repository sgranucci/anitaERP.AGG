<?php

namespace App\Support\Caja;

use App\Models\Caja\TesoreriaCrownCotizacion;
use App\Support\Listado\FiltrosListadoRequest;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class TesoreriaCrownCotizacionListadoFiltros
{
    public const MODO_TODOS = 'todos';

    public const MODO_CAMPO = 'campo';

    /** @var array<string, array{column: string, type: string, label: string}> */
    public const CAMPOS = [
        'jornada' => ['column' => 'tesoreria_crown_cotizacion.jornada', 'type' => 'fecha', 'label' => 'Jornada'],
        'compra' => ['column' => 'tesoreria_crown_cotizacion.compra', 'type' => 'decimal', 'label' => 'Compra'],
        'venta' => ['column' => 'tesoreria_crown_cotizacion.venta', 'type' => 'decimal', 'label' => 'Venta'],
    ];

    /** @var array<string, string> */
    public const OPERADORES_FECHA = [
        'igual' => 'Es el día',
        'contiene' => 'Contiene (año o parte)',
        'mayor' => 'Posterior a',
        'menor' => 'Anterior a',
    ];

    /** @var array<string, string> */
    public const OPERADORES_DECIMAL = [
        'igual' => 'Igual a',
        'mayor' => 'Mayor que',
        'menor' => 'Menor que',
    ];

    /**
     * @return array{valor: string, sala_id: int, modo: string, campo: string, operador: string}
     */
    public static function resolverDesdeRequest(Request $request, ?string $busquedaRuta = null): array
    {
        $salaId = (int) $request->input('sala_id', 0);
        if (FiltrosListadoRequest::solicitudLimpiaFiltros($request)) {
            $vacios = self::filtrosVacios();
            $vacios['sala_id'] = $salaId;

            return $vacios;
        }

        $modo = (string) $request->input('filtro_modo', self::MODO_TODOS);
        if (! in_array($modo, [self::MODO_TODOS, self::MODO_CAMPO], true) || $request->boolean('filtro_busqueda_rapida')) {
            $modo = self::MODO_TODOS;
        }
        $campo = (string) $request->input('filtro_campo', 'jornada');
        if (! isset(self::CAMPOS[$campo])) {
            $campo = 'jornada';
        }
        $operador = (string) $request->input('filtro_operador', 'contiene');

        return [
            'valor' => FiltrosListadoRequest::valorBusqueda($request, $busquedaRuta),
            'sala_id' => $salaId,
            'modo' => $modo,
            'campo' => $campo,
            'operador' => $operador,
        ];
    }

    /**
     * @return array{valor: string, sala_id: int, modo: string, campo: string, operador: string}
     */
    public static function filtrosVacios(): array
    {
        return [
            'valor' => '',
            'sala_id' => 0,
            'modo' => self::MODO_TODOS,
            'campo' => 'jornada',
            'operador' => 'contiene',
        ];
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    public static function tieneCriteriosAplicados(array $filtros): bool
    {
        return trim((string) ($filtros['valor'] ?? '')) !== '';
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return array<string, string|int>
     */
    public static function paraQueryString(array $filtros): array
    {
        $params = [];
        if ((int) ($filtros['sala_id'] ?? 0) > 0) {
            $params['sala_id'] = (int) $filtros['sala_id'];
        }
        if (trim((string) ($filtros['valor'] ?? '')) !== '') {
            $params['filtro_valor'] = $filtros['valor'];
        }
        if (($filtros['modo'] ?? self::MODO_TODOS) === self::MODO_CAMPO) {
            $params['filtro_modo'] = self::MODO_CAMPO;
            $params['filtro_campo'] = (string) ($filtros['campo'] ?? 'jornada');
            $params['filtro_operador'] = (string) ($filtros['operador'] ?? 'igual');
        }

        return $params;
    }

    /**
     * @return array<string, string>
     */
    public static function operadoresParaCampo(string $campo): array
    {
        $tipo = self::CAMPOS[$campo]['type'] ?? 'fecha';

        return $tipo === 'decimal' ? self::OPERADORES_DECIMAL : self::OPERADORES_FECHA;
    }

    /**
     * @param  Builder<TesoreriaCrownCotizacion>  $query
     * @param  array<string, mixed>  $filtros
     */
    public static function aplicar(Builder $query, array $filtros): void
    {
        $salaId = (int) ($filtros['sala_id'] ?? 0);
        if ($salaId > 0) {
            $query->where('tesoreria_crown_cotizacion.sala_id', $salaId);
        } else {
            $query->whereRaw('1 = 0');
        }

        $valor = trim((string) ($filtros['valor'] ?? ''));
        if ($valor === '') {
            return;
        }

        if (($filtros['modo'] ?? self::MODO_TODOS) === self::MODO_CAMPO) {
            $campo = (string) ($filtros['campo'] ?? 'jornada');
            $def = self::CAMPOS[$campo] ?? self::CAMPOS['jornada'];
            if ($def['type'] === 'decimal') {
                self::aplicarDecimal($query, $def['column'], (string) ($filtros['operador'] ?? 'igual'), $valor);

                return;
            }
            self::aplicarFecha($query, $def['column'], (string) ($filtros['operador'] ?? 'contiene'), $valor);

            return;
        }

        $fecha = self::normalizarFecha($valor);
        $query->where(function ($q) use ($valor, $fecha) {
            if ($fecha !== null) {
                $q->orWhereDate('tesoreria_crown_cotizacion.jornada', $fecha);
            }
            if (preg_match('/^\d{4}$/', $valor)) {
                $q->orWhere('tesoreria_crown_cotizacion.jornada', 'like', $valor.'-%');
            }
            if (is_numeric(str_replace(',', '.', $valor))) {
                $numero = (float) str_replace(',', '.', $valor);
                $q->orWhere('tesoreria_crown_cotizacion.compra', $numero)
                    ->orWhere('tesoreria_crown_cotizacion.venta', $numero);
            }
        });
    }

    /**
     * @param  Builder<TesoreriaCrownCotizacion>  $query
     */
    private static function aplicarFecha(Builder $query, string $column, string $operador, string $valor): void
    {
        $fecha = self::normalizarFecha($valor);
        if ($operador === 'igual' && $fecha !== null) {
            $query->whereDate($column, $fecha);

            return;
        }
        if ($operador === 'mayor' && $fecha !== null) {
            $query->whereDate($column, '>', $fecha);

            return;
        }
        if ($operador === 'menor' && $fecha !== null) {
            $query->whereDate($column, '<', $fecha);

            return;
        }
        if (preg_match('/^\d{4}$/', $valor)) {
            $query->where($column, 'like', $valor.'-%');

            return;
        }
        if ($fecha !== null) {
            $query->whereDate($column, $fecha);
        }
    }

    /**
     * @param  Builder<TesoreriaCrownCotizacion>  $query
     */
    private static function aplicarDecimal(Builder $query, string $column, string $operador, string $valor): void
    {
        $numero = (float) str_replace(',', '.', $valor);
        $signo = match ($operador) {
            'mayor' => '>',
            'menor' => '<',
            default => '=',
        };
        $query->where($column, $signo, $numero);
    }

    private static function normalizarFecha(string $valor): ?string
    {
        $valor = trim($valor);
        foreach (['Y-m-d', 'd/m/Y', 'd-m-Y'] as $formato) {
            try {
                $fecha = Carbon::createFromFormat($formato, $valor);
                if ($fecha instanceof Carbon) {
                    return $fecha->format('Y-m-d');
                }
            } catch (\Throwable) {
                continue;
            }
        }

        return null;
    }
}
