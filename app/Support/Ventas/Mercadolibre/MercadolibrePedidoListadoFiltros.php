<?php

namespace App\Support\Ventas\Mercadolibre;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

final class MercadolibrePedidoListadoFiltros
{
    /**
     * @return array{
     *   desde:?string,
     *   hasta:?string,
     *   estado_erp:?string,
     *   status:?string,
     *   texto:?string,
     *   consultar:bool
     * }
     */
    public static function resolverDesdeRequest(Request $request): array
    {
        return [
            'desde' => self::fecha($request->input('desde')),
            'hasta' => self::fecha($request->input('hasta')),
            'estado_erp' => self::texto($request->input('estado_erp')),
            'status' => self::texto($request->input('status')),
            'texto' => self::texto($request->input('texto')),
            'consultar' => $request->boolean('consultar') || $request->has('consultar'),
        ];
    }

    /**
     * @param  array<string,mixed>  $filtros
     * @return array<string,mixed>
     */
    public static function paraQueryString(array $filtros): array
    {
        $out = [];
        foreach (['desde', 'hasta', 'estado_erp', 'status', 'texto'] as $k) {
            if (! empty($filtros[$k])) {
                $out[$k] = $filtros[$k];
            }
        }
        if (! empty($filtros['consultar'])) {
            $out['consultar'] = 1;
        }

        return $out;
    }

    /**
     * @param  Builder<\App\Models\Ventas\MercadolibrePedido>  $query
     * @param  array<string,mixed>  $filtros
     */
    public static function aplicar(Builder $query, array $filtros): void
    {
        if (! empty($filtros['desde'])) {
            $query->whereDate('paid_at', '>=', $filtros['desde']);
        }
        if (! empty($filtros['hasta'])) {
            $query->whereDate('paid_at', '<=', $filtros['hasta']);
        }
        if (! empty($filtros['estado_erp'])) {
            $query->where('estado_erp', $filtros['estado_erp']);
        }
        if (! empty($filtros['status'])) {
            $query->where('status', $filtros['status']);
        }
        if (! empty($filtros['texto'])) {
            $t = '%'.$filtros['texto'].'%';
            $query->where(function (Builder $q) use ($t, $filtros) {
                $q->where('buyer_name', 'like', $t)
                    ->orWhere('buyer_nickname', 'like', $t)
                    ->orWhere('buyer_doc', 'like', $t)
                    ->orWhere('payment_id', 'like', $t);
                if (ctype_digit((string) $filtros['texto'])) {
                    $q->orWhere('order_id', (int) $filtros['texto'])
                        ->orWhere('pack_id', (int) $filtros['texto']);
                }
            });
        }
    }

    private static function fecha(mixed $v): ?string
    {
        $v = trim((string) $v);
        if ($v === '') {
            return null;
        }
        try {
            return Carbon::parse($v)->format('Y-m-d');
        } catch (\Throwable) {
            return null;
        }
    }

    private static function texto(mixed $v): ?string
    {
        $v = trim((string) $v);

        return $v === '' ? null : $v;
    }
}
