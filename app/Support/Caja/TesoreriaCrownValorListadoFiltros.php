<?php

namespace App\Support\Caja;

use App\Models\Caja\TesoreriaCrownValor;
use App\Support\Listado\CoincidenciaFlexibleTexto;
use App\Support\Listado\FiltrosListadoRequest;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class TesoreriaCrownValorListadoFiltros
{
    /**
     * @return array{valor: string, tipo_valor_id: int, sala_id: int}
     */
    public static function resolverDesdeRequest(Request $request, ?string $busquedaRuta = null): array
    {
        $salaId = (int) $request->input('sala_id', 0);
        if (FiltrosListadoRequest::solicitudLimpiaFiltros($request)) {
            $vacios = self::filtrosVacios();
            $vacios['sala_id'] = $salaId;

            return $vacios;
        }

        return [
            'valor' => FiltrosListadoRequest::valorBusqueda($request, $busquedaRuta),
            'tipo_valor_id' => (int) $request->input('tipo_valor_id', 0),
            'sala_id' => $salaId,
        ];
    }

    /**
     * @return array{valor: string, tipo_valor_id: int, sala_id: int}
     */
    public static function filtrosVacios(): array
    {
        return [
            'valor' => '',
            'tipo_valor_id' => 0,
            'sala_id' => 0,
        ];
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    public static function tieneCriteriosAplicados(array $filtros): bool
    {
        if ((int) ($filtros['tipo_valor_id'] ?? 0) > 0) {
            return true;
        }

        return trim((string) ($filtros['valor'] ?? '')) !== '';
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
        if ((int) ($filtros['tipo_valor_id'] ?? 0) > 0) {
            $params['tipo_valor_id'] = (int) $filtros['tipo_valor_id'];
        }
        if ((int) ($filtros['sala_id'] ?? 0) > 0) {
            $params['sala_id'] = (int) $filtros['sala_id'];
        }

        return $params;
    }

    /**
     * @param  Builder<TesoreriaCrownValor>  $query
     * @param  array<string, mixed>  $filtros
     */
    public static function aplicar(Builder $query, array $filtros): void
    {
        $salaId = (int) ($filtros['sala_id'] ?? 0);
        if ($salaId > 0) {
            $query->where('tesoreria_crown_valor.sala_id', $salaId);
        } else {
            $query->whereRaw('1 = 0');
        }

        $tipoId = (int) ($filtros['tipo_valor_id'] ?? 0);
        if ($tipoId > 0) {
            $query->where('tesoreria_crown_valor.tipo_valor_id', $tipoId);
        }

        $valor = trim((string) ($filtros['valor'] ?? ''));
        if ($valor === '') {
            return;
        }

        $like = '%'.self::escapeLike($valor).'%';
        $query->where(function ($q) use ($valor, $like) {
            $q->where('tesoreria_crown_valor.serie', 'like', $like)
                ->orWhere('tesoreria_crown_valor.descripcion', 'like', $like)
                ->orWhereHas('tipoValor', function ($tipo) use ($like) {
                    $tipo->where('codigo', 'like', $like)
                        ->orWhere('nombre', 'like', $like);
                });

            $numero = str_replace(',', '.', $valor);
            if (is_numeric($numero)) {
                $q->orWhere('tesoreria_crown_valor.denominacion', (float) $numero);
            }

            CoincidenciaFlexibleTexto::aplicar(
                $q,
                'tesoreria_crown_valor.serie',
                $valor,
                true,
                CoincidenciaFlexibleTexto::LONGITUD_MINIMA_DEFAULT
            );
            CoincidenciaFlexibleTexto::aplicar(
                $q,
                'tesoreria_crown_valor.descripcion',
                $valor,
                true,
                CoincidenciaFlexibleTexto::LONGITUD_MINIMA_DEFAULT
            );
        });
    }

    private static function escapeLike(string $value): string
    {
        return addcslashes($value, '%_\\');
    }
}
