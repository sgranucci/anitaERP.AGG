<?php

declare(strict_types=1);

namespace App\Support\Manuales\Escenas;

/** @return array<string, array<string, mixed>> */
final class VendingEscenas
{
    public static function todas(): array
    {
        $sb = ['Máquinas', 'Rendiciones Ventas', 'Presentación Caja', 'Comprobantes'];

        return [
            'maquinas_listado' => [
                'archivo' => 'maquinas-listado.png',
                'modulo' => 'Vending',
                'pantalla' => 'Máquinas',
                'card_titulo' => 'Listado de máquinas vending',
                'breadcrumb' => 'Ventas › Vending › Máquinas',
                'sidebar' => $sb,
                'tools' => ['Nueva', 'PDF', 'Excel'],
                'columnas' => ['Código', 'Ubicación', 'Empresa', 'Cuenta caja', 'Estado'],
                'filas' => [
                    ['VM-01', 'Hall recepción', 'Biyemas', '111020', 'Activa'],
                    ['VM-02', 'Planta 2', 'Biyemas', '111021', 'Activa'],
                    ['VM-03', 'Comedor', 'Kandiko', '111030', 'Suspendida'],
                ],
            ],
            'maquinas_form' => [
                'archivo' => 'maquinas-form.png',
                'modulo' => 'Vending',
                'pantalla' => 'Máquinas',
                'card_titulo' => 'Alta / edición máquina vending',
                'card_color' => 'primary',
                'breadcrumb' => 'Ventas › Vending › Máquinas › Editar',
                'sidebar' => $sb,
                'campos' => [
                    ['label' => 'Código', 'valor' => 'VM-01'],
                    ['label' => 'Descripción', 'valor' => 'Hall recepción'],
                    ['label' => 'Empresa', 'valor' => 'Biyemas S.A.'],
                    ['label' => 'Cuenta caja', 'valor' => '111020 — Vending hall'],
                    ['label' => 'Artículo default', 'valor' => 'VEN-CAFÉ'],
                    ['label' => 'Estado', 'valor' => 'Activa'],
                ],
                'botones' => [['texto' => 'Guardar', 'estilo' => 'primary']],
            ],
            'rendicion_ventas_listado' => [
                'archivo' => 'rendicion-ventas-listado.png',
                'modulo' => 'Vending',
                'pantalla' => 'Rendiciones',
                'card_titulo' => 'Rendiciones vending (Ventas)',
                'breadcrumb' => 'Ventas › Vending › Rendiciones',
                'sidebar' => $sb,
                'filtros' => ['Desde: 01/08/2026', 'Máquina: Todas'],
                'tools' => ['Nueva', 'PDF', 'Excel'],
                'columnas' => ['Nº', 'Fecha', 'Máquina', 'Efectivo', 'Estado'],
                'filas' => [
                    ['RV-441', '15/08/2026', 'VM-01', '48.500', 'Confirmada'],
                    ['RV-440', '14/08/2026', 'VM-02', '32.200', 'Confirmada'],
                    ['RV-439', '13/08/2026', 'VM-01', '41.000', 'Anulada'],
                ],
            ],
            'rendicion_ventas_form' => [
                'archivo' => 'rendicion-ventas-form.png',
                'modulo' => 'Vending',
                'pantalla' => 'Rendiciones',
                'card_titulo' => 'Alta rendición vending — Ventas',
                'card_color' => 'primary',
                'breadcrumb' => 'Ventas › Vending › Rendiciones › Crear',
                'sidebar' => $sb,
                'campos' => [
                    ['label' => 'Máquina', 'valor' => 'VM-01 Hall recepción'],
                    ['label' => 'Fecha', 'valor' => '15/08/2026'],
                    ['label' => 'Efectivo contado', 'valor' => '48.500,00'],
                    ['label' => 'Observación', 'valor' => 'Retiro semanal'],
                ],
                'columnas' => ['Concepto', 'Cantidad', 'Importe'],
                'filas' => [
                    ['Ventas café', '120', '36.000'],
                    ['Ventas snacks', '45', '12.500'],
                ],
                'botones' => [['texto' => 'Confirmar', 'estilo' => 'success']],
            ],
            'rendicion_ventas_comprobante' => [
                'archivo' => 'rendicion-ventas-comprobante.png',
                'pdf' => true,
                'card_titulo' => 'Comprobante rendición vending RV-441',
                'nota' => 'Ventas · 15/08/2026 · Máquina VM-01',
                'columnas' => ['Concepto', 'Detalle', 'Importe'],
                'filas' => [
                    ['Máquina', 'VM-01 Hall recepción', ''],
                    ['Efectivo', 'Contado en caja', '48.500,00'],
                    ['Usuario', 'vend01', ''],
                    ['Estado', 'Confirmada', ''],
                ],
            ],
            'caja_listado' => [
                'archivo' => 'caja-listado.png',
                'modulo' => 'Vending',
                'pantalla' => 'Presentación',
                'card_titulo' => 'Presentaciones vending en Caja',
                'breadcrumb' => 'Caja › Vending › Presentaciones',
                'sidebar' => $sb,
                'filtros' => ['Mes: 08/2026', 'Empresa: Biyemas'],
                'columnas' => ['Nº', 'Fecha', 'Máquinas', 'Total', 'Estado'],
                'filas' => [
                    ['CV-118', '15/08/2026', 'VM-01, VM-02', '80.700', 'Presentada'],
                    ['CV-117', '08/08/2026', 'VM-01', '39.200', 'Anulada'],
                ],
                'tools' => ['Nueva', 'PDF'],
            ],
            'caja_form' => [
                'archivo' => 'caja-form.png',
                'modulo' => 'Vending',
                'pantalla' => 'Presentación',
                'card_titulo' => 'Alta presentación vending — Caja',
                'card_color' => 'primary',
                'breadcrumb' => 'Caja › Vending › Crear',
                'sidebar' => $sb,
                'campos' => [
                    ['label' => 'Fecha presentación', 'valor' => '15/08/2026'],
                    ['label' => 'Empresa', 'valor' => 'Biyemas S.A.'],
                    ['label' => 'Rendiciones vinculadas', 'valor' => 'RV-441, RV-440'],
                    ['label' => 'Total efectivo', 'valor' => '80.700,00'],
                ],
                'botones' => [['texto' => 'Presentar', 'estilo' => 'success']],
            ],
            'caja_editar' => [
                'archivo' => 'caja-editar.png',
                'modulo' => 'Vending',
                'pantalla' => 'Presentación',
                'card_titulo' => 'Edición presentación CV-118',
                'card_color' => 'primary',
                'breadcrumb' => 'Caja › Vending › Editar',
                'sidebar' => $sb,
                'campos' => [
                    ['label' => 'Estado', 'valor' => 'Presentada'],
                    ['label' => 'Total', 'valor' => '80.700,00'],
                    ['label' => 'Usuario', 'valor' => 'caja01'],
                    ['label' => 'Observación', 'valor' => 'OK arqueo'],
                ],
                'botones' => [
                    ['texto' => 'Actualizar', 'estilo' => 'primary'],
                    ['texto' => 'Anular', 'estilo' => 'danger'],
                ],
            ],
            'caja_comprobante' => [
                'archivo' => 'caja-comprobante.png',
                'pdf' => true,
                'card_titulo' => 'Comprobante presentación Caja CV-118',
                'nota' => 'Caja · 15/08/2026 · Biyemas S.A.',
                'columnas' => ['Ítem', 'Detalle', 'Importe'],
                'filas' => [
                    ['Rendición', 'RV-441 VM-01', '48.500,00'],
                    ['Rendición', 'RV-440 VM-02', '32.200,00'],
                    ['Total', 'Efectivo presentado', '80.700,00'],
                ],
            ],
        ];
    }
}
