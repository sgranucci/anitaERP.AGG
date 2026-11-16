<?php

declare(strict_types=1);

namespace App\Support\Manuales\Escenas;

/**
 * Wireframes conciliación bancaria → PNG (manual:generar-mockups conciliacion-bancaria).
 *
 * @return array<string, array<string, mixed>>
 */
final class ConciliacionBancariaEscenas
{
    public static function todas(): array
    {
        $sb = ['Conciliación', 'Manual', 'Tablero', 'Aging', 'Worklist', 'Reglas'];

        return [
            'flujo_mensual' => [
                'archivo' => 'flujo-mensual.png',
                'modulo' => 'Contable',
                'pantalla' => 'Circuito',
                'card_titulo' => 'Circuito mensual premium de conciliación bancaria',
                'breadcrumb' => 'Contable › Conciliación bancaria',
                'sidebar' => $sb,
                'alertas' => [
                    ['texto' => 'Premium: IB + Excel/CSV/OFX/CAMT/MT940 · worklist · residuales · evidencia · SoD. Sin open banking.', 'tipo' => 'info'],
                ],
                'columnas' => ['Paso', 'Pantalla', 'Quién', 'Resultado'],
                'filas' => [
                    ['1. Corrida', 'Index → Conciliar', 'Contaduría', 'Carátula + pares + residuales'],
                    ['2. Revisar', 'Solapas del resultado', 'Contaduría', 'Cheques / IB / gastos'],
                    ['3. Manual', 'Workspace manual', 'Contaduría / Tesorería', 'Multi-match y extracto'],
                    ['4. Excepciones', 'Worklist', 'Contaduría', 'Asignar / resolver'],
                    ['5. Cerrar', 'Manual + evidencia', 'Contaduría', 'Snapshot + adjuntos'],
                    ['6. Aprobar', 'Manual (otro usuario)', 'Enc-contaduría', 'Aprobado SoD'],
                    ['7. Control', 'Tablero / Aging / Forecast', 'Gerencia', 'Avance multi-cuenta'],
                ],
            ],
            'conc_index' => [
                'archivo' => 'conc-index.png',
                'modulo' => 'Contable',
                'pantalla' => 'Conciliación',
                'card_titulo' => 'Conciliación bancaria — filtros y carátula',
                'card_color' => 'info',
                'breadcrumb' => 'Contable › Conciliación bancaria',
                'sidebar' => $sb,
                'tools' => ['Tablero', 'Aging', 'Worklist', 'Reglas', 'Manual', 'Excel'],
                'campos' => [
                    ['label' => 'Empresa', 'valor' => 'Biyemas S.A.'],
                    ['label' => 'Cuenta de caja', 'valor' => '127 — Macro CTA CTE'],
                    ['label' => 'Período', 'valor' => '10 / 2026'],
                    ['label' => 'Enganche', 'valor' => 'IB OK · cuenta 127'],
                ],
                'columnas' => ['Concepto', 'Importe'],
                'filas' => [
                    ['Saldo banco (extracto)', '12.450.320,55'],
                    ['Cheques no acreditados', '1.820.000,00'],
                    ['Pendientes banco', '245.110,22'],
                    ['Saldo banco ajustado', '10.385.210,33'],
                    ['Saldo contable', '10.385.205,10'],
                    ['Diferencia', '5,23'],
                ],
                'botones' => [['texto' => 'Conciliar', 'estilo' => 'primary']],
            ],
            'conc_solapas' => [
                'archivo' => 'conc-solapas.png',
                'modulo' => 'Contable',
                'pantalla' => 'Conciliación',
                'card_titulo' => 'Resultado — solapas Contaduría',
                'breadcrumb' => 'Contable › Conciliación › Octubre 2026',
                'sidebar' => $sb,
                'tabs' => ['Pendientes cheques', 'Cruce cheques IB', 'Pendientes otros', 'Saldo banco', 'Pendientes banco', 'Gastos'],
                'columnas' => ['Tip', 'Número', 'F.Dev.', 'Detalle', 'Importe', 'Carátula'],
                'filas' => [
                    ['CHP', '70507390', '15/10/2026', 'Proveedor Aceros', '380.000,00', 'Sí'],
                    ['CHP', '70507401', '22/10/2026', 'Servicios Norte', '125.500,00', 'Sí'],
                    ['ANULA', '70507012', '08/10/2026', 'Ya conciliado en banco', '90.000,00', 'Sí'],
                ],
            ],
            'conc_manual' => [
                'archivo' => 'conc-manual.png',
                'modulo' => 'Contable',
                'pantalla' => 'Manual',
                'card_titulo' => 'Conciliación manual — 127 Macro (10/2026)',
                'card_color' => 'info',
                'breadcrumb' => 'Contable › Conciliación › Manual',
                'sidebar' => $sb,
                'filtros' => ['Modo: Contaduría', 'Solo pendientes', 'Fecha: 15/10/2026'],
                'tools' => ['Traer del banco', 'Importar extracto', 'Sugerir par', 'Conciliar', 'Adjuntar', 'Cerrar período', 'Excel'],
                'alertas' => [
                    ['texto' => 'Evidencia: 0 adjuntos · Importar acepta Excel/CSV/OFX/CAMT.053/MT940', 'tipo' => 'info'],
                ],
                'columnas' => ['Lado', 'Fecha', 'Detalle', 'Importe', 'Estado'],
                'filas' => [
                    ['Mayor', '15/10', 'OPP #122853 Ch:70507390', '-380.000,00', 'Pendiente'],
                    ['Banco', '15/10', 'CH DEP 70507390', '-380.000,00', 'Pendiente'],
                    ['Mayor', '15/10', 'TRF.DATA sueldos', '-2.150.000,00', 'Pendiente'],
                    ['Banco', '16/10', 'TRF.DATA PAGREMU', '-2.150.000,00', 'Sugerido'],
                ],
                'botones' => [
                    ['texto' => 'Conciliar', 'estilo' => 'success'],
                    ['texto' => 'Limpiar', 'estilo' => 'outline'],
                ],
            ],
            'conc_multi' => [
                'archivo' => 'conc-multi.png',
                'modulo' => 'Contable',
                'pantalla' => 'Manual',
                'card_titulo' => 'Multi-match — totales de selección',
                'breadcrumb' => 'Contable › Conciliación › Manual',
                'sidebar' => $sb,
                'alertas' => [
                    ['texto' => 'Selección: 2 movimientos mayor + 1 banco. Diferencia 0,00 (dentro de tolerancia $0,05).', 'tipo' => 'success'],
                ],
                'columnas' => ['Selección', 'Cant.', 'Suma'],
                'filas' => [
                    ['anitaERP (mayor)', '2', '-425.110,22'],
                    ['Banco', '1', '-425.110,22'],
                    ['Diferencia', '—', '0,00'],
                ],
                'botones' => [['texto' => 'Conciliar grupo', 'estilo' => 'success']],
            ],
            'conc_cierre' => [
                'archivo' => 'conc-cierre.png',
                'modulo' => 'Contable',
                'pantalla' => 'Manual',
                'card_titulo' => 'Cierre, evidencia y aprobación (SoD)',
                'card_color' => 'warning',
                'breadcrumb' => 'Contable › Conciliación › Manual › 10/2026',
                'sidebar' => $sb,
                'alertas' => [
                    ['texto' => 'SoD: quien cerró no puede aprobar. Evidencia: caratula_oct.pdf · extracto_macro.xlsx', 'tipo' => 'info'],
                ],
                'campos' => [
                    ['label' => 'Estado', 'valor' => 'Cerrado'],
                    ['label' => 'Cerrado por', 'valor' => 'conta01 — 05/11/2026 18:40'],
                    ['label' => 'Pares del mes', 'valor' => '412'],
                    ['label' => 'Grupos multi', 'valor' => '17'],
                    ['label' => 'Diferencia carátula', 'valor' => '5,23'],
                    ['label' => 'Adjuntos', 'valor' => '2 (cierre)'],
                ],
                'botones' => [
                    ['texto' => 'Aprobar', 'estilo' => 'primary'],
                    ['texto' => 'Reabrir', 'estilo' => 'outline'],
                ],
            ],
            'conc_tablero' => [
                'archivo' => 'conc-tablero.png',
                'modulo' => 'Contable',
                'pantalla' => 'Tablero',
                'card_titulo' => 'Tablero de avance — octubre 2026',
                'card_color' => 'info',
                'breadcrumb' => 'Contable › Tablero conciliación bancaria',
                'sidebar' => $sb,
                'filtros' => ['Empresas: Biyemas + Kandiko', 'Mes: 10/2026'],
                'tools' => ['Pdf', 'Excel', 'Csv'],
                'columnas' => ['Código', 'Cuenta', 'Extracto', 'Pares', 'Grupos', 'Estado'],
                'filas' => [
                    ['127', 'Macro CTA CTE', 'IB/EXT', '412', '17', 'Aprobado'],
                    ['201', 'Galicia OP', 'IB/EXT', '188', '4', 'Cerrado'],
                    ['315', 'Nación sueldos', 'Sin extracto', '0', '0', 'Sin corrida'],
                ],
            ],
            'conc_aging' => [
                'archivo' => 'conc-aging.png',
                'modulo' => 'Contable',
                'pantalla' => 'Aging',
                'card_titulo' => 'Aging + forecast — cuenta 127',
                'card_color' => 'info',
                'breadcrumb' => 'Contable › Aging conciliación bancaria',
                'sidebar' => $sb,
                'alertas' => [
                    ['texto' => 'Forecast: 0–7 890.400 · 8–30 1.250.000 · 31–60 420.000 · 61+ 180.000 · total 2.740.400', 'tipo' => 'info'],
                ],
                'campos' => [
                    ['label' => 'Empresa', 'valor' => 'Biyemas S.A.'],
                    ['label' => 'Cuenta', 'valor' => '127 — Macro CTA CTE'],
                    ['label' => 'Período', 'valor' => '10/2026'],
                ],
                'columnas' => ['Bucket', 'Cant.', '|Importe|'],
                'filas' => [
                    ['0–7 días', '12', '890.400,00'],
                    ['8–30', '7', '1.250.000,00'],
                    ['31–60', '3', '420.000,00'],
                    ['61+', '2', '180.000,00'],
                    ['Total', '24', '2.740.400,00'],
                ],
                'tools' => ['Pdf', 'Excel', 'Csv', 'Worklist'],
                'botones' => [['texto' => 'Consultar', 'estilo' => 'primary']],
            ],
            'conc_worklist' => [
                'archivo' => 'conc-worklist.png',
                'modulo' => 'Contable',
                'pantalla' => 'Worklist',
                'card_titulo' => 'Worklist de excepciones — 127 Macro',
                'card_color' => 'warning',
                'breadcrumb' => 'Contable › Worklist excepciones bancarias',
                'sidebar' => $sb,
                'alertas' => [
                    ['texto' => 'Sync aging → worklist: +3 · upd 1 · abiertas 8', 'tipo' => 'info'],
                ],
                'filtros' => ['Estado: Abiertas', 'Mes: 10/2026'],
                'columnas' => ['Lado', 'Días', 'Detalle', 'Importe', 'Estado', 'Asignado'],
                'filas' => [
                    ['Banco', '45', 'TRF NN sin ref.', '320.000,00', 'Abierta', '—'],
                    ['Contable', '12', 'OPP #122900', '95.400,00', 'En curso', 'conta02'],
                    ['Banco', '62', 'COMISION MANT.', '18.200,00', 'Abierta', '—'],
                ],
                'botones' => [
                    ['texto' => 'Asignar', 'estilo' => 'outline'],
                    ['texto' => 'Resolver', 'estilo' => 'success'],
                ],
            ],
            'conc_config' => [
                'archivo' => 'conc-config.png',
                'modulo' => 'Contable',
                'pantalla' => 'Configuración',
                'card_titulo' => 'Configuración conciliación bancaria',
                'card_color' => 'primary',
                'breadcrumb' => 'Configuración › Contable › Conciliación bancaria',
                'sidebar' => $sb,
                'tabs' => ['Reglas', 'P.C.C.', 'Conceptos IB', 'Cód. operación', 'Patrones gasto', 'Tipo comp.', 'Doc. gastos', 'Residuales'],
                'campos' => [
                    ['label' => 'Tolerancia importe ($)', 'valor' => '0,05'],
                    ['label' => 'Días tolerancia fecha', 'valor' => '3'],
                    ['label' => 'Multi-match automático', 'valor' => 'Activo'],
                    ['label' => 'SoD aprobación', 'valor' => 'Activo'],
                    ['label' => 'Tipo caja egreso', 'valor' => 'EGR'],
                    ['label' => 'Tipo compra ICO', 'valor' => 'ICO'],
                ],
                'botones' => [['texto' => 'Guardar', 'estilo' => 'primary']],
            ],
            'conc_doc_gasto' => [
                'archivo' => 'conc-doc-gasto.png',
                'modulo' => 'Contable',
                'pantalla' => 'Configuración',
                'card_titulo' => 'Doc. gastos — cuenta o concepto IVA',
                'card_color' => 'primary',
                'breadcrumb' => 'Configuración › Conciliación › Doc. gastos',
                'sidebar' => $sb,
                'alertas' => [
                    ['texto' => 'Concepto IVA manda si está cargado. Si no, se usa la cuenta contable. Lupa / F1 para consultar.', 'tipo' => 'info'],
                ],
                'columnas' => ['Código', 'Cuenta', 'Concepto IVA', 'Etiqueta'],
                'filas' => [
                    ['2', '532050000', '—', 'Imp. débito/crédito'],
                    ['3', '—', '150', 'IIBB retención'],
                    ['4', '—', '140', 'IIBB percepción'],
                    ['7', '532040001', '—', 'Comisiones/intereses'],
                ],
                'botones' => [
                    ['texto' => 'Agrega renglón', 'estilo' => 'outline'],
                    ['texto' => 'Guardar', 'estilo' => 'primary'],
                ],
            ],
            'conc_gastos' => [
                'archivo' => 'conc-gastos.png',
                'modulo' => 'Contable',
                'pantalla' => 'Conciliación',
                'card_titulo' => 'Gastos bancarios + residuales',
                'breadcrumb' => 'Contable › Conciliación › Gastos',
                'sidebar' => $sb,
                'alertas' => [
                    ['texto' => 'Residuales clasificados (2): interes · fee — completá cuenta/concepto en Reglas → Residuales', 'tipo' => 'warning'],
                ],
                'columnas' => ['Código', 'Descripción', 'Importe', 'Cuenta / Concepto'],
                'filas' => [
                    ['2', 'IMPUESTO AL DEBITO/CREDITO', '185.420,10', '532050000'],
                    ['3', 'I BRUTOS RETENCION', '42.300,00', 'Concepto 150'],
                    ['7', 'COMISIONES/INTERESES/SELLOS', '98.750,55', '532040001'],
                ],
                'botones' => [['texto' => 'Generar egreso / ICO', 'estilo' => 'success']],
            ],
            'conc_alinear' => [
                'archivo' => 'conc-alinear.png',
                'modulo' => 'Contable',
                'pantalla' => 'Conciliación',
                'card_titulo' => 'Alinear Excel Contaduría',
                'breadcrumb' => 'Contable › Conciliación bancaria',
                'sidebar' => $sb,
                'modal' => [
                    'titulo' => 'Alinear Excel Contaduría 10/2026',
                    'texto' => 'Aceptar = APLICAR cambios. Cancelar = solo dry-run (sin grabar).',
                    'columnas' => ['Análisis', 'Cantidad'],
                    'filas' => [
                        ['Pares a crear', '38'],
                        ['Movimientos extracto', '210'],
                        ['Overrides Saldo', '12'],
                        ['Ya existentes (sin tocar)', '401'],
                    ],
                    'botones' => [
                        ['texto' => 'Aplicar', 'estilo' => 'primary'],
                        ['texto' => 'Solo dry-run', 'estilo' => 'outline'],
                    ],
                ],
            ],
        ];
    }
}
