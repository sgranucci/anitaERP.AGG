<?php

namespace App\Support\Ventas;

use App\Models\Ventas\Tipotransaccion;
use App\Models\Ventas\Venta_Emision;

/**
 * En notas de crédito originadas en una factura, los precios unitarios
 * se toman tal cual de venta_emision (hasta 6 decimales). Redondear a 2
 * centavos en el form/JS desfasaba el total de la NC respecto de la FAC.
 */
final class VentaNotaCreditoPrecioLiteralSupport
{
    public static function esNotaCreditoTipotransaccionId(int $tipotransaccionId): bool
    {
        if ($tipotransaccionId <= 0) {
            return false;
        }

        $tipo = Tipotransaccion::query()->find($tipotransaccionId);

        return $tipo !== null && ($tipo->signo ?? '') === 'R';
    }

    /**
     * Reemplaza precios[] por el precio grabado en la factura origen.
     *
     * @param  array<string, mixed>  $data
     */
    public static function aplicarPreciosFacturaOrigen(array &$data): void
    {
        $ventaId = (int) ($data['venta_id'] ?? 0);
        $tipoId = (int) ($data['tipotransaccion_id'] ?? 0);
        if ($ventaId <= 0 || ! self::esNotaCreditoTipotransaccionId($tipoId)) {
            return;
        }
        if (! isset($data['precios']) || ! is_array($data['precios']) || $data['precios'] === []) {
            return;
        }

        $origenes = Venta_Emision::query()
            ->where('venta_id', $ventaId)
            ->orderBy('id')
            ->get(['id', 'articulo_id', 'precio']);
        if ($origenes->isEmpty()) {
            return;
        }

        $porId = $origenes->keyBy('id');
        $porIndice = $origenes->values();
        $ids = is_array($data['ids'] ?? null) ? $data['ids'] : [];
        $articuloIds = is_array($data['articulo_ids'] ?? null) ? $data['articulo_ids'] : [];

        foreach ($data['precios'] as $i => $_) {
            $emisionId = (int) ($ids[$i] ?? 0);
            $em = null;
            if ($emisionId > 0) {
                $em = $porId->get($emisionId) ?? $porId->get((string) $emisionId);
            }
            if ($em === null) {
                $em = $porIndice->get($i);
            }
            if ($em === null) {
                continue;
            }

            $articuloLinea = (int) ($articuloIds[$i] ?? 0);
            if ($articuloLinea > 0 && (int) $em->articulo_id !== $articuloLinea) {
                continue;
            }

            $data['precios'][$i] = self::formatLiteral($em->precio);
        }
    }

    public static function formatLiteral($precio): string
    {
        if ($precio === null || $precio === '') {
            return '0';
        }

        if (is_string($precio)) {
            $precio = str_replace([' ', ','], '', $precio);
            if (is_numeric($precio)) {
                if (strpos($precio, '.') !== false) {
                    $precio = rtrim(rtrim($precio, '0'), '.');
                    if ($precio === '' || $precio === '-') {
                        return '0';
                    }
                }

                return $precio;
            }
        }

        $texto = number_format((float) $precio, 6, '.', '');
        $texto = rtrim(rtrim($texto, '0'), '.');

        return $texto === '' ? '0' : $texto;
    }
}
