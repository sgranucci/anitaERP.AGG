<?php

namespace App\Support\Compras\Retencion;

/**
 * Resolución de régimen efectivo en el pago.
 *
 * Precedencia: override del pago → default del comprobante/factura → default del proveedor.
 */
final class RetencionRegimenResolver
{
    public static function resolverId(?int $overridePago, ?int $defaultComprobante, ?int $defaultProveedor): ?int
    {
        if ($overridePago !== null && $overridePago > 0) {
            return $overridePago;
        }

        if ($defaultComprobante !== null && $defaultComprobante > 0) {
            return $defaultComprobante;
        }

        if ($defaultProveedor !== null && $defaultProveedor > 0) {
            return $defaultProveedor;
        }

        return null;
    }

    /**
     * @return array{
     *     retencionganancia_id: int|null,
     *     retencioniva_id: int|null,
     *     retencionsuss_id: int|null,
     *     retieneganancia: bool,
     *     retieneiva: bool,
     *     retienesuss: bool
     * }
     */
    public static function defaultsDesdeProveedor(\App\Models\Compras\Proveedor $proveedor): array
    {
        return [
            'retencionganancia_id' => $proveedor->retencionganancia_id
                ? (int) $proveedor->retencionganancia_id
                : null,
            'retencioniva_id' => $proveedor->retencioniva_id
                ? (int) $proveedor->retencioniva_id
                : null,
            'retencionsuss_id' => $proveedor->retencionsuss_id
                ? (int) $proveedor->retencionsuss_id
                : null,
            'retieneganancia' => strtoupper((string) ($proveedor->retieneganancia ?? 'N')) === 'S',
            'retieneiva' => strtoupper((string) ($proveedor->retieneiva ?? 'N')) === 'S',
            'retienesuss' => strtoupper((string) ($proveedor->retienesuss ?? 'N')) === 'S',
        ];
    }
}
