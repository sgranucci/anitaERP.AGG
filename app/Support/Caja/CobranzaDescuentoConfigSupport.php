<?php

namespace App\Support\Caja;

use App\Models\Configuracion\Actividad_Arca;
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

    /**
     * Punto de venta de la factura a la que se aplica el descuento.
     */
    public static function puntoventaIdDesdeFactura(int $puntoventaId): int
    {
        if ($puntoventaId <= 0 || ! Puntoventa::query()->whereKey($puntoventaId)->exists()) {
            throw new InvalidArgumentException(
                'La factura no tiene un punto de venta válido para emitir la nota de crédito.'
            );
        }

        return $puntoventaId;
    }

    /**
     * Actividad de la factura. Si el comprobante no la trae, la del mismo punto de venta.
     */
    public static function actividadArcaIdDesdeFactura(?int $actividadFacturaId, int $puntoventaFacturaId): int
    {
        if (self::actividadArcaExiste((int) ($actividadFacturaId ?? 0))) {
            return (int) $actividadFacturaId;
        }

        $desdePv = (int) (Puntoventa::query()->whereKey($puntoventaFacturaId)->value('actividad_arca_id') ?? 0);
        if (self::actividadArcaExiste($desdePv)) {
            return $desdePv;
        }

        throw new InvalidArgumentException(
            'La factura no tiene actividad ARCA y su punto de venta tampoco.'
        );
    }

    private static function actividadArcaExiste(int $id): bool
    {
        return $id > 0 && Actividad_Arca::query()->whereKey($id)->exists();
    }

    public static function tipotransaccionNotaCreditoId(int $empresaId, ?string $letraFacturaOrigen = null): int
    {
        $mapaLetra = config('cobranza.nc_tipotransaccion_por_letra', []);
        $letra = strtoupper(trim((string) $letraFacturaOrigen));
        if ($letra !== '' && isset($mapaLetra[$letra])) {
            $porLetra = self::tipoNotaCreditoCobranza((int) $mapaLetra[$letra]);
            if ($porLetra) {
                return (int) $porLetra->id;
            }

            throw new InvalidArgumentException(self::mensajeTipoNcInvalido((int) $mapaLetra[$letra], $letra));
        }

        $id = (int) config('cobranza.nc_tipotransaccion_id', 0);
        $tipo = self::tipoNotaCreditoCobranza($id);
        if ($tipo) {
            return (int) $tipo->id;
        }

        $ncp = self::tipoNcpPorAbreviatura();
        if ($ncp) {
            return (int) $ncp->id;
        }

        throw new InvalidArgumentException(self::mensajeTipoNcInvalido($id, null));
    }

    /**
     * Nota de crédito usable en el descuento: activa, operación devolución y signo Resta.
     * El default 4 de AGG en Ferli es CONOT (consumo de OT) y además queda oculto
     * para Eloquent porque deleted_at es 0000-00-00.
     */
    private static function tipoNotaCreditoCobranza(int $id): ?Tipotransaccion
    {
        if ($id <= 0) {
            return null;
        }

        $tipo = Tipotransaccion::query()->find($id);
        if (! $tipo || ! $tipo->esNotaCredito() || $tipo->signo !== 'R') {
            return null;
        }
        if (($tipo->estado ?? 'A') === 'S') {
            return null;
        }

        return $tipo;
    }

    private static function tipoNcpPorAbreviatura(): ?Tipotransaccion
    {
        $tipo = Tipotransaccion::query()
            ->where('abreviatura', 'NCP')
            ->where('operacion', 'C')
            ->where('estado', 'A')
            ->orderBy('id')
            ->first();

        if (! $tipo || $tipo->signo !== 'R') {
            return null;
        }

        return $tipo;
    }

    private static function mensajeTipoNcInvalido(int $id, ?string $letra): string
    {
        $prefijo = $letra !== null && $letra !== ''
            ? 'La nota de crédito letra '.$letra.' (tipo id '.$id.') no es válida.'
            : 'No hay una nota de crédito válida para el descuento de cobranza.';

        if ($id <= 0) {
            return $prefijo.' Configure COBRANZA_NC_TIPOTRANSACCION_ID con el tipo NCP.';
        }

        $crudo = Tipotransaccion::withTrashed()->find($id);
        if (! $crudo) {
            return $prefijo.' No existe el tipo de transacción id '.$id.'.';
        }

        $baja = $crudo->trashed() ? ' Está dado de baja.' : '';

        return $prefijo.' El id '.$id.' es '.$crudo->abreviatura.' ('.$crudo->nombre
            .'), operación '.$crudo->operacion.', signo '.$crudo->signo.'.'.$baja
            .' Corresponde el tipo NCP (crédito por descuento).';
    }

    public static function articuloIdParaDescuento(int $empresaId): int
    {
        $id = (int) config('cobranza.nc_articulo_id', 0);
        if ($id > 0) {
            $existe = Articulo::query()
                ->whereKey($id)
                ->where(function ($q) use ($empresaId) {
                    $q->where('empresa_id', $empresaId)->orWhereNull('empresa_id');
                })
                ->exists();
            if ($existe) {
                return $id;
            }
        }

        $sku = trim((string) config('cobranza.nc_articulo_sku', ''));
        if ($sku !== '') {
            $art = Articulo::query()
                ->where(function ($q) use ($empresaId) {
                    $q->where('empresa_id', $empresaId)->orWhereNull('empresa_id');
                })
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
