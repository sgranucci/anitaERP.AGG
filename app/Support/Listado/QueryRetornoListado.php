<?php

namespace App\Support\Listado;

use Illuminate\Http\Request;

/**
 * Query string para volver al index conservando filtros (y página) tras ABM.
 *
 * @phpstan-type FiltrosListadoClass class-string<object{
 *     resolverDesdeRequest(Request, ?string=): array,
 *     paraQueryString(array): array
 * }>
 */
class QueryRetornoListado
{
    /**
     * true si la request trae parámetros del listado (llegó desde index filtrado/paginado).
     */
    public static function requestTraeContextoIndex(Request $request): bool
    {
        foreach ([
            'filtro_valor',
            'filtro_modo',
            'filtro_campo',
            'filtro_operador',
            'filtro_valor_hasta',
            'filtro_busqueda_rapida',
            'filtro_reparto',
            'fecha_entrega_desde',
            'fecha_entrega_hasta',
            'empresa_id',
            'empresa_todas',
        ] as $key) {
            if ($request->query->has($key)) {
                return true;
            }
        }

        return (int) $request->query('page', 0) > 1;
    }

    /**
     * Query de retorno al index solo cuando se navegó desde el listado (index → editar/crear).
     *
     * @param  FiltrosListadoClass  $filtrosClass
     * @return array<string, string|int>
     */
    public static function desdeRequestSiIndex(Request $request, string $filtrosClass, ?string $busquedaRuta = null): array
    {
        if (! self::requestTraeContextoIndex($request)) {
            return [];
        }

        return self::desdeRequest($request, $filtrosClass, $busquedaRuta);
    }

    /**
     * @param  FiltrosListadoClass  $filtrosClass
     * @return array<string, string|int>
     */
    public static function desdeRequest(Request $request, string $filtrosClass, ?string $busquedaRuta = null): array
    {
        // Solo query string: evita que empresa_id (u otros campos del ABM) contaminen el retorno al index.
        $filtrosRequest = new Request($request->query->all());

        $query = $filtrosClass::paraQueryString(
            $filtrosClass::resolverDesdeRequest($filtrosRequest, $busquedaRuta)
        );

        $page = (int) $request->query('page', 0);
        if ($page > 1) {
            $query['page'] = $page;
        }

        return $query;
    }

    /**
     * Query para links editar/nuevo en el index (filtros activos + página).
     * Usar en la vista con @php inline; @include del partial no propaga la variable.
     *
     * @return array<string, string|int>
     */
    public static function retornoLinksDesdeFiltrosQuery(array $filtrosQuery = []): array
    {
        $query = $filtrosQuery;
        $page = (int) request()->input('page', 0);
        if ($page > 1) {
            $query['page'] = $page;
        }

        return $query;
    }

    public static function esModalConsulta(Request $request): bool
    {
        return $request->input('origen') === 'modal_consulta';
    }

    /**
     * Parámetros para redirect a editar tras guardar/actualizar (conserva filtros del index o modal consulta).
     *
     * @param  FiltrosListadoClass  $filtrosClass
     * @return array<string, string|int>
     */
    public static function paramsRutaEditar(Request $request, string $filtrosClass, int $id): array
    {
        if (self::esModalConsulta($request)) {
            return [
                'id' => $id,
                'origen' => 'modal_consulta',
                'vista' => 'consulta',
            ];
        }

        return array_merge(['id' => $id], self::desdeRequest($request, $filtrosClass));
    }
}
