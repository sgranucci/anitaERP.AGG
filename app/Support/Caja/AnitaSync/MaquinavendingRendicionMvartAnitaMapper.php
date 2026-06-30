<?php

namespace App\Support\Caja\AnitaSync;

use App\Models\Ventas\MaquinavendingRendicion;
use App\Models\Ventas\MaquinavendingRendicionArticulo;

/**
 * Líneas rendmvart (artículos vendidos por rulo) — sistema ventas vía bridge por empresa.
 */
final class MaquinavendingRendicionMvartAnitaMapper
{
    public static function camposInsert(): string
    {
        return implode(', ', [
            'rendmva_nro_oper',
            'rendmva_ubicacion',
            'rendmva_articulo',
            'rendmva_cantidad',
            'rendmva_precio',
        ]);
    }

    /**
     * @param  array{ubicacion:int, articulo:string, cantidad:float, precio:float}  $linea
     * @param  array<string, mixed>  $ctx
     */
    public static function valoresInsert(array $linea, array $ctx): string
    {
        return implode(', ', [
            "'".MaquinavendingRendicionCabeceraAnitaMapper::entero($ctx['nro_ticket'] ?? 0)."'",
            "'".MaquinavendingRendicionCabeceraAnitaMapper::entero($linea['ubicacion'] ?? 0)."'",
            "'".MaquinavendingRendicionCabeceraAnitaMapper::texto($linea['articulo'] ?? '', 13)."'",
            "'".MaquinavendingRendicionCabeceraAnitaMapper::decimal($linea['cantidad'] ?? 0)."'",
            "'".MaquinavendingRendicionCabeceraAnitaMapper::decimal($linea['precio'] ?? 0)."'",
        ]);
    }

    /**
     * @param  array{ubicacion:int, articulo:string, cantidad:float, precio:float}  $linea
     */
    public static function valoresUpdate(array $linea): string
    {
        return 'rendmva_articulo = '
            ."'".MaquinavendingRendicionCabeceraAnitaMapper::texto($linea['articulo'] ?? '', 13)."'"
            .', rendmva_cantidad = '
            .MaquinavendingRendicionCabeceraAnitaMapper::decimal($linea['cantidad'] ?? 0)
            .', rendmva_precio = '
            .MaquinavendingRendicionCabeceraAnitaMapper::decimal($linea['precio'] ?? 0);
    }

    public static function wherePorOperacion(int $nroTicket): string
    {
        return " WHERE rendmva_nro_oper = '"
            .MaquinavendingRendicionCabeceraAnitaMapper::entero($nroTicket)."' ";
    }

    public static function wherePorOperacionYUbicacion(int $nroTicket, int $ubicacion): string
    {
        return self::wherePorOperacion($nroTicket)
            ." AND rendmva_ubicacion = '"
            .MaquinavendingRendicionCabeceraAnitaMapper::entero($ubicacion)."' ";
    }

    public static function skuAnita(?string $sku): string
    {
        $sku = trim((string) $sku);
        if ($sku === '') {
            return str_repeat('0', 13);
        }

        return str_pad($sku, 13, '0', STR_PAD_LEFT);
    }

    /**
     * @return list<array{ubicacion:int, articulo:string, cantidad:float, precio:float}>
     */
    public static function lineasDesdeRendicion(MaquinavendingRendicion $rendicion): array
    {
        $rendicion->loadMissing('articulos.articulo');

        $lineas = [];
        foreach ($rendicion->articulos as $detalle) {
            if (! $detalle instanceof MaquinavendingRendicionArticulo) {
                continue;
            }

            $cantidad = round((float) $detalle->cantidad, 3);
            if ($cantidad <= 0) {
                continue;
            }

            $ubicacion = (int) $detalle->numero_rulo;
            if ($ubicacion <= 0) {
                continue;
            }

            $sku = self::skuAnita($detalle->articulo->sku ?? '');
            $precio = round((float) $detalle->precio_lista, 4);

            $lineas[] = [
                'ubicacion' => $ubicacion,
                'articulo' => $sku,
                'cantidad' => $cantidad,
                'precio' => $precio,
            ];
        }

        usort($lineas, static fn (array $a, array $b): int => $a['ubicacion'] <=> $b['ubicacion']);

        return $lineas;
    }
}
