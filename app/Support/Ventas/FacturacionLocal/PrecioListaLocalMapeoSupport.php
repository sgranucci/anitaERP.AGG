<?php

namespace App\Support\Ventas\FacturacionLocal;

/**
 * Mapeo de códigos de lista Anita Local (stkpre.stkp_lista) → códigos ERP (listaprecio.codigo).
 * Ferli: 5→11, 6→12, 50→13.
 */
final class PrecioListaLocalMapeoSupport
{
    /**
     * @return array<string, string> codigo_anita_local => codigo_erp
     */
    public static function mapa(): array
    {
        $raw = config('facturacion_local.lista_precio_mapeo', []);
        if (! is_array($raw) || $raw === []) {
            $raw = ['5' => '11', '6' => '12', '50' => '13'];
        }

        $out = [];
        foreach ($raw as $origen => $destino) {
            $o = self::normalizarCodigo($origen);
            $d = self::normalizarCodigo($destino);
            if ($o === '' || $d === '') {
                continue;
            }
            $out[$o] = $d;
        }

        return $out;
    }

    /**
     * @return list<string>
     */
    public static function codigosAnitaLocal(): array
    {
        return array_keys(self::mapa());
    }

    /**
     * @return list<string>
     */
    public static function codigosErp(): array
    {
        return array_values(array_unique(self::mapa()));
    }

    public static function codigoErpDesdeAnita(string|int|null $codigoAnita): ?string
    {
        $codigo = self::normalizarCodigo($codigoAnita);
        if ($codigo === '') {
            return null;
        }

        $mapa = self::mapa();

        return $mapa[$codigo] ?? null;
    }

    /**
     * Nombres sugeridos al crear listaprecio ERP faltantes (clave = código ERP).
     *
     * @return array<string, string>
     */
    public static function nombresErpPorDefecto(): array
    {
        $raw = config('facturacion_local.lista_precio_nombres_erp', []);
        if (! is_array($raw) || $raw === []) {
            return [
                '11' => 'WEB',
                '12' => 'OFERTA WEB',
                '13' => 'LUGANO',
            ];
        }

        $out = [];
        foreach ($raw as $codigo => $nombre) {
            $c = self::normalizarCodigo($codigo);
            $n = trim((string) $nombre);
            if ($c !== '' && $n !== '') {
                $out[$c] = $n;
            }
        }

        return $out;
    }

    public static function normalizarCodigo(string|int|null $codigo): string
    {
        $s = trim((string) $codigo);
        if ($s === '') {
            return '';
        }
        if (ctype_digit($s)) {
            return (string) ((int) $s);
        }

        return $s;
    }
}
