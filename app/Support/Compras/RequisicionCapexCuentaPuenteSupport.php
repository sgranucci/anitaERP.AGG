<?php

namespace App\Support\Compras;

use App\Models\Stock\Articulo;
use App\Support\Stock\ArticuloCuentaCompraGastoSupport;

/**
 * Si el artículo imputa a la cuenta puente de bienes de uso, la línea de
 * requisición exige CAPEX. La cuenta es 123010030 «COMPRAS BS. DE USO -CTA.PUENTE»
 * (mismo código en cada empresa). Se toma la cuenta de compras y, si no hay, la de gastos.
 */
final class RequisicionCapexCuentaPuenteSupport
{
    public const CODIGO_CUENTA = '123010030';

    /**
     * @param  array{codigo?: string, nombre?: string}|null  $cuenta
     */
    public static function cuentaExigeCapex(?array $cuenta): bool
    {
        if ($cuenta === null) {
            return false;
        }

        return trim((string) ($cuenta['codigo'] ?? '')) === self::CODIGO_CUENTA;
    }

    /**
     * @param  array<string, mixed>  $payload  articulo_ids[], cantidades[], capex_ids[], empresa_id
     *
     * @throws \InvalidArgumentException
     */
    public static function validar(array $payload): void
    {
        if (! OrdencompraUiConfigSupport::pedirPartidaCapex()) {
            return;
        }

        $articuloIds = $payload['articulo_ids'] ?? [];
        if (! is_array($articuloIds) || $articuloIds === []) {
            return;
        }

        $empresaId = (int) ($payload['empresa_id'] ?? 0);
        $ids = [];
        foreach ($articuloIds as $id) {
            $n = (int) $id;
            if ($n > 0) {
                $ids[] = $n;
            }
        }

        $cuentas = ArticuloCuentaCompraGastoSupport::etiquetasPorArticulos($ids, $empresaId);
        $skus = $ids === []
            ? collect()
            : Articulo::query()->whereIn('id', $ids)->pluck('sku', 'id');

        $faltan = [];
        $n = count($articuloIds);
        for ($i = 0; $i < $n; $i++) {
            $articuloId = (int) ($articuloIds[$i] ?? 0);
            if ($articuloId <= 0) {
                continue;
            }
            $cantidad = (float) ($payload['cantidades'][$i] ?? 0);
            if ($cantidad <= 0) {
                continue;
            }
            if (! self::cuentaExigeCapex($cuentas[$articuloId] ?? null)) {
                continue;
            }
            $capexId = $payload['capex_ids'][$i] ?? null;
            if ($capexId !== null && $capexId !== '' && (int) $capexId > 0) {
                continue;
            }
            $sku = trim((string) ($skus[$articuloId] ?? ''));
            $etiqueta = $sku !== '' ? $sku : ('artículo '.$articuloId);
            $faltan[] = 'línea '.($i + 1).' ('.$etiqueta.')';
        }

        if ($faltan === []) {
            return;
        }

        throw new \InvalidArgumentException(
            'La cuenta puente de bienes de uso ('.self::CODIGO_CUENTA.' COMPRAS BS. DE USO -CTA.PUENTE) exige un CAPEX. Falta en: '
            .implode('; ', $faltan).'.'
        );
    }
}
