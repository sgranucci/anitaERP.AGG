<?php

declare(strict_types=1);

namespace App\Support\Ventas\Ferli;

use App\Models\Stock\Combinacion;
use App\Models\Stock\Lote;
use App\Models\Stock\Talle;
use App\Models\Ventas\Pedido_Combinacion;
use App\Models\Ventas\Venta_Emision;
use App\Support\Configuracion\EntornoEmpresaSupport;

/**
 * Ferli: la NC de mostrador y la nota de crédito total no mandan combinación ni talle.
 * Anita (compaux/stkmov) los usa: se copian desde venta_emision de la FAC origen.
 *
 * El canje del POS (+1/−1) es otro caso: el renglón que entra ya trae artículo,
 * color, talle y precio. No se pisan con la factura del par que sale.
 */
final class FacturaLineaCombinacionFerliSupport
{
    /**
     * @param  list<array<string, mixed>>  $dataFactura
     * @param  array<string, mixed>  $requestData
     * @return list<array<string, mixed>>
     */
    public static function enriquecerDesdeEmisionOrigen(array $dataFactura, array $requestData): array
    {
        if (! EntornoEmpresaSupport::esFerli() || $dataFactura === []) {
            return $dataFactura;
        }

        if (self::esCanjePos($requestData)) {
            foreach ($dataFactura as $i => $item) {
                if (! is_array($item) || empty($item['articulo_id'])) {
                    continue;
                }
                $dataFactura[$i] = self::completarVariantePropia($item);
            }

            return $dataFactura;
        }

        $emisionesPorId = self::cargarEmisiones($requestData);
        if ($emisionesPorId === []) {
            return $dataFactura;
        }

        $idsRequest = array_values(array_map(
            static fn ($v) => (int) $v,
            (array) ($requestData['ids'] ?? [])
        ));
        $emisionesOrden = array_values($emisionesPorId);
        $usadas = [];

        foreach ($dataFactura as $i => $item) {
            if (! is_array($item) || empty($item['articulo_id'])) {
                continue;
            }

            $emision = self::resolverEmision($item, $i, $idsRequest, $emisionesPorId, $emisionesOrden, $usadas);
            if (! $emision) {
                continue;
            }

            $usadas[(int) $emision->id] = true;
            $dataFactura[$i] = self::aplicarEmisionAlItem($item, $emision);
        }

        return $dataFactura;
    }

    /**
     * @param  array<string, mixed>  $requestData
     * @return array<int, Venta_Emision>
     */
    private static function cargarEmisiones(array $requestData): array
    {
        $ids = array_values(array_filter(array_map(
            static fn ($v) => (int) $v,
            (array) ($requestData['ids'] ?? [])
        )));
        $ventaId = (int) ($requestData['venta_id'] ?? 0);

        $query = Venta_Emision::query()
            ->with(['combinaciones:id,codigo', 'talles:id,codigo,nombre']);

        if ($ids !== []) {
            $query->whereIn('id', $ids);
        } elseif ($ventaId > 0) {
            $query->where('venta_id', $ventaId)->orderBy('numeroitem')->orderBy('id');
        } else {
            return [];
        }

        $out = [];
        foreach ($query->get() as $emision) {
            $out[(int) $emision->id] = $emision;
        }

        return $out;
    }

    /**
     * NC parcial: el renglón 0 no es el primer ítem de la factura.
     * Si ya trae combinación, se busca esa variante. Si no, se usa la posición
     * (NC total de mostrador, que no manda combinación ni talle).
     *
     * @param  array<string, mixed>  $item
     * @param  list<int>  $idsRequest
     * @param  array<int, Venta_Emision>  $emisionesPorId
     * @param  list<Venta_Emision>  $emisionesOrden
     * @param  array<int, true>  $usadas
     */
    private static function resolverEmision(
        array $item,
        int $i,
        array $idsRequest,
        array $emisionesPorId,
        array $emisionesOrden,
        array $usadas
    ): ?Venta_Emision {
        $idEmision = (int) ($idsRequest[$i] ?? 0);
        if ($idEmision > 0 && isset($emisionesPorId[$idEmision]) && ! isset($usadas[$idEmision])) {
            return $emisionesPorId[$idEmision];
        }

        $combinacionItem = (int) ($item['combinacion_id'] ?? 0);
        if ($combinacionItem > 0) {
            $articuloId = (int) ($item['articulo_id'] ?? 0);
            $talleId = (int) ($item['talle_id'] ?? 0);
            foreach ($emisionesOrden as $emision) {
                $emisionId = (int) $emision->id;
                if (isset($usadas[$emisionId])) {
                    continue;
                }
                if ((int) $emision->articulo_id !== $articuloId) {
                    continue;
                }
                if ((int) ($emision->combinacion_id ?? 0) !== $combinacionItem) {
                    continue;
                }
                if ($talleId > 0 && (int) ($emision->talle_id ?? 0) !== $talleId) {
                    continue;
                }

                return $emision;
            }

            return null;
        }

        $porIndice = $emisionesOrden[$i] ?? null;
        if ($porIndice && isset($usadas[(int) $porIndice->id])) {
            return null;
        }

        return $porIndice;
    }

    /**
     * @param  array<string, mixed>  $requestData
     */
    private static function esCanjePos(array $requestData): bool
    {
        $opciones = is_array($requestData['opciones_emision'] ?? null)
            ? $requestData['opciones_emision']
            : [];

        return ! empty($opciones['canje_pos']);
    }

    /**
     * Canje: código de color y medida salen del renglón que entra, no de la FAC.
     *
     * @param  array<string, mixed>  $item
     * @return array<string, mixed>
     */
    private static function completarVariantePropia(array $item): array
    {
        $combinacionId = (int) ($item['combinacion_id'] ?? 0);
        if ($combinacionId > 0) {
            $item['codigocombinacion'] = trim((string) (Combinacion::query()->whereKey($combinacionId)->value('codigo') ?? ''));
        } else {
            $item['codigocombinacion'] = $item['codigocombinacion'] ?? '';
        }

        $talleId = (int) ($item['talle_id'] ?? 0);
        if ($talleId > 0) {
            $talle = Talle::query()->find($talleId);
            if ($talle) {
                $medida = trim((string) ($talle->nombre ?? ''));
                if ($medida === '') {
                    $medida = trim((string) ($talle->codigo ?? ''));
                }
                $item['medida'] = $medida;
            }
        }

        return $item;
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array<string, mixed>
     */
    private static function aplicarEmisionAlItem(array $item, Venta_Emision $emision): array
    {
        $combinacionId = (int) ($emision->combinacion_id ?? 0);
        if ($combinacionId > 0) {
            $item['combinacion_id'] = $combinacionId;
            $codigo = trim((string) ($emision->combinaciones?->codigo ?? ''));
            if ($codigo === '') {
                $codigo = trim((string) (Combinacion::query()->whereKey($combinacionId)->value('codigo') ?? ''));
            }
            $item['codigocombinacion'] = $codigo;
        } else {
            $item['codigocombinacion'] = $item['codigocombinacion'] ?? '';
        }

        $talleId = (int) ($emision->talle_id ?? 0);
        if ($talleId > 0) {
            $item['talle_id'] = $talleId;
            $talle = $emision->talles;
            if (! $talle) {
                $talle = Talle::query()->find($talleId);
            }
            if ($talle) {
                $medida = trim((string) ($talle->nombre ?? ''));
                if ($medida === '') {
                    $medida = trim((string) ($talle->codigo ?? ''));
                }
                $item['medida'] = $medida;
            }
        }


        if (! empty($emision->pedido_combinacion_id)) {
            $item['pedido_combinacion_id'] = (int) $emision->pedido_combinacion_id;
        }
        if (! empty($emision->ordentrabajo_id)) {
            $item['ordentrabajo_id'] = (int) $emision->ordentrabajo_id;
        }
        if (! empty($emision->modulo_id)) {
            $item['modulo_id'] = (int) $emision->modulo_id;
        }

        $item['despacho'] = self::despachoDesdeEmision($emision);
        if (! array_key_exists('loteimportacion_id', $item) || $item['loteimportacion_id'] === null) {
            $loteImportacionId = self::loteImportacionIdDesdeEmision($emision);
            if ($loteImportacionId > 0) {
                $item['loteimportacion_id'] = $loteImportacionId;
            }
        }

        return $item;
    }

    private static function despachoDesdeEmision(Venta_Emision $emision): string
    {
        $loteId = self::loteImportacionIdDesdeEmision($emision);
        if ($loteId <= 0) {
            return '';
        }

        $nro = Lote::query()->whereKey($loteId)->value('numerodespacho');

        return $nro !== null ? trim((string) $nro) : '';
    }

    private static function loteImportacionIdDesdeEmision(Venta_Emision $emision): int
    {
        $pcId = (int) ($emision->pedido_combinacion_id ?? 0);
        if ($pcId <= 0) {
            return 0;
        }

        $pc = Pedido_Combinacion::query()
            ->select(['id', 'lote_id', 'picking_lote_codigo'])
            ->find($pcId);
        if (! $pc) {
            return 0;
        }

        $loteId = (int) ($pc->lote_id ?? 0);
        if ($loteId > 0) {
            return $loteId;
        }

        $codigoLote = trim((string) ($pc->picking_lote_codigo ?? ''));
        if ($codigoLote === '' || $codigoLote === '0') {
            return 0;
        }

        // Misma resolución que facturación OT/picking: loteimportacion por código de lote.
        $lote = Lote::query()->where('numerodespacho', $codigoLote)->value('id');
        if ($lote) {
            return (int) $lote;
        }

        return 0;
    }
}
