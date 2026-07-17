<?php

namespace App\Services\Caja\Flash;

use App\Models\Caja\Estacionamiento\JornadaEstacionamiento;
use App\Support\Caja\Flash\FlashCajaBingoTotalesSupport;
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
 * No consulta rendgastro/Anita: la venta del flash sale íntegramente del ERP.
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

        foreach ($salas as $sala) {
            try {
                $salaFlash = $this->calcularPorSala((int) $sala->codigo, $fechaYmd, $empresaId);
                $acumulado = $this->sumarFlash($acumulado, $salaFlash);
            } catch (Throwable $e) {
                Log::warning('Flash Wigos sala '.$sala->codigo.': '.$e->getMessage(), [
                    'empresa_id' => $empresaId,
                    'fecha' => $fechaSql,
                ]);
            }
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

        return $acumulado;
    }

    /**
     * @return array<string, float|int>
     */
    private function calcularPorSala(int $salaCodigo, string $fechaYmd, int $empresaId): array
    {
        $flash = $this->estructuraVacia();

        $baseline = null;
        foreach (['M', 'T', 'N'] as $turno) {
            $datos = WigosSqlServerProcess::ejecutarCalcDatosFlashTurno($fechaYmd, $turno, $empresaId);

            if ($turno === 'M') {
                $baseline = $datos;
            }

            $billSlots = $turno === 'M' ? (float) ($baseline['bill_slots'] ?? 0) : 0.0;
            $billRul = $turno === 'M' ? (float) ($baseline['bill_rul'] ?? 0) : 0.0;
            $billPoker = $turno === 'M' ? (float) ($baseline['bill_poker'] ?? 0) : 0.0;
            $winSlots = $turno === 'M' ? (float) ($baseline['win_slots'] ?? 0) : 0.0;
            $winRul = $turno === 'M' ? (float) ($baseline['win_rul'] ?? 0) : 0.0;
            $coinInSlots = $turno === 'M' ? (float) ($baseline['coin_in_slots'] ?? 0) : 0.0;
            $coinInRul = $turno === 'M' ? (float) ($baseline['coin_in_rul'] ?? 0) : 0.0;
            $coinInPoker = $turno === 'M' ? (float) ($baseline['coin_in_poker'] ?? 0) : 0.0;

            if ($turno === 'M') {
                $flash['cant_rul'] = (int) ($datos['units_rul'] ?? 0);
                $flash['cant_slots'] = max(0, (int) ($datos['units_slots'] ?? 0) - (int) ($datos['units_poker'] ?? 0));
            }

            $ventasCaja = $turno === 'M' ? (float) ($datos['ventas_caja'] ?? 0) : 0.0;
            $ventasSlots = $turno === 'M' ? (float) ($datos['ventas_slots'] ?? 0) : (float) ($datos['venta_slots'] ?? 0);
            $ventasRuletas = $turno === 'M' ? (float) ($datos['ventas_ruletas'] ?? 0) : (float) ($datos['venta_ruletas'] ?? 0);
            $pagosCaja = $turno === 'M' ? (float) ($datos['pagos_caja'] ?? 0) : 0.0;
            $pagosSlots = $turno === 'M' ? (float) ($datos['pagos_slots'] ?? 0) : (float) ($datos['tito_slots'] ?? 0);
            $pagosRuletas = $turno === 'M' ? (float) ($datos['pagos_ruletas'] ?? 0) : 0.0;
            $pagosManuales = (float) ($datos['pagos_manuales'] ?? 0);
            $titoSlots = (float) ($datos['tito_slots'] ?? 0);
            $titoRul = (float) ($datos['tito_rul'] ?? 0);
            $titoPoker = (float) ($datos['tito_poker'] ?? 0);

            $flash['slot_d'] = round((float) $flash['slot_d'] + $billSlots + $ventasSlots + $ventasCaja, 2);
            $flash['slot_r'] = round((float) $flash['slot_r'] + $billSlots + $ventasSlots + $ventasCaja - $pagosSlots - $pagosCaja - $pagosManuales, 2);
            $flash['slot_coin_in'] = round((float) $flash['slot_coin_in'] + $coinInSlots - $coinInPoker, 2);
            $flash['win_ol_slot'] = round((float) $flash['win_ol_slot'] + $winSlots, 2);
            $flash['soft_count'] = round((float) $flash['soft_count'] + $billSlots - $billPoker, 2);
            $flash['hard_count'] = round((float) $flash['hard_count'] + $titoSlots - $titoPoker, 2);

            $flash['rul_d'] = round((float) $flash['rul_d'] + $ventasRuletas + $billRul, 2);
            $flash['rul_r'] = round((float) $flash['rul_r'] + $billRul + $ventasRuletas - $pagosRuletas, 2);
            $flash['rul_coin_in'] = round((float) $flash['rul_coin_in'] + $coinInRul, 2);
            $flash['win_ol_rul'] = round((float) $flash['win_ol_rul'] + $winRul, 2);
            $flash['soft_rul'] = round((float) $flash['soft_rul'] + $billRul, 2);
            $flash['hard_rul'] = round((float) $flash['hard_rul'] + $titoRul, 2);
        }

        return $flash;
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
