<?php

declare(strict_types=1);

namespace App\Support\Manuales\Escenas;

/** @return array<string, array<string, mixed>> */
final class CajaEscenas
{
    public static function todas(): array
    {
        $sb = ['Posición financiera', 'Flash', 'Rend. máquinas', 'Bingo', 'Cuentas caja'];

        return [
            'mapa_caja' => [
                'archivo' => 'mapa-caja.png',
                'modulo' => 'Caja',
                'pantalla' => 'Posición financiera',
                'card_titulo' => 'Mapa de módulos — área Caja / tesorería',
                'breadcrumb' => 'Caja › Manual › Mapa',
                'sidebar' => $sb,
                'nota' => 'Referencia de pantallas del manual de Caja para Biyemas.',
                'columnas' => ['Módulo', 'Pantalla principal', 'Propósito'],
                'filas' => [
                    ['Posición financiera', 'Consulta mensual', 'Saldos y movimientos por empresa'],
                    ['Flash de caja', 'Formulario diario', 'Totales operativos del día'],
                    ['Rendición máquinas', 'Carga por turno', 'Wigos / slots por terminal'],
                    ['Bingo', 'Rendición y pozo', 'Terminal bingo y acumulado'],
                    ['Cuentas de caja', 'ABM medios', 'Medios de cobro y cuentas contables'],
                    ['Cierres contables', 'Contable › Cierres', 'Asientos de presentación (manual aparte)'],
                ],
            ],
            'flujo_datos' => [
                'archivo' => 'flujo-datos.png',
                'modulo' => 'Caja',
                'pantalla' => 'Flash',
                'card_titulo' => 'Flujo diario de datos — Caja Biyemas',
                'breadcrumb' => 'Caja › Manual › Flujo',
                'sidebar' => $sb,
                'columnas' => ['Hora / momento', 'Origen', 'Destino ERP', 'Resultado'],
                'filas' => [
                    ['08:00', 'Apertura turno máquinas', 'Rendición máquinas', 'Carga turno C pendiente'],
                    ['12:00', 'Wigos / API externa', 'Rendición máquinas', 'Importe bruto y neto por terminal'],
                    ['18:00', 'Operador caja', 'Flash diario', 'Totales por rubro y medio'],
                    ['19:00', 'Consolidación', 'Posición financiera', 'Saldos actualizados mes'],
                    ['20:00', 'Contabilidad', 'Cierre rendiciones', 'Preview asiento → Anita'],
                ],
            ],
            'posicion_financiera' => [
                'archivo' => 'posicion-financiera.png',
                'modulo' => 'Caja',
                'pantalla' => 'Posición financiera',
                'card_titulo' => 'Posición financiera — consulta mensual',
                'breadcrumb' => 'Caja › Posición financiera',
                'sidebar' => $sb,
                'filtros' => ['Empresa: Biyemas', 'Mes: Agosto 2026', 'Moneda: ARS'],
                'tools' => ['Consultar', 'PDF', 'Excel'],
                'columnas' => ['Rubro', 'Saldo inicial', 'Ingresos', 'Egresos', 'Saldo final'],
                'filas' => [
                    ['Efectivo caja', '$ 850.000', '$ 4.820.000', '$ 4.650.000', '$ 1.020.000'],
                    ['Bancos', '$ 12.400.000', '$ 8.200.000', '$ 6.100.000', '$ 14.500.000'],
                    ['Cheques en cartera', '$ 2.100.000', '$ 980.000', '$ 1.450.000', '$ 1.630.000'],
                    ['Tarjetas / medios POS', '$ 0', '$ 3.410.000', '$ 3.280.000', '$ 130.000'],
                ],
            ],
            'flash_form' => [
                'archivo' => 'flash-form.png',
                'modulo' => 'Caja',
                'pantalla' => 'Flash',
                'card_titulo' => 'Flash de caja — formulario diario',
                'card_color' => 'primary',
                'breadcrumb' => 'Caja › Flash › 15/08/2026',
                'sidebar' => $sb,
                'campos' => [
                    ['label' => 'Empresa', 'valor' => 'Biyemas S.A.'],
                    ['label' => 'Fecha', 'valor' => '15/08/2026'],
                    ['label' => 'Usuario', 'valor' => 'caja01'],
                    ['label' => 'Estado', 'valor' => 'Borrador'],
                ],
                'columnas' => ['Rubro', 'Total declarado', 'Origen API', 'Diferencia'],
                'filas' => [
                    ['Slots / máquinas', '$ 1.245.800', '$ 1.245.800', '$ 0'],
                    ['Ruleta / mesas', '$ 382.500', '$ 380.000', '$ 2.500'],
                    ['Bingo terminal', '$ 98.200', '$ 98.200', '$ 0'],
                    ['Gastronomía POS', '$ 482.350', '$ 482.350', '$ 0'],
                ],
                'botones' => [
                    ['texto' => 'Guardar flash', 'estilo' => 'primary'],
                    ['texto' => 'Presentar', 'estilo' => 'success'],
                ],
            ],
            'flash_origen' => [
                'archivo' => 'flash-origen.png',
                'modulo' => 'Caja',
                'pantalla' => 'Flash',
                'card_titulo' => 'Flash diario — detalle rubro Slots / máquinas',
                'card_color' => 'primary',
                'breadcrumb' => 'Caja › Flash › Origen total',
                'sidebar' => $sb,
                'campos' => [
                    ['label' => 'Rubro', 'valor' => 'Slots / máquinas'],
                    ['label' => 'Total flash', 'valor' => '$ 1.245.800,00'],
                ],
                'modal' => [
                    'titulo' => 'Origen de total — API origen-total',
                    'texto' => 'Desglose automático desde rendiciones del día (Wigos / win_ol_slot).',
                    'columnas' => ['Terminal', 'Turno', 'Bruto', 'Neto', 'Fuente'],
                    'filas' => [
                        ['T-101', 'C', '$ 420.000', '$ 378.000', 'win_ol_slot'],
                        ['T-102', 'C', '$ 385.200', '$ 346.680', 'win_ol_slot'],
                        ['T-105', 'C', '$ 440.600', '$ 396.540', 'win_ol_slot'],
                        ['Ajuste manual', '—', '$ 0', '$ 124.580', 'Operador'],
                    ],
                    'botones' => [['texto' => 'Aceptar', 'estilo' => 'primary'], ['texto' => 'Cerrar', 'estilo' => 'outline']],
                ],
            ],
            'rendicion_maquina' => [
                'archivo' => 'rendicion-maquina.png',
                'modulo' => 'Caja',
                'pantalla' => 'Rend. máquinas',
                'card_titulo' => 'Rendición de máquinas — carga turno C',
                'card_color' => 'primary',
                'breadcrumb' => 'Caja › Rendición máquinas › Cargar',
                'sidebar' => $sb,
                'campos' => [
                    ['label' => 'Empresa', 'valor' => 'Biyemas S.A.'],
                    ['label' => 'Fecha', 'valor' => '15/08/2026'],
                    ['label' => 'Turno', 'valor' => 'C — Noche'],
                    ['label' => 'Terminal', 'valor' => 'T-101'],
                ],
                'columnas' => ['Concepto', 'Moneda', 'Bruto', 'Comisión', 'Neto'],
                'filas' => [
                    ['Coin in', 'ARS', '$ 520.000', '$ 52.000', '$ 468.000'],
                    ['Hand pay', 'ARS', '$ 12.000', '$ 0', '$ 12.000'],
                    ['Ticket out', 'ARS', '$ 88.000', '$ 0', '$ 88.000'],
                    ['Neto rendición', 'ARS', '—', '—', '$ 420.000'],
                ],
                'botones' => [['texto' => 'Guardar rendición', 'estilo' => 'primary']],
            ],
            'bingo_rendicion' => [
                'archivo' => 'bingo-rendicion.png',
                'modulo' => 'Caja',
                'pantalla' => 'Bingo',
                'card_titulo' => 'Bingo — carga de rendición en terminal',
                'card_color' => 'primary',
                'breadcrumb' => 'Caja › Bingo › Rendición',
                'sidebar' => $sb,
                'campos' => [
                    ['label' => 'Empresa', 'valor' => 'Biyemas S.A.'],
                    ['label' => 'Terminal bingo', 'valor' => 'BIN-01'],
                    ['label' => 'Sorteo / sesión', 'valor' => '15/08/2026 — Noche'],
                ],
                'columnas' => ['Concepto', 'Cartones', 'Importe', 'Medio cobro'],
                'filas' => [
                    ['Ventas cartones', '1.240', '$ 86.800', 'Efectivo / tarjeta'],
                    ['Premios pagados', '18', '$ 42.600', 'Efectivo'],
                    ['Comisión sala', '—', '$ 8.680', '—'],
                    ['Neto sesión', '—', '$ 35.520', '—'],
                ],
                'botones' => [['texto' => 'Confirmar rendición', 'estilo' => 'success']],
            ],
            'bingo_pozo' => [
                'archivo' => 'bingo-pozo.png',
                'modulo' => 'Caja',
                'pantalla' => 'Bingo',
                'card_titulo' => 'Bingo — pozo acumulado y presentación',
                'breadcrumb' => 'Caja › Bingo › Pozo',
                'sidebar' => $sb,
                'campos' => [
                    ['label' => 'Empresa', 'valor' => 'Biyemas S.A.'],
                    ['label' => 'Pozo actual', 'valor' => '$ 1.850.000,00'],
                    ['label' => 'Aporte sesión', 'valor' => '$ 62.680,00'],
                    ['label' => 'Estado', 'valor' => 'Abierto — pendiente cierre contable'],
                ],
                'columnas' => ['Fecha', 'Sesión', 'Aporte pozo', 'Premio mayor', 'Saldo pozo'],
                'filas' => [
                    ['14/08/2026', 'Noche', '$ 58.400', '$ 0', '$ 1.787.320'],
                    ['15/08/2026', 'Tarde', '$ 61.200', '$ 250.000', '$ 1.598.520'],
                    ['15/08/2026', 'Noche', '$ 62.680', '$ 0', '$ 1.850.000'],
                ],
                'alertas' => [
                    ['texto' => 'El pozo se presenta en caja y se contabiliza en el cierre BIN del módulo Contable.', 'tipo' => 'info'],
                ],
            ],
        ];
    }
}
