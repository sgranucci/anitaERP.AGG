<?php

declare(strict_types=1);

namespace App\Support\Manuales\Escenas;

/** @return array<string, array<string, mixed>> */
final class StockEscenas
{
    public static function todas(): array
    {
        $sb = ['Recuentos', 'Movimientos artículo', 'Importar', 'Configuración'];

        return [
            'recuento_listado' => [
                'archivo' => 'recuento-listado.png',
                'modulo' => 'Stock',
                'pantalla' => 'Recuentos',
                'card_titulo' => 'Listado de recuentos de inventario',
                'breadcrumb' => 'Stock › Recuento',
                'sidebar' => $sb,
                'filtros' => ['Estado: En conteo', 'Depósito: DEP-01', 'Empresa: Biyemas'],
                'tools' => ['Nuevo', 'PDF', 'Excel'],
                'columnas' => ['Nº', 'Fecha', 'Depósito', 'Estado', 'Líneas', 'Usuario'],
                'filas' => [
                    ['RC-310', '15/08/2026', 'DEP-01', 'En conteo', '186', 'stock01'],
                    ['RC-305', '01/08/2026', 'DEP-02', 'Cerrado', '92', 'stock02'],
                    ['RC-298', '15/07/2026', 'DEP-01', 'Cerrado', '210', 'stock01'],
                ],
            ],
            'recuento_crear' => [
                'archivo' => 'recuento-crear.png',
                'modulo' => 'Stock',
                'pantalla' => 'Recuentos',
                'card_titulo' => 'Alta de recuento — cabecera',
                'card_color' => 'primary',
                'breadcrumb' => 'Stock › Recuento › Crear',
                'sidebar' => $sb,
                'campos' => [
                    ['label' => 'Empresa', 'valor' => 'Biyemas S.A.'],
                    ['label' => 'Depósito', 'valor' => 'DEP-01 Principal'],
                    ['label' => 'Fecha', 'valor' => '15/08/2026'],
                    ['label' => 'Observación', 'valor' => 'Inventario mensual salón'],
                ],
                'botones' => [['texto' => 'Crear y agregar líneas', 'estilo' => 'primary']],
            ],
            'recuento_editar' => [
                'archivo' => 'recuento-editar.png',
                'modulo' => 'Stock',
                'pantalla' => 'Recuentos',
                'card_titulo' => 'Edición RC-310 — líneas de conteo',
                'card_color' => 'primary',
                'breadcrumb' => 'Stock › Recuento › Editar',
                'sidebar' => $sb,
                'columnas' => ['SKU', 'Descripción', 'Teórico', 'Contado', 'Diferencia'],
                'filas' => [
                    ['ART-8801', 'Rodamiento 6205', '100', '98', '-2'],
                    ['ART-8810', 'Grasa 5kg', '40', '40', '0'],
                    ['ART-1200', 'Caja cartón', '500', '512', '+12'],
                ],
                'botones' => [['texto' => 'Guardar', 'estilo' => 'primary'], ['texto' => 'Cerrar inventario', 'estilo' => 'warning']],
            ],
            'recuento_ver' => [
                'archivo' => 'recuento-ver.png',
                'modulo' => 'Stock',
                'pantalla' => 'Recuentos',
                'card_titulo' => 'Detalle RC-305 e historial',
                'breadcrumb' => 'Stock › Recuento › Ver',
                'sidebar' => $sb,
                'campos' => [
                    ['label' => 'Estado', 'valor' => 'Cerrado'],
                    ['label' => 'Modo ajuste', 'valor' => 'Ajustar diferencias'],
                    ['label' => 'Cerrado por', 'valor' => 'stock02'],
                    ['label' => 'Fecha cierre', 'valor' => '02/08/2026 18:40'],
                ],
                'columnas' => ['Fecha', 'Evento', 'Usuario', 'Detalle'],
                'filas' => [
                    ['01/08 09:00', 'Creado', 'stock02', 'DEP-02'],
                    ['01/08 17:20', 'Conteo', 'stock02', '92 líneas'],
                    ['02/08 18:40', 'Cerrado', 'stock02', 'Ajuste generado'],
                ],
            ],
            'recuento_cierre' => [
                'archivo' => 'recuento-opciones-cierre.png',
                'modulo' => 'Stock',
                'pantalla' => 'Recuentos',
                'card_titulo' => 'Panel de cierre — modos de ajuste',
                'card_color' => 'warning',
                'breadcrumb' => 'Stock › Recuento › Cerrar',
                'sidebar' => $sb,
                'alertas' => [
                    ['texto' => 'Elegí el modo antes de confirmar. El cierre genera movimientos firmados en artículo_movimiento.', 'tipo' => 'warning'],
                ],
                'columnas' => ['Modo', 'Efecto', 'Uso típico'],
                'filas' => [
                    ['Ajustar diferencias', 'Entrada/salida por delta', 'Inventario rutinario'],
                    ['Forzar a contado', 'Saldo = contado', 'Corte contable'],
                    ['Solo registrar', 'Sin movimiento stock', 'Auditoría'],
                ],
                'botones' => [
                    ['texto' => 'Confirmar cierre', 'estilo' => 'danger'],
                    ['texto' => 'Cancelar', 'estilo' => 'outline'],
                ],
            ],
            'recuento_movimientos' => [
                'archivo' => 'recuento-movimientos.png',
                'modulo' => 'Stock',
                'pantalla' => 'Movimientos',
                'card_titulo' => 'Movimientos por artículo y depósito',
                'breadcrumb' => 'Stock › Consulta movimientos',
                'sidebar' => $sb,
                'filtros' => ['Artículo: ART-8801', 'Depósito: DEP-01', 'Desde: 01/08'],
                'columnas' => ['Fecha', 'Comprobante', 'Entrada', 'Salida', 'Saldo'],
                'filas' => [
                    ['14/08', 'COM-5521', '20', '', '118'],
                    ['15/08', 'RC-310 ajuste', '', '2', '116'],
                    ['15/08', 'TRA-2201', '', '15', '101'],
                ],
                'nota' => 'Columnas Entrada/Salida según signo de cantidad (positiva/negativa).',
            ],
            'recuento_importar' => [
                'archivo' => 'recuento-importar.png',
                'modulo' => 'Stock',
                'pantalla' => 'Importar',
                'card_titulo' => 'Importación de líneas desde Excel',
                'card_color' => 'primary',
                'breadcrumb' => 'Stock › Recuento › Importar',
                'sidebar' => $sb,
                'campos' => [
                    ['label' => 'Recuento destino', 'valor' => 'RC-310'],
                    ['label' => 'Archivo', 'valor' => 'conteo_dep01_20260815.xlsx'],
                ],
                'columnas' => ['Fila', 'SKU', 'Contado', 'Resultado'],
                'filas' => [
                    ['2', 'ART-8801', '98', 'OK'],
                    ['3', 'ART-8810', '40', 'OK'],
                    ['4', 'ART-9999', '1', 'SKU inexistente'],
                ],
                'botones' => [['texto' => 'Importar válidas', 'estilo' => 'success']],
            ],
        ];
    }
}
