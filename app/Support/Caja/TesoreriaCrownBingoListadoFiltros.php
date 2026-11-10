<?php

namespace App\Support\Caja;

use App\Support\Listado\FiltrosListadoRequest;
use DateTime;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class TesoreriaCrownBingoListadoFiltros
{
    /**
     * @return array<string, mixed>
     */
    public static function resolverDesdeRequest(Request $request, ?string $busquedaRuta = null): array
    {
        $salaId = (int) $request->input('sala_id', 0);
        if (FiltrosListadoRequest::solicitudLimpiaFiltros($request)) {
            return [
                'valor' => '',
                'sala_id' => $salaId,
                'fecha_desde' => '',
                'fecha_hasta' => '',
            ];
        }

        return [
            'valor' => FiltrosListadoRequest::valorBusqueda($request, $busquedaRuta),
            'sala_id' => $salaId,
            'fecha_desde' => self::fecha($request->input('fecha_desde')),
            'fecha_hasta' => self::fecha($request->input('fecha_hasta')),
        ];
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    public static function tieneCriteriosAplicados(array $filtros): bool
    {
        return trim((string) ($filtros['valor'] ?? '')) !== ''
            || trim((string) ($filtros['fecha_desde'] ?? '')) !== ''
            || trim((string) ($filtros['fecha_hasta'] ?? '')) !== '';
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return array<string, string|int>
     */
    public static function paraQueryString(array $filtros): array
    {
        $params = [];
        if (trim((string) ($filtros['valor'] ?? '')) !== '') {
            $params['filtro_valor'] = $filtros['valor'];
        }
        if ((int) ($filtros['sala_id'] ?? 0) > 0) {
            $params['sala_id'] = (int) $filtros['sala_id'];
        }
        foreach (['fecha_desde', 'fecha_hasta'] as $clave) {
            $valor = trim((string) ($filtros[$clave] ?? ''));
            if ($valor !== '') {
                $params[$clave] = $valor;
            }
        }

        return $params;
    }

    /**
     * @param  Builder<\App\Models\Caja\TesoreriaCrownBingo>  $query
     * @param  array<string, mixed>  $filtros
     */
    public static function aplicar(Builder $query, array $filtros): void
    {
        $salaId = (int) ($filtros['sala_id'] ?? 0);
        if ($salaId > 0) {
            $query->where('sala_id', $salaId);
        }
        $desde = trim((string) ($filtros['fecha_desde'] ?? ''));
        if ($desde !== '') {
            $query->whereDate('fecha', '>=', $desde);
        }
        $hasta = trim((string) ($filtros['fecha_hasta'] ?? ''));
        if ($hasta !== '') {
            $query->whereDate('fecha', '<=', $hasta);
        }
        $valor = trim((string) ($filtros['valor'] ?? ''));
        if ($valor !== '') {
            $like = '%'.$valor.'%';
            $query->where(function ($q) use ($like, $valor) {
                $q->where('fecha', 'like', $like);
                if (is_numeric(str_replace(['.', ','], ['', '.'], $valor))) {
                    $numero = (float) str_replace(',', '.', $valor);
                    $q->orWhere('tot_carton', $numero)
                        ->orWhere('tot_premio', $numero)
                        ->orWhere('tot_rse', $numero)
                        ->orWhere('resultado', $numero);
                }
            });
        }
    }

    private static function fecha(mixed $valor): string
    {
        $texto = trim((string) $valor);
        if ($texto === '') {
            return '';
        }
        $dt = DateTime::createFromFormat('Y-m-d', $texto)
            ?: DateTime::createFromFormat('d/m/Y', $texto);

        return $dt ? $dt->format('Y-m-d') : '';
    }
}
