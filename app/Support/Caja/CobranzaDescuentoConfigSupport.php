<?php

namespace App\Support\Caja;

use App\Models\Stock\Articulo;
use App\Models\Ventas\Puntoventa;
use App\Models\Ventas\Tipotransaccion;
use InvalidArgumentException;

final class CobranzaDescuentoConfigSupport
{
    public static function habilitado(): bool
    {
        return (bool) config('cobranza.descuento_nc_habilitado', true);
    }

    /**
     * ¿La NCP de cobranza lleva percepción IIBB? El Bierzo: no (descuento financiero).
     */
    public static function ncPercibeIibb(): bool
    {
        return (bool) config('cobranza.nc_percepcion_iibb', true);
    }

    public static function puntoventaIdParaEmpresa(int $empresaId): int
    {
        $map = config('cobranza.nc_puntoventa_por_empresa', []);
        $id = (int) ($map[$empresaId] ?? $map[(string) $empresaId] ?? 0);
        if ($id <= 0) {
            $fallback = config('facturacion.PUNTOVENTA_FACTURACION');
            if (is_array($fallback)) {
                $id = (int) ($fallback[0] ?? 0);
            } else {
                $id = (int) $fallback;
            }
        }

        if ($id <= 0) {
            throw new InvalidArgumentException(
                'Configure COBRANZA_NC_PUNTOVENTA_POR_EMPRESA en .env para la empresa '.$empresaId.'.'
            );
        }

        $pv = Puntoventa::query()->find($id);
        if (! $pv || (int) $pv->empresa_id !== $empresaId) {
            throw new InvalidArgumentException(
                'El punto de venta id '.$id.' no existe o no pertenece a la empresa '.$empresaId.'.'
            );
        }

        return $id;
    }

    public static function tipotransaccionNotaCreditoId(int $empresaId, ?string $letraFacturaOrigen = null): int
    {
        $mapaLetra = config('cobranza.nc_tipotransaccion_por_letra', []);
        $letra = strtoupper(trim((string) $letraFacturaOrigen));
        if ($letra !== '' && isset($mapaLetra[$letra])) {
            return (int) $mapaLetra[$letra];
        }

        $id = (int) config('cobranza.nc_tipotransaccion_id', 0);
        if ($id <= 0) {
            throw new InvalidArgumentException(
                'Configure COBRANZA_NC_TIPOTRANSACCION_ID o COBRANZA_NC_TIPOTRANSACCION_POR_LETRA en .env.'
            );
        }

        $tipo = Tipotransaccion::query()->find($id);
        if (! $tipo || $tipo->signo !== 'R') {
            throw new InvalidArgumentException(
                'El tipo de transacción id '.$id.' no es una nota de crédito válida (signo Resta).'
            );
        }

        return $id;
    }

    public static function articuloIdParaDescuento(int $empresaId): int
    {
        $id = (int) config('cobranza.nc_articulo_id', 0);
        if ($id > 0) {
            $existe = Articulo::query()->whereKey($id)->where('empresa_id', $empresaId)->exists();
            if ($existe) {
                return $id;
            }
        }

        $sku = trim((string) config('cobranza.nc_articulo_sku', ''));
        if ($sku !== '') {
            $art = Articulo::query()
                ->where('empresa_id', $empresaId)
                ->where('sku', $sku)
                ->value('id');
            if ($art) {
                return (int) $art;
            }
        }

        throw new InvalidArgumentException(
            'Configure COBRANZA_NC_ARTICULO_ID o COBRANZA_NC_ARTICULO_SKU para descuentos en cobranza (empresa '.$empresaId.').'
        );
    }

    public static function extraerLetraDesdeCodigoVenta(?string $codigo): string
    {
        if ($codigo && preg_match('/\s([A-Z])\s*-/u', $codigo, $m)) {
            return $m[1];
        }

        return 'A';
    }
}
