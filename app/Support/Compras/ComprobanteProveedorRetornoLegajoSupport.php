<?php

namespace App\Support\Compras;

use Illuminate\Http\Request;

/**
 * Si se entra a cargar la FC desde el legajo (bandeja u OC), al terminar se vuelve ahí.
 */
final class ComprobanteProveedorRetornoLegajoSupport
{
    public const ORIGEN_BANDEJA = 'legajo';

    public const ORIGEN_OC = 'oc';

    public static function origenDesdeRequest(Request $request): ?string
    {
        $origen = trim((string) $request->input('origen', $request->query('origen', '')));

        return in_array($origen, [self::ORIGEN_BANDEJA, self::ORIGEN_OC], true) ? $origen : null;
    }

    public static function ordencompraIdDesdeRequest(Request $request, ?int $fallback = null): int
    {
        $id = (int) $request->input(
            'ordencompra_id',
            $request->input('legajo_oc_id', $request->query('ordencompra_id', 0))
        );
        if ($id > 0) {
            return $id;
        }

        return (int) ($fallback ?? 0);
    }

    /**
     * @return array<string, int|string>
     */
    public static function queryParams(Request $request, ?int $ordencompraId = null): array
    {
        $origen = self::origenDesdeRequest($request);
        if ($origen === null) {
            return [];
        }
        $ocId = self::ordencompraIdDesdeRequest($request, $ordencompraId);
        $params = ['origen' => $origen];
        if ($ocId > 0) {
            $params['ordencompra_id'] = $ocId;
        }

        return $params;
    }

    public static function url(Request $request, ?int $ordencompraId = null): ?string
    {
        $origen = self::origenDesdeRequest($request);
        if ($origen === null) {
            return null;
        }
        $ocId = self::ordencompraIdDesdeRequest($request, $ordencompraId);
        if ($origen === self::ORIGEN_OC && $ocId > 0) {
            return route('editar_ordencompra', ['id' => $ocId]);
        }

        return route('consultar_legajo_compra', [
            'vista' => OrdencompraLegajoBandejaFiltros::VISTA_CXP,
        ]);
    }

    /**
     * @return array{origen: string, url: string}|null
     */
    public static function paraVista(Request $request, ?int $ordencompraId = null): ?array
    {
        $origen = self::origenDesdeRequest($request);
        $url = self::url($request, $ordencompraId);
        if ($origen === null || $url === null) {
            return null;
        }

        return ['origen' => $origen, 'url' => $url];
    }
}
