<?php

namespace App\Services\Caja\Flash;

use App\Models\Caja\Estacionamiento\JornadaEstacionamiento;
use App\Support\Caja\Flash\FlashCajaBingoTotalesSupport;
use App\Models\Configuracion\Sala;
use App\Support\Caja\Estacionamiento\EstacionamientoTurnoOperativoTotalesSupport;
use App\Services\Caja\RendicionGastronomiaAuditoriaAnitaService;
use App\Support\Wigos\WigosSqlServerProcess;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Calcula campos del flash diario desde Wigos (slots/ruletas) + conciliación rendgastro/ERP (AyB/estac) + ERP (bingo/vehículos).
 */
final class FlashCajaCalculoService
{
    public function __construct(
        private readonly RendicionGastronomiaAuditoriaAnitaService $auditoriaRendgService,
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
     * AyB y estacionamiento alineados a {@see RendicionGastronomiaAuditoriaAnitaService}
     * (total gastro = salón PCs + post-cierre; estac = Σ PV neto ERP por circuito estacionamiento).
     * Bingo y cantidad de vehículos desde ERP.
     *
     * @return array{ayb: float, estac: float, cant_vehic: int, bingo_cant_carton: int, bingo_total_venta: float, bingo_resultado: float}
     */
    private function totalesAyBEstacBingo(int $empresaId, string $fechaSql): array
    {
        $ayb = 0.0;
        $estac = 0.0;

        try {
            $informe = $this->auditoriaRendgService->auditarFechaJornada($empresaId, $fechaSql);
            foreach ($informe['filas'] as $fila) {
                $tipo = (string) ($fila['tipo_fila'] ?? '');
                if ($tipo === 'total_gastro') {
                    $ayb = round((float) ($fila['erp_z'] ?? 0), 2);
                }
                if ($tipo === 'total_estacionamiento') {
                    $estac = round((float) ($fila['erp_z'] ?? 0), 2);
                }
            }
        } catch (Throwable $e) {
            Log::warning('Flash conciliación rendgastro '.$fechaSql.': '.$e->getMessage(), [
                'empresa_id' => $empresaId,
            ]);
        }

        $bingo = FlashCajaBingoTotalesSupport::resolver($empresaId, $fechaSql);

        $cantVehic = 0;
        $jornadas = JornadaEstacionamiento::query()
            ->where('empresa_id', $empresaId)
            ->whereDate('fecha_jornada', $fechaSql)
            ->whereNotNull('cierre_en')
            ->get();

        foreach ($jornadas as $jornada) {
            try {
                $totales = EstacionamientoTurnoOperativoTotalesSupport::calcularPorJornada($jornada);
                $cantVehic += (int) ($totales['cantidad_comprobantes'] ?? 0);
            } catch (Throwable $e) {
                Log::warning('Flash estacionamiento jornada '.$jornada->id.': '.$e->getMessage());
            }
        }

        return [
            'ayb' => $ayb,
            'estac' => $estac,
            'cant_vehic' => $cantVehic,
            'bingo_cant_carton' => $bingo['bingo_cant_carton'],
            'bingo_total_venta' => $bingo['bingo_total_venta'],
            'bingo_resultado' => $bingo['bingo_resultado'],
        ];
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
