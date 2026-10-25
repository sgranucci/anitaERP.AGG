<?php

namespace App\Support\Caja;

use App\Models\Caja\TesoreriaCrownDeposito;
use App\Support\Listado\CoincidenciaFlexibleTexto;
use App\Support\Listado\FiltrosListadoRequest;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class TesoreriaCrownDepositoListadoFiltros
{
    /**
     * @return array{valor: string, empresa_id: int, sala_id: int, tipo_deposito_id: int}
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
            'empresa_id' => (int) $request->input('empresa_id', 0),
            'sala_id' => (int) $request->input('sala_id', 0),
            'tipo_deposito_id' => (int) $request->input('tipo_deposito_id', 0),
        ];
    }

    /**
     * @return array{valor: string, empresa_id: int, sala_id: int, tipo_deposito_id: int}
     */
    public static function filtrosVacios(): array
    {
        return [
            'valor' => '',
            'empresa_id' => 0,
            'sala_id' => 0,
            'tipo_deposito_id' => 0,
        ];
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    public static function tieneCriteriosAplicados(array $filtros): bool
    {
        if ((int) ($filtros['empresa_id'] ?? 0) > 0) {
            return true;
        }
        if ((int) ($filtros['tipo_deposito_id'] ?? 0) > 0) {
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
        foreach (['empresa_id', 'sala_id', 'tipo_deposito_id'] as $clave) {
            if ((int) ($filtros[$clave] ?? 0) > 0) {
                $params[$clave] = (int) $filtros[$clave];
            }
        }

        return $params;
    }

    /**
     * @param  Builder<TesoreriaCrownDeposito>  $query
     * @param  array<string, mixed>  $filtros
     */
    public static function aplicar(Builder $query, array $filtros): void
    {
        if (array_key_exists('empresa_ids', $filtros)) {
            $ids = array_values(array_filter(
                array_map('intval', (array) $filtros['empresa_ids']),
                fn (int $id) => $id > 0
            ));
            if ($ids === []) {
                $query->whereRaw('1 = 0');
            } else {
                $query->whereIn('tesoreria_crown_deposito.empresa_id', $ids);
            }
        }

        $empresaId = (int) ($filtros['empresa_id'] ?? 0);
        if ($empresaId > 0) {
            $query->where('tesoreria_crown_deposito.empresa_id', $empresaId);
        }

        $salaId = (int) ($filtros['sala_id'] ?? 0);
        if ($salaId > 0) {
            $query->where('tesoreria_crown_deposito.sala_id', $salaId);
        } else {
            $query->whereRaw('1 = 0');
        }

        $tipoId = (int) ($filtros['tipo_deposito_id'] ?? 0);
        if ($tipoId > 0) {
            $query->where('tesoreria_crown_deposito.tipo_deposito_id', $tipoId);
        }

        $valor = trim((string) ($filtros['valor'] ?? ''));
        if ($valor === '') {
            return;
        }

        $like = '%'.self::escapeLike($valor).'%';
        $query->where(function ($q) use ($valor, $like) {
            $q->where('tesoreria_crown_deposito.codigo', 'like', $like)
                ->orWhere('tesoreria_crown_deposito.nombre', 'like', $like)
                ->orWhereHas('sala', function ($sala) use ($like) {
                    $sala->where('codigo', 'like', $like)->orWhere('nombre', 'like', $like);
                })
                ->orWhereHas('tipoDeposito', function ($tipo) use ($like) {
                    $tipo->where('codigo', 'like', $like)->orWhere('nombre', 'like', $like);
                })
                ->orWhereHas('empresa', function ($empresa) use ($like) {
                    $empresa->where('nombre', 'like', $like);
                });

            CoincidenciaFlexibleTexto::aplicar(
                $q,
                'tesoreria_crown_deposito.nombre',
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
