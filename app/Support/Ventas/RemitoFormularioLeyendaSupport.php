<?php

namespace App\Support\Ventas;

/**
 * Leyendas del remito oficial (Anita l-comprobQR / a-comprob.c).
 *
 * `comp_leyenda` son 4 líneas fijas de 40 caracteres:
 * - leyenda1..3 van al pie de la tabla de ítems
 * - leyenda va a OBSERVACIONES
 */
final class RemitoFormularioLeyendaSupport
{
    public const ANCHO_LINEA = 40;

    /**
     * @return array{leyenda1: string, leyenda2: string, leyenda3: string, leyenda: string}
     */
    public static function partir(?string $texto): array
    {
        $lineas = ['', '', '', ''];
        $texto = str_replace(["\r\n", "\r"], "\n", trim((string) $texto));
        if ($texto === '') {
            return self::empaquetar($lineas);
        }

        $idx = 0;
        foreach (explode("\n", $texto) as $bruta) {
            $resto = trim($bruta);
            if ($resto === '' && $idx === 0) {
                continue;
            }
            if ($resto === '') {
                $idx++;
                if ($idx >= 4) {
                    break;
                }
                continue;
            }
            while ($resto !== '' && $idx < 4) {
                $lineas[$idx] = mb_substr($resto, 0, self::ANCHO_LINEA);
                $resto = mb_substr($resto, self::ANCHO_LINEA);
                $idx++;
            }
            if ($idx >= 4) {
                break;
            }
        }

        return self::empaquetar($lineas);
    }

    /**
     * Texto para el remito impreso: leyenda de la factura (comp_leyenda) y, si falta, la del remito.
     */
    public static function desdeVenta(object $venta): array
    {
        $ventaLeyenda = trim((string) ($venta->leyenda ?? ''));
        if ($ventaLeyenda !== '') {
            return self::partir($ventaLeyenda);
        }

        return self::partir((string) ($venta->remitos->leyenda ?? ''));
    }

    /**
     * Buffer de 160 caracteres que Anita graba en `comp_leyenda` (4 × 40).
     */
    public static function paraCompLeyenda(?string $texto): string
    {
        $p = self::partir($texto);

        return self::fijo40($p['leyenda1'])
            .self::fijo40($p['leyenda2'])
            .self::fijo40($p['leyenda3'])
            .self::fijo40($p['leyenda']);
    }

    /**
     * @param  list<string>  $lineas
     * @return array{leyenda1: string, leyenda2: string, leyenda3: string, leyenda: string}
     */
    private static function empaquetar(array $lineas): array
    {
        return [
            'leyenda1' => $lineas[0] ?? '',
            'leyenda2' => $lineas[1] ?? '',
            'leyenda3' => $lineas[2] ?? '',
            'leyenda' => $lineas[3] ?? '',
        ];
    }

    private static function fijo40(string $texto): string
    {
        $texto = mb_substr($texto, 0, self::ANCHO_LINEA);
        $faltan = self::ANCHO_LINEA - mb_strlen($texto);

        return $faltan > 0 ? $texto.str_repeat(' ', $faltan) : $texto;
    }
}
