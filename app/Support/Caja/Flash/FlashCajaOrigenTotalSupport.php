<?php

declare(strict_types=1);

namespace App\Support\Caja\Flash;

use App\Services\Caja\Flash\FlashCajaCalculoService;
use App\Support\Wigos\WigosSqlServerProcess;
use Carbon\Carbon;
use Throwable;

/**
 * Arma la explicación + cuenta + listado de movimientos Wigos/ERP de un total del Flash.
 */
final class FlashCajaOrigenTotalSupport
{
    /** @var array<string, array{titulo: string, origen: string, formula: string, explicacion: string, grupos_wigos: list<string>}> */
    private const CAMPOS = [
        'slot_d' => [
            'titulo' => 'Drop slots (slot_d)',
            'origen' => 'wigos+rendicion',
            'formula' => 'BillSlots + VentasSlots + VentasCaja + MontoNetoQR − ImpuestoDrop − ImpuestoVenta',
            'explicacion' => 'Drop financiero de slots del día. Bill y tickets entran brutos desde Wigos (solo turno M); QR entra neto; impuestos se restan una sola vez desde el turno completo (C) de rendmaquina.',
            'grupos_wigos' => ['drop', 'tickets_venta', 'qr'],
        ],
        'slot_r' => [
            'titulo' => 'Win slots (slot_r)',
            'origen' => 'wigos+rendicion',
            'formula' => 'BillSlots + VentasSlots + VentasCaja + MontoNetoQR − PagosSlots − PagosCaja − PagosManuales(M+T+N) − ImpuestoDrop − ImpuestoVenta',
            'explicacion' => 'Resultado / win financiero de slots. Misma base del drop menos pagos de tickets y pagos manuales de sesión, y menos impuestos del turno C.',
            'grupos_wigos' => ['drop', 'tickets_venta', 'tickets_pago', 'qr', 'sesiones'],
        ],
        'slot_coin_in' => [
            'titulo' => 'Coin in slots',
            'origen' => 'wigos',
            'formula' => 'Σ COIN_IN de SP_QlickView_Win_per_EGM (TIPO_TERMINAL = Slot)',
            'explicacion' => 'Coin-in on-line de slots del working day. Solo se toma del turno M (baseline).',
            'grupos_wigos' => ['win_egm'],
        ],
        'win_ol_slot' => [
            'titulo' => 'Win on-line slots',
            'origen' => 'wigos',
            'formula' => 'Σ WIN de SP_QlickView_Win_per_EGM (TIPO_TERMINAL = Slot)',
            'explicacion' => 'Win on-line de slots del working day (turno M). No se le restan impuestos de rendición.',
            'grupos_wigos' => ['win_egm'],
        ],
        'cant_slots' => [
            'titulo' => 'Cantidad slots',
            'origen' => 'wigos',
            'formula' => 'Cant. terminales TipoTerminal=1 − Cant. poker (TipoTerminal=3) en spDropDiarioPorTerminal',
            'explicacion' => 'Unidades de slots del día (baseline turno M), descontando poker.',
            'grupos_wigos' => ['drop'],
        ],
        'soft_count' => [
            'titulo' => 'Soft count / drop efectivo slots',
            'origen' => 'wigos',
            'formula' => 'BillSlots − BillPoker (spDropDiarioPorTerminal, turno M)',
            'explicacion' => 'Drop efectivo de billetes en slots menos poker. Es bruto del SP (sin impuesto_drop).',
            'grupos_wigos' => ['drop'],
        ],
        'hard_count' => [
            'titulo' => 'Hard count slots (tito)',
            'origen' => 'wigos',
            'formula' => 'Σ PagosTickets de spGananciaDeSalaPorSesion (turnos M+T+N) − tito poker',
            'explicacion' => 'Tito / pagos de tickets de sesión acumulados en el día. No es la venta de tickets.',
            'grupos_wigos' => ['sesiones'],
        ],
        'rul_d' => [
            'titulo' => 'Drop ruletas (rul_d)',
            'origen' => 'wigos',
            'formula' => 'BillRul + VentasRuletas',
            'explicacion' => 'Drop de ruletas electrónicas: billetes del SP drop + tickets de venta TerminalType=2 (turno M).',
            'grupos_wigos' => ['drop', 'tickets_venta'],
        ],
        'rul_r' => [
            'titulo' => 'Win ruletas (rul_r)',
            'origen' => 'wigos',
            'formula' => 'BillRul + VentasRuletas − PagosRuletas',
            'explicacion' => 'Win financiero de ruletas: drop menos pagos de tickets de ruleta.',
            'grupos_wigos' => ['drop', 'tickets_venta', 'tickets_pago'],
        ],
        'rul_coin_in' => [
            'titulo' => 'Coin in ruletas',
            'origen' => 'wigos',
            'formula' => 'Σ COIN_IN de SP_QlickView_Win_per_EGM (TIPO_TERMINAL = Ruleta)',
            'explicacion' => 'Coin-in on-line de ruletas (turno M).',
            'grupos_wigos' => ['win_egm'],
        ],
        'win_ol_rul' => [
            'titulo' => 'Win on-line ruletas',
            'origen' => 'wigos',
            'formula' => 'Σ WIN de SP_QlickView_Win_per_EGM (TIPO_TERMINAL = Ruleta)',
            'explicacion' => 'Win on-line de ruletas (turno M).',
            'grupos_wigos' => ['win_egm'],
        ],
        'cant_rul' => [
            'titulo' => 'Cantidad ruletas',
            'origen' => 'wigos',
            'formula' => 'Cant. terminales de ruleta en spDropDiarioPorTerminal (≠1 y ≠3)',
            'explicacion' => 'Unidades de ruleta electrónica del día (turno M).',
            'grupos_wigos' => ['drop'],
        ],
        'soft_rul' => [
            'titulo' => 'Soft count ruletas',
            'origen' => 'wigos',
            'formula' => 'BillRul (spDropDiarioPorTerminal, turno M)',
            'explicacion' => 'Drop efectivo de billetes en ruletas (bruto del SP).',
            'grupos_wigos' => ['drop'],
        ],
        'hard_rul' => [
            'titulo' => 'Hard count ruletas (tito)',
            'origen' => 'wigos',
            'formula' => 'Tito ruletas de sesión (M+T+N)',
            'explicacion' => 'Tito de ruletas acumulado por sesión.',
            'grupos_wigos' => ['sesiones'],
        ],
        'ayb' => [
            'titulo' => 'AyB (food & beverage)',
            'origen' => 'erp',
            'formula' => 'Neto gastronomía ERP del día (facturas − NC)',
            'explicacion' => 'No viene de Wigos. Se calcula desde ventas de gastronomía del ERP para la empresa/fecha.',
            'grupos_wigos' => [],
        ],
        'estac' => [
            'titulo' => 'Estacionamiento',
            'origen' => 'erp',
            'formula' => 'Σ (facturas − NC) de jornadas de estacionamiento cerradas del día',
            'explicacion' => 'No viene de Wigos. Totales de turnos operativos de estacionamiento ERP.',
            'grupos_wigos' => [],
        ],
        'vending' => [
            'titulo' => 'Vending',
            'origen' => 'erp',
            'formula' => 'Σ total_ventas de rendiciones maquinavending ERP del día',
            'explicacion' => 'No viene de Wigos ni de Anita flash. Campo propio de anitaERP.',
            'grupos_wigos' => [],
        ],
        'cant_vehic' => [
            'titulo' => 'Cantidad vehículos',
            'origen' => 'erp',
            'formula' => 'Σ cantidad_comprobantes de jornadas de estacionamiento del día',
            'explicacion' => 'No viene de Wigos. Contador de comprobantes de estacionamiento ERP.',
            'grupos_wigos' => [],
        ],
        'bingo_cant_carton' => [
            'titulo' => 'Cartones bingo',
            'origen' => 'erp',
            'formula' => 'Totales bingo ERP del día',
            'explicacion' => 'No viene de Wigos. Se resuelve desde rendiciones/comprobantes de bingo en ERP.',
            'grupos_wigos' => [],
        ],
        'bingo_total_venta' => [
            'titulo' => 'Ventas bingo',
            'origen' => 'erp',
            'formula' => 'Totales bingo ERP del día',
            'explicacion' => 'No viene de Wigos. Ventas de cartones bingo en ERP.',
            'grupos_wigos' => [],
        ],
        'bingo_resultado' => [
            'titulo' => 'Resultado bingo',
            'origen' => 'erp',
            'formula' => 'Totales bingo ERP del día',
            'explicacion' => 'No viene de Wigos. Resultado/win de bingo en ERP.',
            'grupos_wigos' => [],
        ],
    ];

    /**
     * @return list<string>
     */
    public static function camposSoportados(): array
    {
        return array_keys(self::CAMPOS);
    }

    /**
     * @return array<string, mixed>
     */
    public static function armar(int $empresaId, string $fecha, string $campo, ?float $valorPantalla = null): array
    {
        $campo = trim($campo);
        if (! isset(self::CAMPOS[$campo])) {
            throw new \InvalidArgumentException('Campo de flash no soportado para origen: '.$campo);
        }

        $meta = self::CAMPOS[$campo];
        $fechaSql = Carbon::parse($fecha)->format('Y-m-d');
        $fechaYmd = Carbon::parse($fecha)->format('Ymd');

        $calculoService = app(FlashCajaCalculoService::class);
        $calculado = $calculoService->calcular($empresaId, $fechaSql);
        $desglose = is_array($calculado['desglose_wigos'] ?? null) ? $calculado['desglose_wigos'] : [];
        $comp = is_array($desglose['componentes_aplicados'] ?? null) ? $desglose['componentes_aplicados'] : [];
        $imp = is_array($calculado['impuestos_rendicion'] ?? null) ? $calculado['impuestos_rendicion'] : [];

        $cuenta = self::armarCuenta($campo, $comp, $imp, $calculado);
        $totalFormula = self::valorFlash($campo, $calculado);
        $secciones = [];

        if ($meta['grupos_wigos'] !== []) {
            try {
                $detalle = WigosSqlServerProcess::ejecutarDetalleMovimientosFlash(
                    $fechaYmd,
                    $empresaId,
                    $meta['grupos_wigos'],
                );
                $secciones = self::seccionesDesdeDetalle($campo, $detalle);
            } catch (Throwable $e) {
                $secciones[] = [
                    'titulo' => 'Movimientos Wigos',
                    'nota' => 'No se pudo listar el detalle: '.$e->getMessage(),
                    'columnas' => [],
                    'filas' => [],
                    'subtotal' => null,
                    'truncado' => false,
                    'sp' => null,
                    'params' => null,
                ];
            }
        } else {
            $secciones[] = [
                'titulo' => 'Origen ERP',
                'nota' => $meta['explicacion'],
                'columnas' => [],
                'filas' => [],
                'subtotal' => $totalFormula,
                'truncado' => false,
                'sp' => null,
                'params' => null,
            ];
        }

        $sumaCuenta = 0.0;
        foreach ($cuenta as $linea) {
            $sumaCuenta += (float) ($linea['valor'] ?? 0);
        }
        $sumaCuenta = round($sumaCuenta, 2);
        $totalFormula = round((float) $totalFormula, 2);
        $valorPantallaNorm = $valorPantalla !== null ? round($valorPantalla, 2) : null;
        $diffPantalla = $valorPantallaNorm !== null
            ? round($valorPantallaNorm - $totalFormula, 2)
            : null;

        $aviso = null;
        if ($valorPantallaNorm !== null && abs((float) $diffPantalla) >= 0.02) {
            $aviso = 'El valor en pantalla ('.$valorPantallaNorm.') no coincide con el cálculo actual ERP ('
                .$totalFormula.'). Diferencia: '.$diffPantalla.'. '
                .'Si el flash se importó desde Anita o se editó a mano, use «Calcular desde ERP/Wigos» '
                .'para alinear el formulario con esta cuenta (Wigos + impuestos turno C).';
        }

        return [
            'campo' => $campo,
            'titulo' => $meta['titulo'],
            'origen' => $meta['origen'],
            'formula' => $meta['formula'],
            'explicacion' => $meta['explicacion'],
            'empresa_id' => $empresaId,
            'fecha' => $fechaSql,
            'cuenta' => $cuenta,
            'suma_cuenta' => $sumaCuenta,
            'total_formula' => $totalFormula,
            // Compat: el front usaba total_flash como total de la fórmula recalculada.
            'total_flash' => $totalFormula,
            'valor_pantalla' => $valorPantallaNorm,
            'diferencia_pantalla' => $diffPantalla,
            'coincide' => abs($sumaCuenta - $totalFormula) < 0.02,
            'coincide_pantalla' => $valorPantallaNorm === null
                || abs((float) $diffPantalla) < 0.02,
            'aviso' => $aviso,
            'impuestos_rendicion' => $imp,
            'secciones' => $secciones,
        ];
    }

    /**
     * @param  array<string, float|int>  $comp
     * @param  array<string, mixed>  $imp
     * @param  array<string, mixed>  $calculado
     * @return list<array{label: string, valor: float, signo: string}>
     */
    private static function armarCuenta(string $campo, array $comp, array $imp, array $calculado): array
    {
        $g = static fn (string $k): float => round((float) ($comp[$k] ?? 0), 2);
        $impDrop = round((float) ($imp['impuesto_drop'] ?? 0), 2);
        $impVenta = round((float) ($imp['impuesto_venta'] ?? 0), 2);

        return match ($campo) {
            'slot_d' => [
                self::linea('Bill slots (bruto)', $g('bill_slots'), '+'),
                self::linea('Ventas slots (tickets)', $g('ventas_slots'), '+'),
                self::linea('Ventas caja (tickets)', $g('ventas_caja'), '+'),
                self::linea('QR neto', $g('monto_neto_qr'), '+'),
                self::linea('Impuesto drop (turno C)', -$impDrop, '−'),
                self::linea('Impuesto venta (turno C)', -$impVenta, '−'),
            ],
            'slot_r' => [
                self::linea('Bill slots (bruto)', $g('bill_slots'), '+'),
                self::linea('Ventas slots (tickets)', $g('ventas_slots'), '+'),
                self::linea('Ventas caja (tickets)', $g('ventas_caja'), '+'),
                self::linea('QR neto', $g('monto_neto_qr'), '+'),
                self::linea('Pagos slots (tickets)', -$g('pagos_slots'), '−'),
                self::linea('Pagos caja (tickets)', -$g('pagos_caja'), '−'),
                self::linea('Pagos manuales (M+T+N)', -$g('pagos_manuales'), '−'),
                self::linea('Impuesto drop (turno C)', -$impDrop, '−'),
                self::linea('Impuesto venta (turno C)', -$impVenta, '−'),
            ],
            'rul_d' => [
                self::linea('Bill ruletas (bruto)', $g('bill_rul'), '+'),
                self::linea('Ventas ruletas (tickets)', $g('ventas_ruletas'), '+'),
            ],
            'rul_r' => [
                self::linea('Bill ruletas (bruto)', $g('bill_rul'), '+'),
                self::linea('Ventas ruletas (tickets)', $g('ventas_ruletas'), '+'),
                self::linea('Pagos ruletas (tickets)', -$g('pagos_ruletas'), '−'),
            ],
            'soft_count' => [
                self::linea('Bill slots', $g('bill_slots'), '+'),
                self::linea('Bill poker', -$g('bill_poker'), '−'),
            ],
            'soft_rul' => [
                self::linea('Bill ruletas', $g('bill_rul'), '+'),
            ],
            'slot_coin_in' => [
                self::linea('Coin in slots (OL)', $g('coin_in_slots'), '+'),
            ],
            'rul_coin_in' => [
                self::linea('Coin in ruletas (OL)', $g('coin_in_rul'), '+'),
            ],
            'win_ol_slot' => [
                self::linea('Win slots on-line', $g('win_slots'), '+'),
            ],
            'win_ol_rul' => [
                self::linea('Win ruletas on-line', $g('win_rul'), '+'),
            ],
            'hard_count' => [
                self::linea('Tito slots − poker', (float) ($calculado['hard_count'] ?? 0), '+'),
            ],
            'hard_rul' => [
                self::linea('Tito ruletas', (float) ($calculado['hard_rul'] ?? 0), '+'),
            ],
            'cant_slots' => [
                self::linea('Cant. slots', (float) ($calculado['cant_slots'] ?? 0), '+'),
            ],
            'cant_rul' => [
                self::linea('Cant. ruletas', (float) ($calculado['cant_rul'] ?? 0), '+'),
            ],
            default => [
                self::linea(self::CAMPOS[$campo]['titulo'] ?? $campo, (float) ($calculado[$campo] ?? 0), '+'),
            ],
        };
    }

    /**
     * @return array{label: string, valor: float, signo: string}
     */
    private static function linea(string $label, float $valor, string $signo): array
    {
        return [
            'label' => $label,
            'valor' => round($valor, 2),
            'signo' => $signo,
        ];
    }

    /**
     * @param  array<string, mixed>  $calculado
     */
    private static function valorFlash(string $campo, array $calculado): float
    {
        return round((float) ($calculado[$campo] ?? 0), 2);
    }

    /**
     * @param  array<string, mixed>  $detalle
     * @return list<array<string, mixed>>
     */
    private static function seccionesDesdeDetalle(string $campo, array $detalle): array
    {
        $grupos = is_array($detalle['grupos'] ?? null) ? $detalle['grupos'] : [];
        $secciones = [];

        if (isset($grupos['drop']) && in_array($campo, ['slot_d', 'slot_r', 'soft_count', 'cant_slots', 'rul_d', 'rul_r', 'soft_rul', 'cant_rul'], true)) {
            $g = $grupos['drop'];
            $filas = self::filtrarDrop($campo, $g['filas'] ?? []);
            $secciones[] = [
                'titulo' => 'Drop por terminal (spDropDiarioPorTerminal)',
                'nota' => 'Bill = suma de denominaciones B1…B20000. Tipo 1=Slot, 3=Poker, resto=Ruleta.',
                'sp' => $g['sp'] ?? null,
                'params' => $g['params'] ?? null,
                'columnas' => [
                    ['key' => 'terminal', 'label' => 'Terminal'],
                    ['key' => 'tipo_label', 'label' => 'Tipo'],
                    ['key' => 'bill', 'label' => 'Bill', 'num' => true],
                    ['key' => 'coin_in', 'label' => 'Coin in', 'num' => true],
                    ['key' => 'win', 'label' => 'Win daily', 'num' => true],
                ],
                'filas' => $filas,
                'subtotal' => round(array_sum(array_column($filas, 'bill')), 2),
                'truncado' => (bool) ($g['truncado'] ?? false),
            ];
        }

        if (isset($grupos['tickets_venta']) && in_array($campo, ['slot_d', 'slot_r', 'rul_d', 'rul_r'], true)) {
            $g = $grupos['tickets_venta'];
            $filas = self::filtrarTickets($campo, $g['filas'] ?? [], true);
            $secciones[] = [
                'titulo' => 'Ventas tickets (spTicketsDrop, pVentaPago=0)',
                'nota' => 'TicketAmount bruto. Caja=0, Slot=1, Ruleta=2. Solo turno M en el Flash.',
                'sp' => $g['sp'] ?? null,
                'params' => $g['params'] ?? null,
                'columnas' => [
                    ['key' => 'ticket', 'label' => 'Ticket'],
                    ['key' => 'terminal', 'label' => 'Terminal'],
                    ['key' => 'tipo_label', 'label' => 'Tipo'],
                    ['key' => 'monto', 'label' => 'Monto', 'num' => true],
                    ['key' => 'fecha', 'label' => 'Fecha'],
                ],
                'filas' => $filas,
                'subtotal' => round(array_sum(array_column($filas, 'monto')), 2),
                'truncado' => (bool) ($g['truncado'] ?? false),
            ];
        }

        if (isset($grupos['tickets_pago']) && in_array($campo, ['slot_r', 'rul_r'], true)) {
            $g = $grupos['tickets_pago'];
            $filas = self::filtrarTickets($campo, $g['filas'] ?? [], false);
            $secciones[] = [
                'titulo' => 'Pagos tickets (spTicketsDrop, pVentaPago=1)',
                'nota' => 'Canjes/pagos de tickets (incluye cancelaciones/pagos TITO del lado pago). Clasifica por TerminalTypeCreated.',
                'sp' => $g['sp'] ?? null,
                'params' => $g['params'] ?? null,
                'columnas' => [
                    ['key' => 'ticket', 'label' => 'Ticket'],
                    ['key' => 'terminal', 'label' => 'Terminal'],
                    ['key' => 'tipo_label', 'label' => 'Tipo'],
                    ['key' => 'monto', 'label' => 'Monto', 'num' => true],
                    ['key' => 'fecha', 'label' => 'Fecha'],
                ],
                'filas' => $filas,
                'subtotal' => round(array_sum(array_column($filas, 'monto')), 2),
                'truncado' => (bool) ($g['truncado'] ?? false),
            ];
        }

        if (isset($grupos['qr']) && in_array($campo, ['slot_d', 'slot_r'], true)) {
            $g = $grupos['qr'];
            $filas = $g['filas'] ?? [];
            $secciones[] = [
                'titulo' => 'Transferencias QR (SP_TransferenciasExternasAnita)',
                'nota' => 'Al Flash entra el neto (MontoTotal − Impuesto).',
                'sp' => $g['sp'] ?? null,
                'params' => $g['params'] ?? null,
                'columnas' => [
                    ['key' => 'referencia', 'label' => 'Referencia'],
                    ['key' => 'bruto', 'label' => 'Bruto', 'num' => true],
                    ['key' => 'impuesto', 'label' => 'Impuesto', 'num' => true],
                    ['key' => 'neto', 'label' => 'Neto', 'num' => true],
                ],
                'filas' => $filas,
                'subtotal' => round((float) ($g['subtotal_neto'] ?? array_sum(array_column($filas, 'neto'))), 2),
                'truncado' => (bool) ($g['truncado'] ?? false),
            ];
        }

        if (isset($grupos['win_egm']) && in_array($campo, ['slot_coin_in', 'win_ol_slot', 'rul_coin_in', 'win_ol_rul'], true)) {
            $g = $grupos['win_egm'];
            $filas = self::filtrarWinEgm($campo, $g['filas'] ?? []);
            $colMonto = str_contains($campo, 'coin') ? 'coin_in' : 'win';
            $secciones[] = [
                'titulo' => 'Win / Coin-in por EGM (SP_QlickView_Win_per_EGM)',
                'nota' => 'Filas del working day. Slot o Ruleta según el total consultado.',
                'sp' => $g['sp'] ?? null,
                'params' => $g['params'] ?? null,
                'columnas' => [
                    ['key' => 'terminal', 'label' => 'Terminal'],
                    ['key' => 'tipo', 'label' => 'Tipo'],
                    ['key' => 'coin_in', 'label' => 'Coin in', 'num' => true],
                    ['key' => 'win', 'label' => 'Win', 'num' => true],
                ],
                'filas' => $filas,
                'subtotal' => round(array_sum(array_column($filas, $colMonto)), 2),
                'truncado' => (bool) ($g['truncado'] ?? false),
            ];
        }

        if (isset($grupos['sesiones']) && in_array($campo, ['slot_r', 'hard_count', 'hard_rul'], true)) {
            $g = $grupos['sesiones'];
            $filas = $g['filas'] ?? [];
            $secciones[] = [
                'titulo' => 'Sesiones de sala (spGananciaDeSalaPorSesion)',
                'nota' => 'Pagos manuales y tito (PagosTickets) por turno M/T/N. El Flash acumula pagos manuales y tito en M+T+N.',
                'sp' => $g['sp'] ?? null,
                'params' => $g['params'] ?? null,
                'columnas' => [
                    ['key' => 'sesion', 'label' => 'Sesión'],
                    ['key' => 'turno', 'label' => 'Turno'],
                    ['key' => 'pagos_manuales', 'label' => 'Pagos man.', 'num' => true],
                    ['key' => 'pagos_tickets_tito', 'label' => 'Tito (pagos tickets)', 'num' => true],
                    ['key' => 'venta_tickets', 'label' => 'Venta tickets sesión', 'num' => true],
                ],
                'filas' => $filas,
                'subtotal' => $campo === 'slot_r'
                    ? round((float) ($g['subtotal_pagos_manuales'] ?? 0), 2)
                    : round((float) ($g['subtotal_tito'] ?? 0), 2),
                'truncado' => (bool) ($g['truncado'] ?? false),
            ];
        }

        return $secciones;
    }

    /**
     * @param  list<array<string, mixed>>  $filas
     * @return list<array<string, mixed>>
     */
    private static function filtrarDrop(string $campo, array $filas): array
    {
        return array_values(array_filter($filas, static function (array $f) use ($campo) {
            $tipo = (string) ($f['tipo'] ?? '');
            if (in_array($campo, ['slot_d', 'slot_r', 'soft_count', 'cant_slots'], true)) {
                return $tipo === '1' || $tipo === '3';
            }
            if (in_array($campo, ['rul_d', 'rul_r', 'soft_rul', 'cant_rul'], true)) {
                return $tipo !== '1' && $tipo !== '3';
            }

            return true;
        }));
    }

    /**
     * @param  list<array<string, mixed>>  $filas
     * @return list<array<string, mixed>>
     */
    private static function filtrarTickets(string $campo, array $filas, bool $_venta = true): array
    {
        return array_values(array_filter($filas, static function (array $f) use ($campo) {
            $tipo = (int) ($f['tipo'] ?? -1);
            if (in_array($campo, ['slot_d', 'slot_r'], true)) {
                return $tipo === 0 || $tipo === 1;
            }
            if (in_array($campo, ['rul_d', 'rul_r'], true)) {
                return $tipo === 2;
            }

            return true;
        }));
    }

    /**
     * @param  list<array<string, mixed>>  $filas
     * @return list<array<string, mixed>>
     */
    private static function filtrarWinEgm(string $campo, array $filas): array
    {
        $tipo = str_contains($campo, 'rul') ? 'Ruleta' : 'Slot';

        return array_values(array_filter(
            $filas,
            static fn (array $f) => (string) ($f['tipo'] ?? '') === $tipo
        ));
    }
}
