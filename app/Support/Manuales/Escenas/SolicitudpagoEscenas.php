<?php

declare(strict_types=1);

namespace App\Support\Manuales\Escenas;

/** @return array<string, array<string, mixed>> */
final class SolicitudpagoEscenas
{
    public static function todas(): array
    {
        $sb = ['Solicitudes', 'Informe', 'Configuración'];

        return [
            'sp_listado' => [
                'archivo' => 'sp-listado.png',
                'modulo' => 'Caja',
                'pantalla' => 'Solicitudes',
                'card_titulo' => 'Listado de solicitudes de pago',
                'breadcrumb' => 'Caja › Solicitudes de pago',
                'sidebar' => $sb,
                'filtros' => ['Estado: En aprobación', 'Tipo: Madre', 'Empresa: Biyemas'],
                'tools' => ['Nueva', 'PDF', 'Excel'],
                'columnas' => ['SP', 'Tipo', 'Beneficiario', 'Estado', 'Importe', 'Vence'],
                'filas' => [
                    ['SP-8801', 'Madre', 'Aceros del Sur', 'En aprobación', '1.180.000', '30/08/2026'],
                    ['SP-8801-01', 'Hija', 'Aceros del Sur', 'Pendiente', '590.000', '30/08/2026'],
                    ['SP-8790', 'Madre', 'Servicios Norte', 'Aprobada', '320.000', '20/08/2026'],
                ],
            ],
            'sp_filtros' => [
                'archivo' => 'sp-filtros.png',
                'modulo' => 'Caja',
                'pantalla' => 'Solicitudes',
                'card_titulo' => 'Panel de filtros — solicitudes de pago',
                'breadcrumb' => 'Caja › Solicitudes de pago',
                'sidebar' => $sb,
                'filtros' => ['Madre/Hija: Madre', 'Estado: Todas', 'Desde: 01/08', 'Hasta: 31/08', 'Proveedor'],
                'columnas' => ['Filtro', 'Operador', 'Valor ejemplo'],
                'filas' => [
                    ['Tipo', 'igual', 'Madre'],
                    ['Estado', 'contiene', 'aprob'],
                    ['Fecha', 'entre', '01/08 — 31/08/2026'],
                    ['Empresa', 'igual', 'Biyemas'],
                ],
                'nota' => 'Los filtros inteligentes se propagan a PDF/Excel/CSV.',
            ],
            'sp_modal_familia' => [
                'archivo' => 'sp-modal-familia.png',
                'modulo' => 'Caja',
                'pantalla' => 'Solicitudes',
                'card_titulo' => 'Solicitudes de pago',
                'breadcrumb' => 'Caja › Solicitudes de pago',
                'sidebar' => $sb,
                'modal' => [
                    'titulo' => 'Plan / cuotas — familia SP-8801',
                    'texto' => 'Madre e hijas vinculadas al mismo plan de pago.',
                    'columnas' => ['SP', 'Cuota', 'Importe', 'Estado', 'Vence'],
                    'filas' => [
                        ['SP-8801', 'Plan', '1.180.000', 'En aprobación', '—'],
                        ['SP-8801-01', '1/2', '590.000', 'Pendiente', '30/08/2026'],
                        ['SP-8801-02', '2/2', '590.000', 'Pendiente', '30/09/2026'],
                    ],
                    'botones' => [['texto' => 'Cerrar', 'estilo' => 'outline']],
                ],
            ],
            'sp_formulario' => [
                'archivo' => 'sp-formulario.png',
                'modulo' => 'Caja',
                'pantalla' => 'Solicitudes',
                'card_titulo' => 'Solicitud SP-8801 — Datos y Cuentas',
                'card_color' => 'primary',
                'breadcrumb' => 'Caja › Solicitudes › Editar',
                'sidebar' => $sb,
                'tabs' => ['Datos', 'Cuentas', 'Cuotas', 'Archivos'],
                'campos' => [
                    ['label' => 'Beneficiario', 'valor' => 'Aceros del Sur S.A.'],
                    ['label' => 'Concepto', 'valor' => 'Pago OC-9921'],
                    ['label' => 'Importe total', 'valor' => '1.180.000'],
                    ['label' => 'Moneda', 'valor' => 'ARS'],
                    ['label' => 'Cuenta debito', 'valor' => 'Banco Nación CC'],
                    ['label' => 'CBU / Alias', 'valor' => '0000003100012345678901'],
                ],
                'botones' => [['texto' => 'Guardar', 'estilo' => 'primary'], ['texto' => 'Enviar aprobación', 'estilo' => 'success']],
            ],
            'sp_cuotas' => [
                'archivo' => 'sp-cuotas.png',
                'modulo' => 'Caja',
                'pantalla' => 'Solicitudes',
                'card_titulo' => 'Solapa Cuotas — SP madre',
                'card_color' => 'primary',
                'breadcrumb' => 'Caja › Solicitudes › Cuotas',
                'sidebar' => $sb,
                'tabs' => ['Datos', 'Cuentas', 'Cuotas', 'Archivos'],
                'tab_activa' => 2,
                'columnas' => ['Cuota', 'SP hija', 'Importe', 'Vencimiento', 'Estado'],
                'filas' => [
                    ['1/2', 'SP-8801-01', '590.000', '30/08/2026', 'Pendiente'],
                    ['2/2', 'SP-8801-02', '590.000', '30/09/2026', 'Pendiente'],
                ],
                'botones' => [['texto' => 'Generar hijas', 'estilo' => 'primary']],
            ],
            'sp_informe' => [
                'archivo' => 'sp-informe.png',
                'modulo' => 'Caja',
                'pantalla' => 'Informe',
                'card_titulo' => 'Informe de solicitudes de pago',
                'breadcrumb' => 'Caja › Informe solicitudes',
                'sidebar' => $sb,
                'filtros' => ['Período: 08/2026', 'Estado: Todas', 'Empresa: Biyemas'],
                'tools' => ['PDF', 'Excel', 'CSV'],
                'columnas' => ['Estado', 'Cantidad', 'Importe ARS', 'Importe USD'],
                'filas' => [
                    ['Borrador', '4', '210.000', '0'],
                    ['En aprobación', '7', '2.450.000', '1.200'],
                    ['Aprobada', '12', '5.800.000', '3.400'],
                    ['Pagada', '28', '12.100.000', '8.900'],
                ],
            ],
        ];
    }
}
