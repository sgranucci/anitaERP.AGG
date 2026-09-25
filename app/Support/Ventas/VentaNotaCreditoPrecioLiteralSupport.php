<?php

namespace App\Support\Ventas;

use App\Models\Ventas\Tipotransaccion;
use App\Models\Ventas\Venta_Emision;
use Illuminate\Support\Collection;

/**
 * En notas de crédito originadas en una factura, precios e IVA de línea
 * se toman de venta_emision (lo facturado), no del maestro actual del artículo.
 *
 * Precios: hasta 6 decimales (redondear a 2 en form/JS desfasaba el total).
 * Impuesto: si el artículo cambió de alícuota después, la NC sigue la FAC.
 */
final class VentaNotaCreditoPrecioLiteralSupport
{
    public static function esNotaCreditoTipotransaccionId(int $tipotransaccionId): bool
    {
        if ($tipotransaccionId <= 0) {
            return false;
        }

        $tipo = Tipotransaccion::query()->find($tipotransaccionId);

        return $tipo !== null && $tipo->esNotaCredito();
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

        foreach ($data['precios'] as $i => $_) {
            $em = self::emisionOrigenParaIndice($origenes, $data, (int) $i);
            if ($em === null) {
                continue;
            }

            $data['precios'][$i] = self::formatLiteral($em->precio);
        }
    }

    /**
     * Fuerza impuesto_ids[] con el IVA grabado en venta_emision de la FAC.
     * Evita que calculaFacturaGeneral caiga al impuesto actual del artículo
     * (p. ej. maestro corregido de 27% → 21% o al revés).
     *
     * @param  array<string, mixed>  $data
     */
    public static function aplicarImpuestosFacturaOrigen(array &$data): void
    {
        $ventaId = (int) ($data['venta_id'] ?? 0);
        $tipoId = (int) ($data['tipotransaccion_id'] ?? 0);
        if ($ventaId <= 0 || ! self::esNotaCreditoTipotransaccionId($tipoId)) {
            return;
        }

        $lineCount = max(
            is_array($data['precios'] ?? null) ? count($data['precios']) : 0,
            is_array($data['cantidades'] ?? null) ? count($data['cantidades']) : 0,
            is_array($data['articulo_ids'] ?? null) ? count($data['articulo_ids']) : 0,
            is_array($data['impuesto_ids'] ?? null) ? count($data['impuesto_ids']) : 0,
        );
        if ($lineCount <= 0) {
            return;
        }

        $origenes = Venta_Emision::query()
            ->where('venta_id', $ventaId)
            ->orderBy('id')
            ->get(['id', 'articulo_id', 'impuesto_id']);
        if ($origenes->isEmpty()) {
            return;
        }

        if (! isset($data['impuesto_ids']) || ! is_array($data['impuesto_ids'])) {
            $data['impuesto_ids'] = [];
        }

        for ($i = 0; $i < $lineCount; $i++) {
            $em = self::emisionOrigenParaIndice($origenes, $data, $i);
            if ($em === null) {
                continue;
            }

            $impuestoId = (int) ($em->impuesto_id ?? 0);
            if ($impuestoId > 0) {
                $data['impuesto_ids'][$i] = $impuestoId;
            }
        }
    }

    /**
     * @param  Collection<int, Venta_Emision>  $origenes
     * @param  array<string, mixed>  $data
     */
    private static function emisionOrigenParaIndice(Collection $origenes, array $data, int $i): ?Venta_Emision
    {
        $porId = $origenes->keyBy('id');
        $porIndice = $origenes->values();
        $ids = is_array($data['ids'] ?? null) ? $data['ids'] : [];
        $articuloIds = is_array($data['articulo_ids'] ?? null) ? $data['articulo_ids'] : [];

        $emisionId = (int) ($ids[$i] ?? 0);
        $em = null;
        if ($emisionId > 0) {
            $em = $porId->get($emisionId) ?? $porId->get((string) $emisionId);
        }
        if ($em === null) {
            $em = $porIndice->get($i);
        }
        if ($em === null) {
            return null;
        }

        $articuloLinea = (int) ($articuloIds[$i] ?? 0);
        if ($articuloLinea > 0 && (int) $em->articulo_id !== $articuloLinea) {
            return null;
        }

        return $em;
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
