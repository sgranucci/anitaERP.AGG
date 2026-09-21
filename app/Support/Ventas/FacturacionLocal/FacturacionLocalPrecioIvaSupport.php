<?php

namespace App\Support\Ventas\FacturacionLocal;

use App\Models\Stock\Listaprecio;
use App\Models\Ventas\LocalVenta;

/**
 * Precios POS Local / listas de locales.
 *
 * Regla de negocio Ferli: las listas de los locales (WEB / OFERTA WEB / LUGANO
 * y cualquier listaprecio asignada a local_venta) son siempre precio final
 * (IVA incluido). Factura B debe emitir con incluyeimpuestos='1' sin sumar IVA.
 */
final class FacturacionLocalPrecioIvaSupport
{
    public const INCLUYE_SI = '1';

    public const INCLUYE_NO = '2';

    /**
     * Flag efectivo para emitir: listas de locales → siempre IVA incluido.
     */
    public static function flagLista(?int $listaprecioId): string
    {
        if ($listaprecioId === null || $listaprecioId <= 0) {
            return self::INCLUYE_SI;
        }

        if (self::esListaDeLocal($listaprecioId)) {
            return self::INCLUYE_SI;
        }

        $raw = Listaprecio::query()->whereKey($listaprecioId)->value('incluyeimpuesto');
        $flag = trim((string) ($raw ?? ''));

        return self::esNeto($flag) ? self::INCLUYE_NO : self::INCLUYE_SI;
    }

    public static function esNeto(string $flag): bool
    {
        $f = strtoupper(trim($flag));

        return $f === self::INCLUYE_NO || $f === 'N' || $f === '0';
    }

    /**
     * Lista usada por algún local, o códigos ERP del mapeo Anita Local (11/12/13).
     */
    public static function esListaDeLocal(int $listaprecioId): bool
    {
        if ($listaprecioId <= 0) {
            return false;
        }

        if (LocalVenta::query()->where('listaprecio_id', $listaprecioId)->exists()) {
            return true;
        }

        $codigosLocales = array_values(array_unique(array_map(
            'strval',
            array_values(PrecioListaLocalMapeoSupport::mapa())
        )));
        if ($codigosLocales === []) {
            $codigosLocales = ['11', '12', '13'];
        }

        $codigo = Listaprecio::query()->whereKey($listaprecioId)->value('codigo');
        if ($codigo === null || $codigo === '') {
            return false;
        }

        return in_array((string) $codigo, $codigosLocales, true)
            || in_array((string) ((int) $codigo), $codigosLocales, true);
    }

    /**
     * Precio a mostrar/cobrar en POS: las listas locales ya son finales.
     */
    public static function precioParaPos(float $precioLista, ?int $listaprecioId): float
    {
        return round(max(0., $precioLista), 4);
    }

    /**
     * @param  list<float>  $precios
     * @param  list<int>  $articuloIds  reservado (firma estable)
     * @return array{precios:list<float>,incluyeimpuestos:list<string>,flag_lista:string}
     */
    public static function prepararParaEmision(
        array $precios,
        array $articuloIds,
        ?int $listaprecioId,
        string $letra,
        bool $preciosSonDeLista = true,
    ): array {
        unset($articuloIds, $preciosSonDeLista);

        $flagLista = self::flagLista($listaprecioId);
        $letra = strtoupper(trim($letra));
        $esB = $letra === 'B' || $letra === '';

        $outPrecios = [];
        $outFlags = [];

        foreach ($precios as $precio) {
            $outPrecios[] = round((float) $precio, 4);
            // Factura B (y locales): siempre IVA incluido. Letra A: mismo flag de lista local (también final).
            $outFlags[] = ($esB || $flagLista === self::INCLUYE_SI)
                ? self::INCLUYE_SI
                : $flagLista;
        }

        return [
            'precios' => $outPrecios,
            'incluyeimpuestos' => $outFlags,
            'flag_lista' => $flagLista,
        ];
    }
}
