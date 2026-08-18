<?php

declare(strict_types=1);

namespace App\Support\Manuales\Escenas;

/**
 * Wireframes de propuesta de pago → PNG (pipeline prioriza PNG sobre SVG).
 *
 * @return array<string, array<string, mixed>>
 */
final class PropuestaPagoEscenas
{
    public static function todas(): array
    {
        $sb = ['Propuestas', 'Clearing', 'Proyección', 'Cockpit', 'Auditoría'];

        return [
            'config_premium' => [
                'archivo' => 'config-premium.png',
                'modulo' => 'AP',
                'pantalla' => 'Configuración',
                'card_titulo' => 'Configuración Premium / Light por empresa',
                'card_color' => 'primary',
                'breadcrumb' => 'Caja › Propuesta de pagos › Configuración',
                'sidebar' => $sb,
                'columnas' => ['Empresa', 'Modo', 'Árbol autorización', 'Lote bancario'],
                'filas' => [
                    ['Biyemas', 'Premium', 'PP Gerencia', 'Sí'],
                    ['Kandiko', 'Light', 'SP simple', 'No'],
                    ['Rebisco', 'Premium', 'PP Gerencia', 'Sí'],
                ],
                'botones' => [['texto' => 'Guardar', 'estilo' => 'primary']],
            ],
            'pp_listado' => [
                'archivo' => 'pp-listado.png',
                'modulo' => 'AP',
                'pantalla' => 'Propuestas',
                'card_titulo' => 'Listado de propuestas de pagos',
                'breadcrumb' => 'Caja › Propuesta de pagos',
                'sidebar' => $sb,
                'filtros' => ['Estado: En autorización', 'Empresa: Biyemas'],
                'tools' => ['Nueva', 'PDF', 'Excel'],
                'columnas' => ['PP', 'Fecha', 'Empresa', 'Estado', 'Total', 'Ítems'],
                'filas' => [
                    ['PP-1201', '15/08/2026', 'Biyemas', 'En autorización', '4.820.000', '18'],
                    ['PP-1198', '12/08/2026', 'Biyemas', 'Aprobada', '2.100.000', '9'],
                    ['PP-1190', '08/08/2026', 'Kandiko', 'Ejecutada', '890.000', '4'],
                ],
            ],
            'pp_crear' => [
                'archivo' => 'pp-crear.png',
                'modulo' => 'AP',
                'pantalla' => 'Propuestas',
                'card_titulo' => 'Alta de propuesta — grilla de deuda',
                'card_color' => 'primary',
                'breadcrumb' => 'Caja › Propuesta de pagos › Crear',
                'sidebar' => $sb,
                'campos' => [
                    ['label' => 'Empresa', 'valor' => 'Biyemas S.A.'],
                    ['label' => 'Fecha valor', 'valor' => '18/08/2026'],
                    ['label' => 'Moneda', 'valor' => 'ARS'],
                    ['label' => 'Observación', 'valor' => 'Lote quincenal proveedores'],
                ],
                'columnas' => ['Proveedor', 'Comprobante', 'Vence', 'Saldo', 'A pagar'],
                'filas' => [
                    ['Aceros del Sur', 'FAC A 4521', '20/08', '1.180.000', '1.180.000'],
                    ['Empaques Norte', 'FAC B 881', '22/08', '420.000', '420.000'],
                    ['Servicios Norte', 'SP-8790', '20/08', '320.000', '320.000'],
                ],
                'botones' => [['texto' => 'Armar propuesta', 'estilo' => 'success']],
            ],
            'pp_instrumentos' => [
                'archivo' => 'pp-instrumentos.png',
                'modulo' => 'AP',
                'pantalla' => 'Propuestas',
                'card_titulo' => 'Instrumentos y exclusiones post-aprobación',
                'card_color' => 'primary',
                'breadcrumb' => 'Caja › PP-1201 › Instrumentos',
                'sidebar' => $sb,
                'columnas' => ['Proveedor', 'Importe', 'Instrumento', 'Excluir'],
                'filas' => [
                    ['Aceros del Sur', '1.180.000', 'Transferencia', 'No'],
                    ['Empaques Norte', '420.000', 'Echeq', 'No'],
                    ['Servicios Norte', '320.000', 'Transferencia', 'Sí'],
                ],
                'botones' => [['texto' => 'Guardar instrumentos', 'estilo' => 'primary']],
            ],
            'pp_ejecutar' => [
                'archivo' => 'pp-ejecutar.png',
                'modulo' => 'AP',
                'pantalla' => 'Propuestas',
                'card_titulo' => 'Ejecutar lote → órdenes de pago',
                'card_color' => 'warning',
                'breadcrumb' => 'Caja › PP-1198 › Ejecutar',
                'sidebar' => $sb,
                'alertas' => [
                    ['texto' => 'La ejecución genera OP por ítem aprobado e instrumento definido.', 'tipo' => 'info'],
                ],
                'columnas' => ['Ítem', 'Instrumento', 'Importe', 'OP resultante'],
                'filas' => [
                    ['Aceros del Sur', 'Transferencia', '1.180.000', 'OP-55201'],
                    ['Empaques Norte', 'Echeq', '420.000', 'OP-55202'],
                ],
                'botones' => [['texto' => 'Ejecutar lote', 'estilo' => 'success']],
            ],
            'pp_lote_bancario' => [
                'archivo' => 'pp-lote-bancario.png',
                'modulo' => 'AP',
                'pantalla' => 'Propuestas',
                'card_titulo' => 'Lote bancario — generar y marcar enviado',
                'breadcrumb' => 'Caja › PP-1198 › Lote bancario',
                'sidebar' => $sb,
                'campos' => [
                    ['label' => 'Banco', 'valor' => 'Nación — Interbanking'],
                    ['label' => 'Archivo', 'valor' => 'lote_pp1198_20260812.txt'],
                    ['label' => 'Estado envío', 'valor' => 'Generado'],
                    ['label' => 'OP incluidas', 'valor' => '2'],
                ],
                'botones' => [
                    ['texto' => 'Exportar', 'estilo' => 'primary'],
                    ['texto' => 'Marcar enviado', 'estilo' => 'success'],
                ],
            ],
            'clearing' => [
                'archivo' => 'clearing.png',
                'modulo' => 'AP',
                'pantalla' => 'Clearing',
                'card_titulo' => 'Workbench de clearing bancario',
                'breadcrumb' => 'Caja › Clearing bancario',
                'sidebar' => $sb,
                'filtros' => ['Fecha: 15/08/2026', 'Banco: Nación'],
                'columnas' => ['OP', 'Banco', 'Importe', 'Extracto', 'Estado'],
                'filas' => [
                    ['OP-55201', 'Nación', '1.180.000', 'EXT-991', 'Conciliado'],
                    ['OP-55202', 'Nación', '420.000', '—', 'Pendiente'],
                    ['OP-55180', 'Galicia', '95.000', 'EXT-880', 'Conciliado'],
                ],
                'botones' => [['texto' => 'Conciliar seleccionadas', 'estilo' => 'primary']],
            ],
            'proyeccion_pagos' => [
                'archivo' => 'proyeccion-pagos.png',
                'modulo' => 'AP',
                'pantalla' => 'Proyección',
                'card_titulo' => 'Proyección de pagos',
                'breadcrumb' => 'Caja › Proyección de pagos',
                'sidebar' => $sb,
                'filtros' => ['Desde: 15/08', 'Hasta: 15/09', 'Empresa: Biyemas'],
                'tools' => ['PDF', 'Excel'],
                'columnas' => ['Tramo', 'SP', 'PP', 'OP', 'Total'],
                'filas' => [
                    ['0–7 días', '1.200.000', '2.400.000', '890.000', '4.490.000'],
                    ['8–15 días', '800.000', '1.100.000', '0', '1.900.000'],
                    ['16–30 días', '1.500.000', '0', '0', '1.500.000'],
                ],
            ],
            'cockpit' => [
                'archivo' => 'cockpit.png',
                'modulo' => 'AP',
                'pantalla' => 'Cockpit',
                'card_titulo' => 'Cockpit: KPIs y forecast',
                'breadcrumb' => 'Caja › Cockpit tesorería AP',
                'sidebar' => $sb,
                'columnas' => ['KPI', 'Hoy', '7 días', '30 días'],
                'filas' => [
                    ['Cash position', '12.4 M', '—', '—'],
                    ['SP pendiente', '3.1 M', '4.2 M', '6.8 M'],
                    ['PP en curso', '4.8 M', '2.1 M', '0'],
                    ['OP a debitar', '1.6 M', '0.9 M', '0.2 M'],
                ],
                'nota' => 'Grilla consolidada SP + instrumentos + propuestas.',
            ],
            'excepciones' => [
                'archivo' => 'excepciones.png',
                'modulo' => 'AP',
                'pantalla' => 'Propuestas',
                'card_titulo' => 'Excepciones: reabrir, parcial y delta',
                'card_color' => 'warning',
                'breadcrumb' => 'Caja › PP › Excepciones',
                'sidebar' => $sb,
                'columnas' => ['Caso', 'Acción', 'Efecto'],
                'filas' => [
                    ['OP rechazada banco', 'Reabrir ítem', 'Vuelve a deuda elegible'],
                    ['Pago parcial', 'Propuesta delta', 'Nueva PP por saldo'],
                    ['Error instrumento', 'Corregir y reejecutar', 'Sin duplicar OP'],
                ],
                'botones' => [['texto' => 'Crear propuesta delta', 'estilo' => 'primary']],
            ],
            'auditoria' => [
                'archivo' => 'auditoria.png',
                'modulo' => 'AP',
                'pantalla' => 'Auditoría',
                'card_titulo' => 'Pack de auditoría / compliance',
                'breadcrumb' => 'Caja › Auditoría propuestas',
                'sidebar' => $sb,
                'tools' => ['PDF pack', 'Excel'],
                'columnas' => ['PP', 'Aprobadores', 'Ejecución', 'Clearing', 'Hash'],
                'filas' => [
                    ['PP-1198', 'ger01, fin02', '12/08 16:10', 'OK', 'a1b2…'],
                    ['PP-1190', 'ger01', '08/08 11:02', 'OK', 'c3d4…'],
                ],
                'nota' => 'El pack incluye timeline de autorización e instrumentos.',
            ],
        ];
    }
}
