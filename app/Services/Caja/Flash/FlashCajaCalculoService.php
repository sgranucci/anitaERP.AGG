<?php

namespace App\Services\Caja\Flash;

use App\Models\Caja\Estacionamiento\JornadaEstacionamiento;
use App\Support\Caja\Flash\FlashCajaBingoTotalesSupport;
use App\Support\Caja\Flash\FlashCajaImpuestosRendicionSupport;
use App\Models\Configuracion\Sala;
use App\Support\Caja\Estacionamiento\EstacionamientoTurnoOperativoTotalesSupport;
use App\Support\Ventas\Gastronomia\GastronomiaConciliacionPorPcSupport;
use App\Support\Ventas\Gastronomia\GastronomiaConciliacionVendingRendgSupport;
use App\Support\Wigos\WigosSqlServerProcess;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Calcula campos del flash diario desde Wigos (slots/ruletas) + venta directa ERP
 * (AyB, estacionamiento y vending netos = facturas − NC) + ERP (bingo/vehículos).
 * Drop/win slots: resta impuesto_drop + impuesto_venta del turno completo (C)
 * de rendmaquina Anita (fallback ERP rendicion_maquina). AyB/estac/vending del ERP.
 */
final class FlashCajaCalculoService
{
    public function __construct(
        private readonly GastronomiaConciliacionPorPcSupport $porPcSupport,
        private readonly GastronomiaConciliacionVendingRendgSupport $vendingRendgSupport,
    ) {
    }
    /**
     * @return array<string, mixed>
     */
    public function calcular(int $empresaId, string $fecha): array
    {
        $fechaCarbon = Carbon::parse($fecha);
        $fechaYmd = $fechaCarbon->format('Ymd');
        $fechaSql = $fechaCarbon->format('Y-m-d');

        $acumulado = $this->estructuraVacia();
        $salas = Sala::query()
            ->where('empresa_id', $empresaId)
            ->whereNotNull('codigo')
            ->where('codigo', '!=', '')
            ->get(['id', 'codigo']);

        $erroresWigos = [];
        $salasOk = 0;
        $desgloseSalas = [];
        foreach ($salas as $sala) {
            try {
                $porSala = $this->calcularPorSala((int) $sala->codigo, $fechaYmd, $empresaId);
                $acumulado = $this->sumarFlash($acumulado, $porSala['flash']);
                $desgloseSalas[] = $porSala['desglose'];
                $salasOk++;
            } catch (Throwable $e) {
                $erroresWigos[] = 'sala '.$sala->codigo.': '.$e->getMessage();
                Log::warning('Flash Wigos sala '.$sala->codigo.': '.$e->getMessage(), [
                    'empresa_id' => $empresaId,
                    'fecha' => $fechaSql,
                ]);
            }
        }

        if ($salas->isNotEmpty() && $salasOk === 0) {
            throw new \RuntimeException(
                'No se pudo obtener gaming de Wigos. '.implode(' | ', $erroresWigos)
            );
        }

        $erp = $this->totalesAyBEstacBingo($empresaId, $fechaSql);
        $acumulado['ayb'] = $erp['ayb'];
        $acumulado['estac'] = $erp['estac'];
        $acumulado['vending'] = $erp['vending'];
        $acumulado['cant_vehic'] = $erp['cant_vehic'];
        $acumulado['bingo_cant_carton'] = $erp['bingo_cant_carton'];
        $acumulado['bingo_total_venta'] = $erp['bingo_total_venta'];
        $acumulado['bingo_resultado'] = $erp['bingo_resultado'];
        $acumulado['calculado_en'] = now()->toDateTimeString();
        if ($erroresWigos !== []) {
            $acumulado['advertencias_wigos'] = $erroresWigos;
        }

        $impuestos = FlashCajaImpuestosRendicionSupport::resolverDia($empresaId, $fechaSql);
        $descuentoImpuestos = (float) ($impuestos['total'] ?? 0);
        if ($descuentoImpuestos != 0.0) {
            $acumulado['slot_d'] = round((float) $acumulado['slot_d'] - $descuentoImpuestos, 2);
            $acumulado['slot_r'] = round((float) $acumulado['slot_r'] - $descuentoImpuestos, 2);
        }
        $acumulado['impuestos_rendicion'] = $impuestos;

        $acumulado['desglose_wigos'] = $this->armarDesgloseWigos(
            $desgloseSalas,
            $acumulado,
            $empresaId,
            $fechaSql,
            $impuestos,
        );

        return $acumulado;
    }

    /**
     * Gaming por sala — misma secuencia que calc_datos_wigos.pru.php + on_line.fc + a-flash.c:
     *
     * 1) Turno M: bill (spDropDiarioPorTerminal), coin/win (SP_QlickView_Win_per_EGM),
     *    tickets (spTicketsDrop venta/pago), QR (SP_TransferenciasExternasAnita).
     * 2) Turnos T/N: solo pagos_manuales y tito de sesión; bill/tickets/QR en 0.
     * 3) Fórmulas (por sala; luego a nivel empresa se restan impuestos rendición C):
     *    slot_d = Bill + VentasSlots + VentasCaja + MontoNetoQR − ImpDrop − ImpVenta
     *    slot_r = Bill + VentasSlots + VentasCaja + MontoNetoQR − Pagos… − ImpDrop − ImpVenta
     *    rul_d  = BillRul + VentasRuletas
     *    rul_r  = BillRul + VentasRuletas − PagosRuletas
     *
     * MontoNetoQR (MontoTotal − Impuesto) al drop y win de slots; no a ruletas.
     * ImpDrop/ImpVenta: una sola vez por día desde turno completo rendmaquina.
     *
     * @return array{flash: array<string, float|int>, desglose: array<string, mixed>}
     */
    private function calcularPorSala(int $salaCodigo, string $fechaYmd, int $empresaId): array
    {
        $flash = $this->estructuraVacia();

        // Como a-flash: baseline de mañana (bill/coin/win/tickets/QR) y luego acumula M+T+N.
        $baseline = WigosSqlServerProcess::ejecutarCalcDatosFlashTurno($fechaYmd, 'M', $empresaId);

        $flash['cant_rul'] = (int) ($baseline['units_rul'] ?? 0);
        $flash['cant_slots'] = max(0, (int) ($baseline['units_slots'] ?? 0) - (int) ($baseline['units_poker'] ?? 0));

        $turnosDesglose = [];
        $componentes = $this->estructuraComponentesWigos();

        foreach (['M', 'T', 'N'] as $turno) {
            $datos = $turno === 'M'
                ? $baseline
                : WigosSqlServerProcess::ejecutarCalcDatosFlashTurno($fechaYmd, $turno, $empresaId);

            $esManiana = $turno === 'M';

            $billSlots = $esManiana ? (float) ($baseline['bill_slots'] ?? 0) : 0.0;
            $billRul = $esManiana ? (float) ($baseline['bill_rul'] ?? 0) : 0.0;
            $billPoker = $esManiana ? (float) ($baseline['bill_poker'] ?? 0) : 0.0;
            $winSlots = $esManiana ? (float) ($baseline['win_slots'] ?? 0) : 0.0;
            $winRul = $esManiana ? (float) ($baseline['win_rul'] ?? 0) : 0.0;
            $coinInSlots = $esManiana ? (float) ($baseline['coin_in_slots'] ?? 0) : 0.0;
            $coinInRul = $esManiana ? (float) ($baseline['coin_in_rul'] ?? 0) : 0.0;
            $coinInPoker = $esManiana ? (float) ($baseline['coin_in_poker'] ?? 0) : 0.0;

            $ventasCaja = $esManiana ? (float) ($baseline['ventas_caja'] ?? 0) : 0.0;
            $ventasSlots = $esManiana ? (float) ($baseline['ventas_slots'] ?? 0) : 0.0;
            $ventasRuletas = $esManiana ? (float) ($baseline['ventas_ruletas'] ?? 0) : 0.0;
            $pagosCaja = $esManiana ? (float) ($baseline['pagos_caja'] ?? 0) : 0.0;
            $pagosSlots = $esManiana ? (float) ($baseline['pagos_slots'] ?? 0) : 0.0;
            $pagosRuletas = $esManiana ? (float) ($baseline['pagos_ruletas'] ?? 0) : 0.0;
            $montoQr = $esManiana ? (float) ($baseline['monto_qr'] ?? 0) : 0.0;
            $montoNetoQr = $esManiana ? (float) ($baseline['monto_neto_qr'] ?? 0) : 0.0;
            $impuestoQr = $esManiana ? (float) ($baseline['impuesto_qr'] ?? 0) : 0.0;

            $pagosManuales = (float) ($datos['pagos_manuales'] ?? 0);
            $titoSlots = (float) ($datos['tito_slots'] ?? 0);
            $titoRul = (float) ($datos['tito_rul'] ?? 0);
            $titoPoker = (float) ($datos['tito_poker'] ?? 0);

            $deltaSlotD = round($billSlots + $ventasSlots + $ventasCaja + $montoNetoQr, 2);
            $deltaSlotR = round($billSlots + $ventasSlots + $ventasCaja + $montoNetoQr - $pagosSlots - $pagosCaja - $pagosManuales, 2);
            $deltaRulD = round($ventasRuletas + $billRul, 2);
            $deltaRulR = round($billRul + $ventasRuletas - $pagosRuletas, 2);

            $flash['slot_d'] = round((float) $flash['slot_d'] + $deltaSlotD, 2);
            $flash['slot_r'] = round((float) $flash['slot_r'] + $deltaSlotR, 2);
            $flash['slot_coin_in'] = round((float) $flash['slot_coin_in'] + $coinInSlots - $coinInPoker, 2);
            $flash['win_ol_slot'] = round((float) $flash['win_ol_slot'] + $winSlots, 2);
            $flash['soft_count'] = round((float) $flash['soft_count'] + $billSlots - $billPoker, 2);
            $flash['hard_count'] = round((float) $flash['hard_count'] + $titoSlots - $titoPoker, 2);

            $flash['rul_d'] = round((float) $flash['rul_d'] + $deltaRulD, 2);
            $flash['rul_r'] = round((float) $flash['rul_r'] + $deltaRulR, 2);
            $flash['rul_coin_in'] = round((float) $flash['rul_coin_in'] + $coinInRul, 2);
            $flash['win_ol_rul'] = round((float) $flash['win_ol_rul'] + $winRul, 2);
            $flash['soft_rul'] = round((float) $flash['soft_rul'] + $billRul, 2);
            $flash['hard_rul'] = round((float) $flash['hard_rul'] + $titoRul, 2);

            $turnoFila = [
                'turno' => $turno,
                'aplica_bill_tickets_qr' => $esManiana,
                'bill_slots' => round($billSlots, 2),
                'bill_rul' => round($billRul, 2),
                'bill_poker' => round($billPoker, 2),
                'ventas_caja' => round($ventasCaja, 2),
                'ventas_slots' => round($ventasSlots, 2),
                'ventas_ruletas' => round($ventasRuletas, 2),
                'pagos_caja' => round($pagosCaja, 2),
                'pagos_slots' => round($pagosSlots, 2),
                'pagos_ruletas' => round($pagosRuletas, 2),
                'monto_qr' => round($montoQr, 2),
                'monto_neto_qr' => round($montoNetoQr, 2),
                'impuesto_qr' => round($impuestoQr, 2),
                'pagos_manuales' => round($pagosManuales, 2),
                'tito_slots' => round($titoSlots, 2),
                'tito_rul' => round($titoRul, 2),
                'tito_poker' => round($titoPoker, 2),
                'coin_in_slots' => round($coinInSlots, 2),
                'coin_in_rul' => round($coinInRul, 2),
                'coin_in_poker' => round($coinInPoker, 2),
                'win_slots' => round($winSlots, 2),
                'win_rul' => round($winRul, 2),
                'delta_slot_d' => $deltaSlotD,
                'delta_slot_r' => $deltaSlotR,
                'delta_rul_d' => $deltaRulD,
                'delta_rul_r' => $deltaRulR,
                'raw_wigos' => [
                    'bill_slots' => round((float) ($datos['bill_slots'] ?? 0), 2),
                    'bill_rul' => round((float) ($datos['bill_rul'] ?? 0), 2),
                    'bill_poker' => round((float) ($datos['bill_poker'] ?? 0), 2),
                    'ventas_caja' => round((float) ($datos['ventas_caja'] ?? 0), 2),
                    'ventas_slots' => round((float) ($datos['ventas_slots'] ?? 0), 2),
                    'ventas_ruletas' => round((float) ($datos['ventas_ruletas'] ?? 0), 2),
                    'pagos_caja' => round((float) ($datos['pagos_caja'] ?? 0), 2),
                    'pagos_slots' => round((float) ($datos['pagos_slots'] ?? 0), 2),
                    'pagos_ruletas' => round((float) ($datos['pagos_ruletas'] ?? 0), 2),
                    'monto_qr' => round((float) ($datos['monto_qr'] ?? 0), 2),
                    'monto_neto_qr' => round((float) ($datos['monto_neto_qr'] ?? 0), 2),
                    'impuesto_qr' => round((float) ($datos['impuesto_qr'] ?? 0), 2),
                    'pagos_manuales' => round((float) ($datos['pagos_manuales'] ?? 0), 2),
                    'tito_slots' => round((float) ($datos['tito_slots'] ?? 0), 2),
                    'tito_rul' => round((float) ($datos['tito_rul'] ?? 0), 2),
                    'tito_poker' => round((float) ($datos['tito_poker'] ?? 0), 2),
                    'coin_in_slots' => round((float) ($datos['coin_in_slots'] ?? 0), 2),
                    'coin_in_rul' => round((float) ($datos['coin_in_rul'] ?? 0), 2),
                    'coin_in_poker' => round((float) ($datos['coin_in_poker'] ?? 0), 2),
                    'win_slots' => round((float) ($datos['win_slots'] ?? 0), 2),
                    'win_rul' => round((float) ($datos['win_rul'] ?? 0), 2),
                    'units_slots' => (int) ($datos['units_slots'] ?? 0),
                    'units_rul' => (int) ($datos['units_rul'] ?? 0),
                    'units_poker' => (int) ($datos['units_poker'] ?? 0),
                ],
            ];
            $turnosDesglose[] = $turnoFila;

            foreach (array_keys($componentes) as $clave) {
                if (! array_key_exists($clave, $turnoFila)) {
                    continue;
                }
                $componentes[$clave] = round((float) $componentes[$clave] + (float) $turnoFila[$clave], 2);
            }
        }

        return [
            'flash' => $flash,
            'desglose' => [
                'sala' => $salaCodigo,
                'cant_slots' => $flash['cant_slots'],
                'cant_rul' => $flash['cant_rul'],
                'componentes_aplicados' => $componentes,
                'totales_sala' => [
                    'slot_d' => $flash['slot_d'],
                    'slot_r' => $flash['slot_r'],
                    'slot_coin_in' => $flash['slot_coin_in'],
                    'win_ol_slot' => $flash['win_ol_slot'],
                    'soft_count' => $flash['soft_count'],
                    'hard_count' => $flash['hard_count'],
                    'rul_d' => $flash['rul_d'],
                    'rul_r' => $flash['rul_r'],
                    'rul_coin_in' => $flash['rul_coin_in'],
                    'win_ol_rul' => $flash['win_ol_rul'],
                    'soft_rul' => $flash['soft_rul'],
                    'hard_rul' => $flash['hard_rul'],
                ],
                'turnos' => $turnosDesglose,
            ],
        ];
    }

    /**
     * @return array<string, float>
     */
    private function estructuraComponentesWigos(): array
    {
        return [
            'bill_slots' => 0.0,
            'bill_rul' => 0.0,
            'bill_poker' => 0.0,
            'ventas_caja' => 0.0,
            'ventas_slots' => 0.0,
            'ventas_ruletas' => 0.0,
            'pagos_caja' => 0.0,
            'pagos_slots' => 0.0,
            'pagos_ruletas' => 0.0,
            'monto_qr' => 0.0,
            'monto_neto_qr' => 0.0,
            'impuesto_qr' => 0.0,
            'pagos_manuales' => 0.0,
            'tito_slots' => 0.0,
            'tito_rul' => 0.0,
            'tito_poker' => 0.0,
            'coin_in_slots' => 0.0,
            'coin_in_rul' => 0.0,
            'coin_in_poker' => 0.0,
            'win_slots' => 0.0,
            'win_rul' => 0.0,
        ];
    }

    /**
     * Documentación de origen Wigos de cada componente (para modal / Excel desglose).
     *
     * @param  array<string, float>  $componentes
     * @param  array<string, mixed>  $impuestos
     * @return list<array<string, mixed>>
     */
    private function origenComponentesWigos(array $componentes, string $fechaSql, array $impuestos = []): array
    {
        $fechaHasta = Carbon::parse($fechaSql)->addDay()->format('Y-m-d');
        $ventasCaja = round((float) ($componentes['ventas_caja'] ?? 0), 2);
        $ventasSlots = round((float) ($componentes['ventas_slots'] ?? 0), 2);
        $ventasRuletas = round((float) ($componentes['ventas_ruletas'] ?? 0), 2);
        $billSlots = round((float) ($componentes['bill_slots'] ?? 0), 2);
        $billRul = round((float) ($componentes['bill_rul'] ?? 0), 2);
        $billPoker = round((float) ($componentes['bill_poker'] ?? 0), 2);
        $montoQr = round((float) ($componentes['monto_qr'] ?? 0), 2);
        $montoNetoQr = round((float) ($componentes['monto_neto_qr'] ?? 0), 2);
        $impuestoQr = round((float) ($componentes['impuesto_qr'] ?? 0), 2);
        $impDrop = round((float) ($impuestos['impuesto_drop'] ?? 0), 2);
        $impVenta = round((float) ($impuestos['impuesto_venta'] ?? 0), 2);
        $origenImp = (string) ($impuestos['origen'] ?? 'ninguno');
        $nroOper = $impuestos['nro_oper'] ?? null;

        return [
            [
                'clave' => 'bill_slots',
                'etiqueta' => 'Drop efectivo billetes slots (bill_slots)',
                'sp' => 'spDropDiarioPorTerminal',
                'params' => '@Date = '.$fechaSql,
                'filtro' => 'TipoTerminal = 1 (slots)',
                'campo_monto' => 'Suma de denominaciones B1+B2+…+B20000 (= columna Total)',
                'base' => 'BRUTO',
                'nota' => 'El SP no expone impuesto_drop ni columna neto. soft_count = bill_slots − bill_poker. '
                    .'El neto de drop se obtiene restando impuesto_drop del turno C de rendmaquina (ver Impuesto drop).',
                'valor' => $billSlots,
            ],
            [
                'clave' => 'bill_rul',
                'etiqueta' => 'Drop efectivo billetes ruletas (bill_rul)',
                'sp' => 'spDropDiarioPorTerminal',
                'params' => '@Date = '.$fechaSql,
                'filtro' => 'TipoTerminal ≠ 1 y ≠ 3 (ruletas)',
                'campo_monto' => 'Suma de denominaciones B1+B2+…+B20000 (= columna Total)',
                'base' => 'BRUTO',
                'nota' => 'Misma fuente que bill_slots; soft_rul = bill_rul.',
                'valor' => $billRul,
            ],
            [
                'clave' => 'bill_poker',
                'etiqueta' => 'Bill poker (se resta del soft_count slots)',
                'sp' => 'spDropDiarioPorTerminal',
                'params' => '@Date = '.$fechaSql,
                'filtro' => 'TipoTerminal = 3 (poker)',
                'campo_monto' => 'Suma de denominaciones B1+B2+…+B20000',
                'base' => 'BRUTO',
                'nota' => 'Se resta en soft_count; no suma a slot_d por separado (va dentro de bill_slots si el SP lo agrupa en tipo 1 — aquí tipo 3).',
                'valor' => $billPoker,
            ],
            [
                'clave' => 'ventas_caja',
                'etiqueta' => 'Ventas caja (tickets)',
                'sp' => 'spTicketsDrop',
                'params' => '@pStart = '.$fechaSql.', @pEnd = '.$fechaHasta.', @pVentaPago = 0 (ventas)',
                'filtro' => 'TerminalType = 0 (caja)',
                'campo_monto' => 'TicketAmount',
                'base' => 'BRUTO',
                'nota' => 'Solo turno M. Rango [fecha, fecha+1). Entra bruto a slot_d / slot_r; '
                    .'impuesto_venta del turno C se resta una sola vez a nivel día.',
                'valor' => $ventasCaja,
            ],
            [
                'clave' => 'ventas_slots',
                'etiqueta' => 'Ventas slots (tickets)',
                'sp' => 'spTicketsDrop',
                'params' => '@pStart = '.$fechaSql.', @pEnd = '.$fechaHasta.', @pVentaPago = 0 (ventas)',
                'filtro' => 'TerminalType = 1 (slots)',
                'campo_monto' => 'TicketAmount',
                'base' => 'BRUTO',
                'nota' => 'Solo turno M. Entra bruto a slot_d / slot_r; impuesto_venta del turno C se resta aparte.',
                'valor' => $ventasSlots,
            ],
            [
                'clave' => 'ventas_ruletas',
                'etiqueta' => 'Ventas ruletas (tickets)',
                'sp' => 'spTicketsDrop',
                'params' => '@pStart = '.$fechaSql.', @pEnd = '.$fechaHasta.', @pVentaPago = 0 (ventas)',
                'filtro' => 'TerminalType = 2 (ruletas)',
                'campo_monto' => 'TicketAmount',
                'base' => 'BRUTO',
                'nota' => 'Solo turno M. Entra en rul_d / rul_r con bill_rul. Impuesto rendmaquina se aplica a slots, no a ruletas.',
                'valor' => $ventasRuletas,
            ],
            [
                'clave' => 'ventas_tickets_suma',
                'etiqueta' => 'Suma ventas tickets (caja + slots + ruletas)',
                'sp' => 'spTicketsDrop (@pVentaPago = 0)',
                'params' => '@pStart = '.$fechaSql.', @pEnd = '.$fechaHasta,
                'filtro' => 'TerminalType ∈ {0,1,2}',
                'campo_monto' => 'TicketAmount',
                'base' => 'BRUTO',
                'nota' => 'ventas_caja + ventas_slots + ventas_ruletas. El impuesto_venta del turno C se resta de slot_d / slot_r.',
                'valor' => round($ventasCaja + $ventasSlots + $ventasRuletas, 2),
            ],
            [
                'clave' => 'monto_neto_qr',
                'etiqueta' => 'Monto neto QR (drop QR rodillo)',
                'sp' => 'SP_TransferenciasExternasAnita',
                'params' => '@pFechaIni = '.$fechaSql.', @pfechaFin = '.$fechaHasta,
                'filtro' => 'Todas las transferencias del rango (el SP suele devolver totales agregados)',
                'campo_monto' => 'MontoTotal − Impuesto (por fila)',
                'base' => 'NETO',
                'nota' => 'Único componente de drop que ya entra neto. Bruto='.$montoQr.' · Impuesto='.$impuestoQr.' · Neto='.$montoNetoQr.'. '
                    .'Se suma a slot_d / slot_r; no a ruletas.',
                'valor' => $montoNetoQr,
            ],
            [
                'clave' => 'impuesto_drop',
                'etiqueta' => 'Impuesto drop (rendición máquinas turno C)',
                'sp' => $origenImp === 'erp' ? 'rendicion_maquina.inputs_json' : 'rendmaquina.rendm_imp_drop',
                'params' => 'empresa/fecha · turno C · origen='.$origenImp
                    .($nroOper !== null ? ' · nro_oper='.$nroOper : ''),
                'filtro' => 'Solo turno completo (C); no suma M+T+N',
                'campo_monto' => 'rendm_imp_drop / inputs.impuesto_drop',
                'base' => 'DESCUENTO',
                'nota' => 'Se resta una vez del día a slot_d y slot_r. Preferencia Anita; si no hay C en Anita, ERP.',
                'valor' => $impDrop,
            ],
            [
                'clave' => 'impuesto_venta',
                'etiqueta' => 'Impuesto venta (rendición máquinas turno C)',
                'sp' => $origenImp === 'erp' ? 'rendicion_maquina.inputs_json' : 'rendmaquina.rendm_imp_venta',
                'params' => 'empresa/fecha · turno C · origen='.$origenImp
                    .($nroOper !== null ? ' · nro_oper='.$nroOper : ''),
                'filtro' => 'Solo turno completo (C); no suma M+T+N',
                'campo_monto' => 'rendm_imp_venta / inputs.impuesto_venta',
                'base' => 'DESCUENTO',
                'nota' => 'Se resta una vez del día a slot_d y slot_r junto con impuesto_drop.',
                'valor' => $impVenta,
            ],
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $desgloseSalas
     * @param  array<string, mixed>  $acumulado
     * @param  array{
     *   impuesto_drop?: float,
     *   impuesto_venta?: float,
     *   total?: float,
     *   origen?: string,
     *   nro_oper?: ?int,
     *   rendicion_id?: ?int
     * }  $impuestos
     * @return array<string, mixed>
     */
    private function armarDesgloseWigos(
        array $desgloseSalas,
        array $acumulado,
        int $empresaId,
        string $fechaSql,
        array $impuestos = [],
    ): array {
        $componentes = $this->estructuraComponentesWigos();
        foreach ($desgloseSalas as $salaDesglose) {
            foreach ($salaDesglose['componentes_aplicados'] ?? [] as $clave => $valor) {
                if (! array_key_exists($clave, $componentes)) {
                    continue;
                }
                $componentes[$clave] = round((float) $componentes[$clave] + (float) $valor, 2);
            }
        }

        $c = $componentes;
        $impDrop = round((float) ($impuestos['impuesto_drop'] ?? 0), 2);
        $impVenta = round((float) ($impuestos['impuesto_venta'] ?? 0), 2);
        $impTotal = round((float) ($impuestos['total'] ?? ($impDrop + $impVenta)), 2);
        $origen = $this->origenComponentesWigos($c, $fechaSql, $impuestos);

        return [
            'empresa_id' => $empresaId,
            'fecha' => $fechaSql,
            'formulas' => [
                'slot_d' => 'BillSlots(bruto) + VentasSlots(bruto) + VentasCaja(bruto) + MontoNetoQR − ImpDrop − ImpVenta (turno C)',
                'slot_r' => 'BillSlots(bruto) + VentasSlots(bruto) + VentasCaja(bruto) + MontoNetoQR − PagosSlots − PagosCaja − PagosManuales(M+T+N) − ImpDrop − ImpVenta (turno C)',
                'slot_coin_in' => 'CoinInSlots − CoinInPoker (solo turno M)',
                'win_ol_slot' => 'WinSlots (solo turno M)',
                'soft_count' => 'BillSlots(bruto) − BillPoker (solo turno M) — soft count / drop efectivo billetes',
                'hard_count' => 'TitoSlots − TitoPoker (M+T+N)',
                'rul_d' => 'BillRul(bruto) + VentasRuletas(bruto)',
                'rul_r' => 'BillRul(bruto) + VentasRuletas(bruto) − PagosRuletas',
                'rul_coin_in' => 'CoinInRul (solo turno M)',
                'win_ol_rul' => 'WinRul (solo turno M)',
                'soft_rul' => 'BillRul(bruto) (solo turno M)',
                'hard_rul' => 'TitoRul (M+T+N)',
                'nota' => 'Bill / tickets / QR solo turno M. Pagos manuales y tito acumulan M+T+N. '
                    .'Drop efectivo y ventas tickets entran BRUTO desde Wigos; luego se restan una sola vez '
                    .'impuesto_drop + impuesto_venta del turno completo (C) de rendmaquina (Anita, fallback ERP). '
                    .'Solo el QR entra ya neto desde el SP. Ver sección Origen de componentes.',
            ],
            'impuestos_rendicion' => [
                'impuesto_drop' => $impDrop,
                'impuesto_venta' => $impVenta,
                'total' => $impTotal,
                'origen' => (string) ($impuestos['origen'] ?? 'ninguno'),
                'nro_oper' => $impuestos['nro_oper'] ?? null,
                'rendicion_id' => $impuestos['rendicion_id'] ?? null,
            ],
            'origen_componentes' => $origen,
            'componentes_aplicados' => $c,
            'verificacion' => [
                'slot_d' => [
                    'formula' => 'BillSlots + VentasSlots + VentasCaja + MontoNetoQR − ImpDrop − ImpVenta',
                    'partes' => [
                        'bill_slots' => $c['bill_slots'],
                        'ventas_slots' => $c['ventas_slots'],
                        'ventas_caja' => $c['ventas_caja'],
                        'monto_neto_qr' => $c['monto_neto_qr'],
                        'impuesto_drop' => -$impDrop,
                        'impuesto_venta' => -$impVenta,
                    ],
                    'suma_partes' => round(
                        $c['bill_slots'] + $c['ventas_slots'] + $c['ventas_caja'] + $c['monto_neto_qr'] - $impTotal,
                        2
                    ),
                    'total_flash' => round((float) ($acumulado['slot_d'] ?? 0), 2),
                ],
                'slot_r' => [
                    'formula' => 'BillSlots + VentasSlots + VentasCaja + MontoNetoQR − PagosSlots − PagosCaja − PagosManuales − ImpDrop − ImpVenta',
                    'partes' => [
                        'bill_slots' => $c['bill_slots'],
                        'ventas_slots' => $c['ventas_slots'],
                        'ventas_caja' => $c['ventas_caja'],
                        'monto_neto_qr' => $c['monto_neto_qr'],
                        'pagos_slots' => $c['pagos_slots'],
                        'pagos_caja' => $c['pagos_caja'],
                        'pagos_manuales' => $c['pagos_manuales'],
                        'impuesto_drop' => -$impDrop,
                        'impuesto_venta' => -$impVenta,
                    ],
                    'suma_partes' => round(
                        $c['bill_slots'] + $c['ventas_slots'] + $c['ventas_caja'] + $c['monto_neto_qr']
                        - $c['pagos_slots'] - $c['pagos_caja'] - $c['pagos_manuales'] - $impTotal,
                        2
                    ),
                    'total_flash' => round((float) ($acumulado['slot_r'] ?? 0), 2),
                ],
                'rul_d' => [
                    'formula' => 'BillRul + VentasRuletas',
                    'partes' => [
                        'bill_rul' => $c['bill_rul'],
                        'ventas_ruletas' => $c['ventas_ruletas'],
                    ],
                    'suma_partes' => round($c['bill_rul'] + $c['ventas_ruletas'], 2),
                    'total_flash' => round((float) ($acumulado['rul_d'] ?? 0), 2),
                ],
                'rul_r' => [
                    'formula' => 'BillRul + VentasRuletas − PagosRuletas',
                    'partes' => [
                        'bill_rul' => $c['bill_rul'],
                        'ventas_ruletas' => $c['ventas_ruletas'],
                        'pagos_ruletas' => $c['pagos_ruletas'],
                    ],
                    'suma_partes' => round($c['bill_rul'] + $c['ventas_ruletas'] - $c['pagos_ruletas'], 2),
                    'total_flash' => round((float) ($acumulado['rul_r'] ?? 0), 2),
                ],
            ],
            'totales_gaming' => [
                'slot_d' => round((float) ($acumulado['slot_d'] ?? 0), 2),
                'slot_r' => round((float) ($acumulado['slot_r'] ?? 0), 2),
                'slot_coin_in' => round((float) ($acumulado['slot_coin_in'] ?? 0), 2),
                'win_ol_slot' => round((float) ($acumulado['win_ol_slot'] ?? 0), 2),
                'soft_count' => round((float) ($acumulado['soft_count'] ?? 0), 2),
                'hard_count' => round((float) ($acumulado['hard_count'] ?? 0), 2),
                'cant_slots' => (int) ($acumulado['cant_slots'] ?? 0),
                'rul_d' => round((float) ($acumulado['rul_d'] ?? 0), 2),
                'rul_r' => round((float) ($acumulado['rul_r'] ?? 0), 2),
                'rul_coin_in' => round((float) ($acumulado['rul_coin_in'] ?? 0), 2),
                'win_ol_rul' => round((float) ($acumulado['win_ol_rul'] ?? 0), 2),
                'soft_rul' => round((float) ($acumulado['soft_rul'] ?? 0), 2),
                'hard_rul' => round((float) ($acumulado['hard_rul'] ?? 0), 2),
                'cant_rul' => (int) ($acumulado['cant_rul'] ?? 0),
            ],
            'salas' => $desgloseSalas,
        ];
    }

    /**
     * AyB, estacionamiento y vending netos (facturas − NC) desde venta directa ERP.
     * AyB = Σ emisiones gastronómicas del día (salón + post-cierre + agregados) netas.
     * Estac = Σ (facturas − NC) por jornada de estacionamiento cerrada.
     * Vending = Σ MaquinavendingRendicion.total_ventas del día.
     * Bingo desde ERP. Sin consultar rendgastro/Anita.
     *
     * @return array{ayb: float, estac: float, vending: float, cant_vehic: int, bingo_cant_carton: int, bingo_total_venta: float, bingo_resultado: float}
     */
    private function totalesAyBEstacBingo(int $empresaId, string $fechaSql): array
    {
        $ayb = 0.0;
        try {
            $ayb = round((float) ($this->porPcSupport->totalErpNetoGastronomiaDia($empresaId, $fechaSql)['neto'] ?? 0), 2);
        } catch (Throwable $e) {
            Log::warning('Flash AyB ERP '.$fechaSql.': '.$e->getMessage(), [
                'empresa_id' => $empresaId,
            ]);
        }

        $vending = $this->totalVending($empresaId, $fechaSql);

        $bingo = FlashCajaBingoTotalesSupport::resolver($empresaId, $fechaSql);

        $estac = 0.0;
        $cantVehic = 0;
        $jornadas = JornadaEstacionamiento::query()
            ->where('empresa_id', $empresaId)
            ->whereDate('fecha_jornada', $fechaSql)
            ->whereNotNull('apertura_en')
            ->whereNotNull('cierre_en')
            ->get();

        foreach ($jornadas as $jornada) {
            try {
                $totales = EstacionamientoTurnoOperativoTotalesSupport::calcularPorJornada($jornada);
                $cantVehic += (int) ($totales['cantidad_comprobantes'] ?? 0);
                $facturas = round((float) ($totales['total_facturas'] ?? 0), 2);
                $notasCredito = round(abs((float) ($totales['total_notas_credito'] ?? 0)), 2);
                $estac = round($estac + $facturas - $notasCredito, 2);
            } catch (Throwable $e) {
                Log::warning('Flash estacionamiento jornada '.$jornada->id.': '.$e->getMessage());
            }
        }

        return [
            'ayb' => $ayb,
            'estac' => $estac,
            'vending' => $vending,
            'cant_vehic' => $cantVehic,
            'bingo_cant_carton' => $bingo['bingo_cant_carton'],
            'bingo_total_venta' => $bingo['bingo_total_venta'],
            'bingo_resultado' => $bingo['bingo_resultado'],
        ];
    }

    /**
     * Ventas vending del día (Σ MaquinavendingRendicion.total_ventas por jornada ERP).
     */
    private function totalVending(int $empresaId, string $fechaSql): float
    {
        try {
            $map = $this->vendingRendgSupport->totalesMaquinavendingErpPorJornada($empresaId, $fechaSql, $fechaSql);

            return round((float) ($map[$fechaSql] ?? 0), 2);
        } catch (Throwable $e) {
            Log::warning('Flash vending '.$fechaSql.': '.$e->getMessage(), [
                'empresa_id' => $empresaId,
            ]);

            return 0.0;
        }
    }

    /**
     * @return array<string, float|int>
     */
    private function estructuraVacia(): array
    {
        return [
            'ayb' => 0.0,
            'slot_coin_in' => 0.0,
            'slot_d' => 0.0,
            'slot_r' => 0.0,
            'soft_count' => 0.0,
            'hard_count' => 0.0,
            'cant_slots' => 0,
            'rul_coin_in' => 0.0,
            'rul_d' => 0.0,
            'rul_r' => 0.0,
            'soft_rul' => 0.0,
            'hard_rul' => 0.0,
            'cant_rul' => 0,
            'bingo_cant_carton' => 0,
            'bingo_total_venta' => 0.0,
            'bingo_resultado' => 0.0,
            'win_ol_slot' => 0.0,
            'win_ol_rul' => 0.0,
            'estac' => 0.0,
            'vending' => 0.0,
            'cant_vehic' => 0,
            'show' => 0.0,
        ];
    }

    /**
     * @param  array<string, float|int>  $base
     * @param  array<string, float|int>  $extra
     * @return array<string, float|int>
     */
    private function sumarFlash(array $base, array $extra): array
    {
        foreach ($extra as $key => $valor) {
            if (! array_key_exists($key, $base)) {
                $base[$key] = $valor;
                continue;
            }
            if (is_int($base[$key]) || is_int($valor)) {
                $base[$key] = (int) $base[$key] + (int) $valor;
            } else {
                $base[$key] = round((float) $base[$key] + (float) $valor, 2);
            }
        }

        return $base;
    }
}
