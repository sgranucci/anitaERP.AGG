<?php

namespace App\Support\Compras;

use App\Models\Compras\Pagoproveedor;
use Illuminate\Support\Facades\DB;

/**
 * Resuelve cabeceras `pagoproveedor` desde etiquetas Anita en
 * `proveedor_cuentacorriente_aplicacion.comprobanteaplicado`
 * (ej. "OPP A 2-57827", "OPP A0002-57719", "OPP  0001-00117622").
 *
 * El import Anita a menudo deja la etiqueta sin FK (`pagoproveedor_id` null)
 * cuando la cabecera OP se importa aparte ("sin cuenta corriente").
 */
final class PagoproveedorEtiquetaAnitaSupport
{
    /**
     * @return array{tipo: string, letra: string, sucursal: int, numero: int}|null
     */
    public static function parse(string $etiqueta): ?array
    {
        $etiqueta = trim(preg_replace('/\s+/', ' ', $etiqueta) ?? '');
        if ($etiqueta === '') {
            return null;
        }

        // "OPP A 2-57827" / "OPP A 0002-00057827"
        if (preg_match(
            '/^(OPP|OPA|OPV|AOP)\s+([A-Z])\s+(\d+)\s*-\s*(\d+)$/i',
            $etiqueta,
            $m,
        ) === 1) {
            return [
                'tipo' => strtoupper($m[1]),
                'letra' => strtoupper($m[2]),
                'sucursal' => (int) $m[3],
                'numero' => (int) $m[4],
            ];
        }

        // "OPP A0002-57719" (letra pegada a sucursal)
        if (preg_match(
            '/^(OPP|OPA|OPV|AOP)\s+([A-Z])0*(\d+)\s*-\s*0*(\d+)\s*$/i',
            $etiqueta,
            $m,
        ) === 1) {
            return [
                'tipo' => strtoupper($m[1]),
                'letra' => strtoupper($m[2]),
                'sucursal' => (int) $m[3],
                'numero' => (int) $m[4],
            ];
        }

        // Anita a veces omite la letra: "OPP  0001-00117622" → se asume A.
        if (preg_match(
            '/^(OPP|OPA|OPV|AOP)\s+(\d+)\s*-\s*(\d+)$/i',
            $etiqueta,
            $m,
        ) === 1) {
            return [
                'tipo' => strtoupper($m[1]),
                'letra' => 'A',
                'sucursal' => (int) $m[2],
                'numero' => (int) $m[3],
            ];
        }

        return null;
    }

    public static function esEtiquetaOp(string $etiqueta): bool
    {
        return self::parse($etiqueta) !== null;
    }

    public static function buscarId(string $etiqueta): ?int
    {
        $parsed = self::parse($etiqueta);
        if ($parsed === null) {
            return null;
        }

        $mapa = self::mapaIdsPorClaves([self::clave($parsed)]);

        return $mapa[self::clave($parsed)] ?? null;
    }

    public static function buscarModelo(string $etiqueta): ?Pagoproveedor
    {
        $id = self::buscarId($etiqueta);
        if ($id === null || $id <= 0) {
            return null;
        }

        return Pagoproveedor::query()
            ->with(['monedas:id,abreviatura'])
            ->find($id);
    }

    /**
     * @param  list<string>  $etiquetas
     * @return array<string, int> etiqueta original → pagoproveedor.id
     */
    public static function mapaIdsPorEtiquetas(array $etiquetas): array
    {
        $porEtiqueta = [];
        $claves = [];
        foreach ($etiquetas as $etiqueta) {
            $etiqueta = trim((string) $etiqueta);
            if ($etiqueta === '') {
                continue;
            }
            $parsed = self::parse($etiqueta);
            if ($parsed === null) {
                continue;
            }
            $clave = self::clave($parsed);
            $porEtiqueta[$etiqueta] = $clave;
            $claves[$clave] = true;
        }
        if ($claves === []) {
            return [];
        }

        $mapaClave = self::mapaIdsPorClaves(array_keys($claves));
        $out = [];
        foreach ($porEtiqueta as $etiqueta => $clave) {
            $id = $mapaClave[$clave] ?? null;
            if ($id !== null && $id > 0) {
                $out[$etiqueta] = $id;
            }
        }

        return $out;
    }

    /**
     * @param  list<string>  $claves  "TIPO|LETRA|SUC|NRO"
     * @return array<string, int>
     */
    public static function mapaIdsPorClaves(array $claves): array
    {
        $claves = array_values(array_unique(array_filter($claves)));
        if ($claves === []) {
            return [];
        }

        $query = DB::table('pagoproveedor')->select([
            'id', 'tipocomprobante', 'letra', 'sucursal', 'numerotransaccion',
        ]);
        $query->where(function ($q) use ($claves) {
            foreach ($claves as $clave) {
                [$tipo, $letra, $suc, $nro] = explode('|', $clave);
                $q->orWhere(function ($sub) use ($tipo, $letra, $suc, $nro) {
                    $sub->where('tipocomprobante', $tipo)
                        ->where('letra', $letra)
                        ->where('sucursal', (int) $suc)
                        ->where(function ($n) use ($nro) {
                            $n->where('numerotransaccion', (int) $nro)
                                ->orWhere('numerotransaccion', (string) ((int) $nro));
                        });
                });
            }
        });

        $mapa = [];
        foreach ($query->orderBy('id')->get() as $fila) {
            $clave = self::clave([
                'tipo' => (string) $fila->tipocomprobante,
                'letra' => (string) $fila->letra,
                'sucursal' => (int) $fila->sucursal,
                'numero' => (int) $fila->numerotransaccion,
            ]);
            if (! isset($mapa[$clave])) {
                $mapa[$clave] = (int) $fila->id;
            }
        }

        // Fallback sin letra si hay un único pago tipo|suc|nro.
        $sinLetra = [];
        foreach ($mapa as $clave => $pagoId) {
            [$tipo, , $suc, $nro] = explode('|', $clave);
            $k2 = $tipo.'|'.$suc.'|'.$nro;
            if (! isset($sinLetra[$k2])) {
                $sinLetra[$k2] = $pagoId;
            } else {
                $sinLetra[$k2] = 0;
            }
        }

        foreach ($claves as $clave) {
            if (isset($mapa[$clave])) {
                continue;
            }
            [$tipo, , $suc, $nro] = explode('|', $clave);
            $pagoId = (int) ($sinLetra[$tipo.'|'.$suc.'|'.$nro] ?? 0);
            if ($pagoId > 0) {
                $mapa[$clave] = $pagoId;
            }
        }

        return $mapa;
    }

    /**
     * @param  array{tipo: string, letra: string, sucursal: int, numero: int}  $parsed
     */
    public static function clave(array $parsed): string
    {
        return strtoupper(trim($parsed['tipo'])).'|'
            .strtoupper(trim($parsed['letra'])).'|'
            .(int) $parsed['sucursal'].'|'
            .(int) $parsed['numero'];
    }
}
