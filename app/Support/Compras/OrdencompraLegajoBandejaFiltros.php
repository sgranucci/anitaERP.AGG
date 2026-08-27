<?php

namespace App\Support\Compras;

use Illuminate\Http\Request;

final class OrdencompraLegajoBandejaFiltros
{
    public const VISTA_PENDIENTES = 'pendientes';

    public const VISTA_ESTADOS = 'estados';

    public const VISTA_CXP = 'cxp';

    public const VISTA_PAGOS = 'pagos';

    public const VISTA_ARCHIVADOS = 'archivados';

    public const VISTA_HISTORICO = 'historico';

    public const TAB_GASTRONOMIA = 'gastronomia';

    public const TAB_RESTO = 'resto';

    public const TAB_TODOS = 'todos';

    public const ATAJO_SIN_FACTURA = 'sin_factura';

    public const ATAJO_SIN_COM = 'sin_com';

    public const ATAJO_COM_SIN_ASIGNAR = 'com_sin_asignar';

    public const ATAJO_LISTO_CARGAR = 'listo_cargar';

    public const ATAJO_FC_CARGADA = 'fc_cargada';

    public const ATAJO_CON_PAGO = 'con_pago';

    /** @var list<string> */
    public const VISTAS = [
        self::VISTA_PENDIENTES,
        self::VISTA_ESTADOS,
        self::VISTA_CXP,
        self::VISTA_PAGOS,
        self::VISTA_ARCHIVADOS,
        self::VISTA_HISTORICO,
    ];

    /** @var list<string> */
    public const ATAJOS = [
        self::ATAJO_SIN_FACTURA,
        self::ATAJO_SIN_COM,
        self::ATAJO_COM_SIN_ASIGNAR,
        self::ATAJO_LISTO_CARGAR,
        self::ATAJO_FC_CARGADA,
        self::ATAJO_CON_PAGO,
    ];

    /**
     * @return array<string, mixed>
     */
    public static function resolverDesdeRequest(Request $request, ?int $empresaDefault = null): array
    {
        $vista = (string) $request->query('vista', self::VISTA_PENDIENTES);
        if (! in_array($vista, self::VISTAS, true)) {
            $vista = self::VISTA_PENDIENTES;
        }
        $tab = (string) $request->query('tab', self::TAB_TODOS);
        if (! in_array($tab, [self::TAB_GASTRONOMIA, self::TAB_RESTO, self::TAB_TODOS], true)) {
            $tab = self::TAB_TODOS;
        }
        $atajo = trim((string) $request->query('atajo', ''));
        if ($atajo !== '' && ! in_array($atajo, self::ATAJOS, true)) {
            $atajo = '';
        }

        $listado = OrdencompraListadoFiltros::resolverDesdeRequest($request, null, $empresaDefault);

        return array_merge($listado, [
            'vista' => $vista,
            'tab' => $tab,
            'atajo' => $atajo,
            'nro_oc' => trim((string) $request->query('nro_oc', '')),
            'nro_factura' => trim((string) $request->query('nro_factura', '')),
            'nro_com' => trim((string) $request->query('nro_com', '')),
            'nro_op' => trim((string) $request->query('nro_op', '')),
        ]);
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return array<string, scalar>
     */
    public static function paraQueryString(array $filtros): array
    {
        $params = OrdencompraListadoFiltros::paraQueryString($filtros);
        $params['vista'] = $filtros['vista'] ?? self::VISTA_PENDIENTES;
        $params['tab'] = $filtros['tab'] ?? self::TAB_TODOS;
        if (! empty($filtros['atajo'])) {
            $params['atajo'] = $filtros['atajo'];
        }
        if (! empty($filtros['nro_oc'])) {
            $params['nro_oc'] = $filtros['nro_oc'];
        }
        if (! empty($filtros['nro_factura'])) {
            $params['nro_factura'] = $filtros['nro_factura'];
        }
        if (! empty($filtros['nro_com'])) {
            $params['nro_com'] = $filtros['nro_com'];
        }
        if (! empty($filtros['nro_op'])) {
            $params['nro_op'] = $filtros['nro_op'];
        }

        return $params;
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return array<string, scalar>
     */
    public static function paraQueryStringEmpresaYVista(array $filtros): array
    {
        $params = OrdencompraListadoFiltros::paraQueryStringEmpresa($filtros);
        $params['vista'] = $filtros['vista'] ?? self::VISTA_PENDIENTES;
        $params['tab'] = $filtros['tab'] ?? self::TAB_TODOS;
        if (! empty($filtros['atajo'])) {
            $params['atajo'] = $filtros['atajo'];
        }

        return $params;
    }
}
