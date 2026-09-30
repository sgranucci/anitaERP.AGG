<?php

namespace App\Support\Contable;

/**
 * Texto libre de comprobante y orden de compra en cada renglón del asiento.
 * El comprobante se parte en tipo, letra, sucursal y número para el mayor.
 */
final class AsientoLineaDocumentoTextoSupport
{
    /**
     * @return array{anita_tipo: ?string, anita_letra: ?string, anita_sucursal: ?int, anita_nro: ?int, vacio: bool, valido: bool}
     */
    public static function comprobante(string $texto): array
    {
        $t = trim((string) preg_replace('/\s+/', ' ', $texto));
        $vacio = [
            'anita_tipo' => null,
            'anita_letra' => null,
            'anita_sucursal' => null,
            'anita_nro' => null,
            'vacio' => true,
            'valido' => true,
        ];
        if ($t === '' || $t === '—' || $t === '-') {
            return $vacio;
        }

        $tipo = '';
        $letra = '';
        $sucursal = 0;
        $nro = 0;

        if (preg_match('/^([A-Za-z0-9]{1,10})\s+([A-Za-z])\s*(\d{1,5})\s*-\s*(\d{1,8})$/', $t, $m)) {
            $tipo = $m[1];
            $letra = $m[2];
            $sucursal = (int) $m[3];
            $nro = (int) $m[4];
        } elseif (preg_match('/^([A-Za-z0-9]{1,10})\s*(\d{1,5})\s*-\s*(\d{1,8})$/', $t, $m)) {
            $tipo = $m[1];
            $sucursal = (int) $m[2];
            $nro = (int) $m[3];
        } elseif (preg_match('/^([A-Za-z])\s*(\d{1,5})\s*-\s*(\d{1,8})$/', $t, $m)) {
            $letra = $m[1];
            $sucursal = (int) $m[2];
            $nro = (int) $m[3];
        } elseif (preg_match('/^(\d{1,5})\s*-\s*(\d{1,8})$/', $t, $m)) {
            $sucursal = (int) $m[1];
            $nro = (int) $m[2];
        } elseif (preg_match('/(\d{1,8})\s*$/', $t, $m)) {
            $nro = (int) $m[1];
            $prefijo = trim(substr($t, 0, -strlen($m[1])));
            if (preg_match('/^([A-Za-z0-9]{1,10})(?:\s+([A-Za-z]))?$/', $prefijo, $p)) {
                $tipo = $p[1];
                $letra = $p[2] ?? '';
            }
        }

        if ($nro <= 0) {
            return [
                'anita_tipo' => null,
                'anita_letra' => null,
                'anita_sucursal' => null,
                'anita_nro' => null,
                'vacio' => false,
                'valido' => false,
            ];
        }

        $tipoLimpio = strtoupper((string) preg_replace('/[^A-Za-z0-9]/', '', $tipo));
        $letraLimpia = strtoupper((string) preg_replace('/[^A-Za-z0-9]/', '', $letra));

        return [
            'anita_tipo' => $tipoLimpio !== '' ? substr($tipoLimpio, 0, 10) : null,
            'anita_letra' => $letraLimpia !== '' ? substr($letraLimpia, 0, 3) : null,
            'anita_sucursal' => $sucursal,
            'anita_nro' => $nro,
            'vacio' => false,
            'valido' => true,
        ];
    }

    public static function ordenCompra(string $texto): ?int
    {
        $digits = preg_replace('/\D/', '', trim($texto)) ?? '';
        $nro = (int) $digits;

        return $nro > 0 ? $nro : null;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{anita_tipo: ?string, anita_letra: ?string, anita_sucursal: ?int, anita_nro: ?int, nro_ordencompra: ?int, vacio: bool, valido: bool}
     */
    public static function desdeRequest(array $data, int $i): array
    {
        if (array_key_exists('comprobante_linea', $data) || array_key_exists('ordencompra_linea', $data)) {
            $comp = self::comprobante((string) ($data['comprobante_linea'][$i] ?? ''));
            $oc = self::ordenCompra((string) ($data['ordencompra_linea'][$i] ?? ''));

            return [
                'anita_tipo' => $comp['anita_tipo'],
                'anita_letra' => $comp['anita_letra'],
                'anita_sucursal' => $comp['anita_sucursal'],
                'anita_nro' => $comp['anita_nro'],
                'nro_ordencompra' => $oc,
                'vacio' => $comp['vacio'] && $oc === null,
                'valido' => $comp['valido'],
            ];
        }

        $nro = (int) ($data['mov_anita_nro'][$i] ?? 0);
        $oc = (int) ($data['mov_nro_ordencompra'][$i] ?? 0);
        $tipo = strtoupper(trim((string) ($data['mov_anita_tipo'][$i] ?? '')));
        $letra = trim((string) ($data['mov_anita_letra'][$i] ?? ''));

        return [
            'anita_tipo' => $nro > 0 && $tipo !== '' ? substr($tipo, 0, 10) : null,
            'anita_letra' => $nro > 0 && $letra !== '' ? substr($letra, 0, 3) : null,
            'anita_sucursal' => $nro > 0 ? (int) ($data['mov_anita_sucursal'][$i] ?? 0) : null,
            'anita_nro' => $nro > 0 ? $nro : null,
            'nro_ordencompra' => $oc > 0 ? $oc : null,
            'vacio' => $nro <= 0 && $oc <= 0,
            'valido' => true,
        ];
    }
}
