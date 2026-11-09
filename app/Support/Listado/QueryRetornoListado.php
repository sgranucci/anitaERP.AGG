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
            'filtro_codigo',
            'filtro_nombre',
            'filtro_reparto',
            'filtro_orden',
            'fecha_desde',
            'fecha_hasta',
            'fecha_entrega_desde',
            'fecha_entrega_hasta',
            'solo_sin_remito',
            'empresa_id',
            'empresa_todas',
            'estado',
            'estado_todas',
            'estado_linea',
            'vista',
            'mostrar_totalizadoras',
            'filtro_tipocuenta',
            'filtro_nivel',
            'estado_comb',
            'filtro_estado',
            'filtro_canal',
            'sala_id',
            'sala_todas',
            'sala_sin',
            'sala_scope',
            // Workbench: vista / QBE / orden / columnas
            'vista_id',
            'vista_estandar',
            'columnas',
            'qbe',
            'sort',
            'group',
            // Pedidos Tiendanube
            'desde',
            'hasta',
            'estado_erp',
            'status_tn',
            'payment_status',
            'store_id',
            'buscar',
            'consultar',
            'graficos',
            'grafico',
            'grafico_click',
            'grafico_click_dimension',
            'grafico_off',
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

        // Meta del workbench: paraQueryString de filtros no las incluye.
        return self::conservarMetaWorkbench($request, $query);
    }

    /**
     * Conserva vista / columnas del listado al volver del ABM.
     *
     * @param  array<string, string|int|array>  $query
     * @return array<string, string|int|array>
     */
    public static function conservarMetaWorkbench(Request $request, array $query): array
    {
        if ($request->query->has('vista_id') && $request->query('vista_id') !== '' && $request->query('vista_id') !== null) {
            $query['vista_id'] = (int) $request->query('vista_id');
        } elseif ($request->boolean('vista_estandar')) {
            $query['vista_estandar'] = 1;
        }

        $columnas = $request->query('columnas');
        if (is_string($columnas) && $columnas !== '') {
            $query['columnas'] = $columnas;
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

    /**
     * Query string del listado actual, para colgarla en un link (cuenta corriente, etc.)
     * y volver al index con los mismos filtros. No arrastra modo consulta.
     */
    public static function queryStringDesdeRequestActual(): string
    {
        $query = request()->query();
        unset($query['origen'], $query['embed'], $query['retorno']);
        if (($query['vista'] ?? null) === 'consulta') {
            unset($query['vista']);
        }

        return http_build_query($query);
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
