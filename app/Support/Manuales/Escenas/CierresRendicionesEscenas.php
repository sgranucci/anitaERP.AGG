<?php

declare(strict_types=1);

namespace App\Support\Manuales\Escenas;

/** @return array<string, array<string, mixed>> */
final class CierresRendicionesEscenas
{
    public static function todas(): array
    {
        $sb = ['Cierre máquinas', 'Cierre bingo', 'Preview asiento', 'Conciliación Flash'];

        return [
            'flujo_cierre_rendicion' => [
                'archivo' => 'flujo-cierre-rendicion.png',
                'modulo' => 'Contable',
                'pantalla' => 'Cierre máquinas',
                'card_titulo' => 'Circuito — cierres de rendiciones',
                'breadcrumb' => 'Contable › Cierres rendiciones › Flujo',
                'sidebar' => $sb,
                'nota' => 'Caja → presentación → cierre contable → asiento → export Anita.',
                'columnas' => ['Etapa', 'Responsable', 'Pantalla', 'Salida'],
                'filas' => [
                    ['1', 'Operador caja', 'Flash / rendiciones', 'Totales diarios cargados'],
                    ['2', 'Supervisor', 'Presentación rendiciones', 'Aprobación del día'],
                    ['3', 'Contabilidad', 'Cierre máquinas / bingo', 'Listado agrupado por día'],
                    ['4', 'Contabilidad', 'Preview asiento', 'DEBE/HABER balanceado'],
                    ['5', 'Contabilidad', 'Ejecutar cierre', 'Asiento en ERP + Anita'],
                ],
            ],
            'cierre_maquina_listado' => [
                'archivo' => 'cierre-maquina-listado.png',
                'modulo' => 'Contable',
                'pantalla' => 'Cierre máquinas',
                'card_titulo' => 'Cierre rendiciones máquinas — listado por día',
                'breadcrumb' => 'Contable › Cierre rendiciones › Máquinas',
                'sidebar' => $sb,
                'filtros' => ['Empresa: Biyemas', 'Mes: Agosto 2026', 'Turno: C', 'Estado: Pendiente'],
                'tools' => ['Consultar', 'Preview asiento', 'Ejecutar cierre'],
                'columnas' => ['Fecha', 'Turno', 'Terminales', 'Neto rendición', 'Estado', 'Asiento'],
                'filas' => [
                    ['13/08/2026', 'C', '12', '$ 14.820.000', 'Cerrado', 'AS-88401'],
                    ['14/08/2026', 'C', '12', '$ 15.105.400', 'Cerrado', 'AS-88455'],
                    ['15/08/2026', 'C', '12', '$ 14.949.600', 'Pendiente', '—'],
                ],
            ],
            'preview_asiento_maquina' => [
                'archivo' => 'preview-asiento-maquina.png',
                'modulo' => 'Contable',
                'pantalla' => 'Preview asiento',
                'card_titulo' => 'Preview asiento cierre máquinas — 15/08/2026 turno C',
                'card_color' => 'warning',
                'breadcrumb' => 'Contable › Cierre máquinas › Preview',
                'sidebar' => $sb,
                'campos' => [
                    ['label' => 'Empresa', 'valor' => 'Biyemas S.A.'],
                    ['label' => 'Fecha asiento', 'valor' => '16/08/2026'],
                    ['label' => 'Neto a contabilizar', 'valor' => '$ 14.949.600,00'],
                    ['label' => 'Tipo', 'valor' => 'Cierre rendición máquinas'],
                ],
                'columnas' => ['Cuenta', 'Descripción', 'Debe', 'Haber', 'CC'],
                'filas' => [
                    ['111010010', 'Caja máquinas slots', '$ 13.454.640', '—', 'CC-01'],
                    ['214010015', 'Comisiones Wigos', '—', '$ 1.494.960', 'CC-01'],
                    ['411050020', 'Ingresos netos slots', '—', '$ 13.454.640', 'CC-01'],
                    ['', 'Totales', '$ 14.949.600', '$ 14.949.600', ''],
                ],
                'botones' => [
                    ['texto' => 'Ejecutar cierre', 'estilo' => 'success'],
                    ['texto' => 'Volver', 'estilo' => 'outline'],
                ],
            ],
            'matriz_cuentas_maquina' => [
                'archivo' => 'matriz-cuentas-maquina.png',
                'modulo' => 'Contable',
                'pantalla' => 'Cierre máquinas',
                'card_titulo' => 'Matriz origen de datos → cuenta contable (máquinas)',
                'breadcrumb' => 'Contable › Cierre máquinas › Origen cuentas',
                'sidebar' => $sb,
                'nota' => 'Referencia de mapeo para el asiento de cierre de rendiciones de máquinas.',
                'columnas' => ['Origen dato', 'Campo / API', 'Cuenta contable', 'Lado'],
                'filas' => [
                    ['Neto slots', 'win_ol_slot.neto', '111010010 Caja máquinas', 'Debe'],
                    ['Comisión Wigos', 'win_ol_slot.comision', '214010015 Comisiones', 'Haber'],
                    ['Ingreso neto', 'Calculado neto − comisión', '411050020 Ingresos slots', 'Haber'],
                    ['Hand pay', 'Rendición manual', '111010012 Pagos manuales', 'Debe/Haber'],
                    ['Diferencia flash', 'Conciliación Flash', '591010005 Diferencias caja', 'Debe/Haber'],
                ],
            ],
            'cierre_bingo' => [
                'archivo' => 'cierre-bingo.png',
                'modulo' => 'Contable',
                'pantalla' => 'Cierre bingo',
                'card_titulo' => 'Cierre rendiciones bingo — listado diario',
                'breadcrumb' => 'Contable › Cierre rendiciones › Bingo',
                'sidebar' => $sb,
                'filtros' => ['Empresa: Biyemas', 'Mes: Agosto 2026', 'Estado: Pendiente'],
                'tools' => ['Consultar', 'Preview BIN', 'Ejecutar cierre'],
                'columnas' => ['Fecha', 'Terminal', 'Ventas', 'Premios', 'Neto', 'Estado'],
                'filas' => [
                    ['13/08/2026', 'BIN-01', '$ 92.400', '$ 38.200', '$ 54.200', 'Cerrado'],
                    ['14/08/2026', 'BIN-01', '$ 88.600', '$ 41.800', '$ 46.800', 'Cerrado'],
                    ['15/08/2026', 'BIN-01', '$ 86.800', '$ 42.600', '$ 44.200', 'Pendiente'],
                ],
            ],
            'preview_bingo' => [
                'archivo' => 'preview-bingo.png',
                'modulo' => 'Contable',
                'pantalla' => 'Preview asiento',
                'card_titulo' => 'Preview asiento BIN — cierre bingo 15/08/2026',
                'card_color' => 'warning',
                'breadcrumb' => 'Contable › Cierre bingo › Preview',
                'sidebar' => $sb,
                'campos' => [
                    ['label' => 'Empresa', 'valor' => 'Biyemas S.A.'],
                    ['label' => 'Tipo asiento', 'valor' => 'BIN — Cierre bingo'],
                    ['label' => 'Neto sesión', 'valor' => '$ 44.200,00'],
                    ['label' => 'Pozo acumulado', 'valor' => '$ 1.850.000,00 (informativo)'],
                ],
                'columnas' => ['Cuenta', 'Descripción', 'Debe', 'Haber'],
                'filas' => [
                    ['111010020', 'Caja bingo terminal', '$ 44.200', '—'],
                    ['411060010', 'Ingresos bingo netos', '—', '$ 44.200'],
                    ['214010018', 'Premios bingo pagados', '$ 42.600', '—'],
                    ['111010020', 'Contrapartida premios', '—', '$ 42.600'],
                ],
                'botones' => [['texto' => 'Ejecutar cierre BIN', 'estilo' => 'success']],
            ],
            'conciliacion_flash' => [
                'archivo' => 'conciliacion-flash.png',
                'modulo' => 'Contable',
                'pantalla' => 'Conciliación Flash',
                'card_titulo' => 'Conciliación Flash — rendiciones vs win_ol_slot / win_ol_rul',
                'breadcrumb' => 'Contable › Controles › Conciliación Flash',
                'sidebar' => $sb,
                'filtros' => ['Empresa: Biyemas', 'Fecha: 15/08/2026', 'Rubro: Slots'],
                'tools' => ['Consultar', 'Exportar diferencias'],
                'columnas' => ['Rubro', 'Total Flash', 'API win_ol', 'Diferencia', 'Estado'],
                'filas' => [
                    ['Slots / máquinas', '$ 1.245.800', '$ 1.245.800', '$ 0', 'OK'],
                    ['Ruleta / mesas', '$ 382.500', '$ 380.000', '$ 2.500', 'Revisar'],
                    ['Bingo terminal', '$ 98.200', '$ 98.200', '$ 0', 'OK'],
                    ['Gastronomía POS', '$ 482.350', '$ 482.350', '$ 0', 'OK'],
                ],
                'alertas' => [
                    ['texto' => 'Diferencias mayores a tolerancia bloquean la presentación del flash del día.', 'tipo' => 'warning'],
                ],
            ],
        ];
    }
}
