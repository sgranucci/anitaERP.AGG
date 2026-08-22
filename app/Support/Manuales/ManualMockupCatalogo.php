<?php

declare(strict_types=1);

namespace App\Support\Manuales;

/**
 * Inventario de manuales con capturas mockeables y rutas de salida.
 */
final class ManualMockupCatalogo
{
    /**
     * @return array<string, array{config:string, img_dir:string, label:string}>
     */
    public static function manuales(): array
    {
        return [
            'gastronomia' => [
                'config' => 'manual_gastronomia',
                'img_dir' => 'docs/manual-gastronomia/img',
                'label' => 'Gastronomía',
            ],
            'compras' => [
                'config' => 'manual_compras',
                'img_dir' => 'docs/manual-compras/img',
                'label' => 'Compras',
            ],
            'recepcion-movstock' => [
                'config' => 'manual_recepcion_movstock',
                'img_dir' => 'docs/manual-recepcion-movstock/img',
                'label' => 'Recepción / Mov. Stock',
            ],
            'contable' => [
                'config' => 'manual_contable',
                'img_dir' => 'docs/manual-contable/img',
                'label' => 'Contable',
            ],
            'uif' => [
                'config' => 'manual_uif',
                'img_dir' => 'docs/manual-uif/img',
                'label' => 'UIF',
            ],
            'solicitudpago' => [
                'config' => 'manual_solicitudpago',
                'img_dir' => 'docs/manual-solicitudpago/img',
                'label' => 'Solicitud de Pago',
            ],
            'stock' => [
                'config' => 'manual_stock',
                'img_dir' => 'docs/manual-stock/img',
                'label' => 'Stock / Recuento',
            ],
            'vending' => [
                'config' => 'manual_vending',
                'img_dir' => 'docs/manual-vending/img',
                'label' => 'Vending',
            ],
            'canjes-marketing' => [
                'config' => 'manual_canjes_marketing',
                'img_dir' => 'docs/manual-canjes-marketing/img',
                'label' => 'Canjes Marketing',
            ],
            'ventas' => [
                'config' => 'manual_ventas',
                'img_dir' => 'docs/manual-ventas/img',
                'label' => 'Ventas / Pedidos',
            ],
            'propuesta-pago' => [
                'config' => 'manual_propuesta_pago',
                'img_dir' => 'docs/manual-propuesta-pago/img',
                'label' => 'Propuesta de Pago',
            ],
            'reporte-definible' => [
                'config' => 'manual_reporte_definible',
                'img_dir' => 'docs/manual-reporte-definible/img',
                'label' => 'Reportes Definibles',
            ],
            'stock-gastronomia' => [
                'config' => 'manual_stock_gastronomia',
                'img_dir' => 'docs/manual-stock-gastronomia/img',
                'label' => 'Stock Gastronomía',
            ],
            'caja' => [
                'config' => 'manual_caja',
                'img_dir' => 'docs/manual-caja/img',
                'label' => 'Caja',
            ],
            'sueldos' => [
                'config' => 'manual_sueldos',
                'img_dir' => 'docs/manual-sueldos/img',
                'label' => 'Sueldos',
            ],
            'cierres-rendiciones' => [
                'config' => 'manual_cierres_rendiciones',
                'img_dir' => 'docs/manual-cierres-rendiciones/img',
                'label' => 'Cierres Rendiciones',
            ],
        ];
    }

    /**
     * Claves de captura que son diagramas (no regenerar con mockup).
     *
     * @return list<string>
     */
    public static function clavesDiagrama(string $manual): array
    {
        return match ($manual) {
            'gastronomia' => ['flujo'],
            'compras' => ['circuito'],
            'recepcion-movstock' => ['flujo_operativo', 'circuito_recepcion', 'circuito_transferencia', 'circuito_trcont'],
            'contable' => ['flujo_cierre', 'circuito_bloqueo'],
            'uif' => ['flujo_uif', 'roles_uif'],
            'solicitudpago' => ['flujo_operativo', 'circuito_estados', 'circuito_madre_hija'],
            'stock' => ['circuito_estados'],
            'vending' => ['flujo'],
            'canjes-marketing' => ['flujo'],
            'ventas' => ['flujo_pedidos'],
            'propuesta-pago' => ['flujo_premium', 'pp_autorizacion'],
            'reporte-definible' => [
                'mapa_modulo', 'glosario_flujo', 'fuente_verdad', 'layouts', 'consolidacion',
                'drill', 'publicacion', 'distribucion', 'notas', 'paridad', 'alertas', 'ejemplos',
            ],
            'sueldos' => ['flujo_sancion'],
            default => [],
        };
    }
}
