<?php

declare(strict_types=1);

namespace App\Support\Caja\RendicionMaquina;

use App\Support\Wigos\WigosSqlServerProcess;
use Carbon\Carbon;
use RuntimeException;

/**
 * Lectura WIGOS para rendición de máquinas — equivalente a
 * RENDM_lee_on_line() (carga_rendmaquina.fc) + on_line.fc + calc_datos_wigos.php.
 *
 * Reutiliza los mismos SP que Flash (`calcDatosFlashTurno`):
 * spGananciaDeSalaPorSesion, spDropDiarioPorTerminal, SP_QlickView_Win_per_EGM,
 * spTicketsDrop, SP_TransferenciasExternasAnita.
 *
 * Reglas Anita:
 * - Turno C → consulta WIGOS como M + bill del día (RENDM_trae_bill_actual).
 * - Turno M/T/N → drop de trabajo = bill anterior; ant. se refresca con fecha D-1.
 * - QR (neto + impuesto) solo en mañana (desde lectura D-1) o en cierre C (día).
 */
final class RendicionMaquinaWigosLeeOnlineSupport
{
    /**
     * @return array{
     *   inputs: array<string, float>,
     *   wigos_json: array<string, float>,
     *   valores?: list<array<string, mixed>>,
     *   gastos?: list<array<string, mixed>>,
     *   calc_orquestador?: array<string, float>,
     *   crudo: array<string, mixed>,
     *   meta: array<string, mixed>
     * }
     */
    public static function traer(int $empresaId, string $fechaYmd, string $turno, ?int $exceptoId = null): array
    {
        if ($empresaId <= 0) {
            throw new RuntimeException('Empresa inválida para lectura WIGOS.');
        }
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $fechaYmd)) {
            throw new RuntimeException('Fecha inválida para lectura WIGOS.');
        }

        $turnoNorm = RendicionMaquinaTurno::normalizar($turno);
        $esCierre = RendicionMaquinaTurno::esCompleto($turnoNorm);
        $letraWigos = RendicionMaquinaTurno::letraWigos($turnoNorm);
        $fechaWigos = str_replace('-', '', $fechaYmd);

        $datosDia = WigosSqlServerProcess::ejecutarCalcDatosFlashTurno(
            $fechaWigos,
            $letraWigos,
            $empresaId,
        );

        $inputs = self::inputsEnCero();
        self::mapearSesionYVentas($inputs, $datosDia);

        $billSlots = (float) ($datosDia['bill_slots'] ?? 0);
        $billRul = (float) ($datosDia['bill_rul'] ?? 0);
        $billSlotsAnt = (float) ($datosDia['bill_slots_anterior'] ?? 0);
        $billRulAnt = (float) ($datosDia['bill_rul_anterior'] ?? 0);
        $montoNetoQr = (float) ($datosDia['monto_neto_qr'] ?? 0);
        $impuestoQr = (float) ($datosDia['impuesto_qr'] ?? 0);

        $crudo = [
            'dia' => $datosDia,
            'dia_anterior' => null,
        ];
        $valores = null;
        $gastos = null;
        $orquestadorCompleto = null;

        if ($esCierre) {
            // RENDM_trae_bill_actual = TRUE: drop bruto + QR neto del día (desfase jornada vs M/T/N).
            $inputs['drop_billete'] = round($billSlots, 2);
            $inputs['drop_ruleta'] = round($billRul, 2);
            $inputs['drop_bill_ant'] = round($billSlotsAnt, 2);
            $inputs['drop_rul_ant'] = round($billRulAnt, 2);
            $inputs['dropqr_rodillo'] = round($montoNetoQr, 2);
            // impuesto_qr / venta / tito / pago / valores / gastos: lee_rendiciones_del_dia
            $completo = RendicionMaquinaCompletoDelDiaSupport::consolidar(
                $empresaId,
                $fechaYmd,
                $exceptoId
            );
            foreach ($completo['inputs'] as $clave => $valor) {
                // No pisar drop/QR brutos del día que vienen de WIGOS con trae_bill_actual.
                if (in_array($clave, ['drop_billete', 'drop_ruleta', 'dropqr_rodillo', 'dropqr_ruleta'], true)) {
                    continue;
                }
                $inputs[$clave] = round((float) $valor, 2);
            }
            $valores = $completo['valores'];
            $gastos = $completo['gastos'];
            $orquestadorCompleto = $completo['orquestador'];
            $crudo['completo_del_dia'] = $completo['meta'];
            $crudo['drop_ant_origen'] = $completo['meta']['origen_drop_ant'] ?? 'ninguno';
        } else {
            // Paridad RENDM_lee_on_line (!trae_bill_actual):
            // 1) drop de trabajo = BillSlotsAnterior; ant. arranca igual (bruto WIGOS).
            // 2) 2ª lectura D-1 pisa drop_bill_ant con BillSlots de D-1 (sigue bruto).
            // Nunca aplicar impuesto acá: el neto solo va a drop_billete en traerWigos.
            $inputs['drop_billete'] = round($billSlotsAnt, 2);
            $inputs['drop_ruleta'] = round($billRulAnt, 2);
            $inputs['drop_bill_ant'] = round($billSlotsAnt, 2);
            $inputs['drop_rul_ant'] = round($billRulAnt, 2);

            $fechaAnt = Carbon::parse($fechaYmd)->subDay()->format('Ymd');
            $datosAnt = WigosSqlServerProcess::ejecutarCalcDatosFlashTurno(
                $fechaAnt,
                RendicionMaquinaTurno::MANIANA,
                $empresaId,
            );
            $crudo['dia_anterior'] = $datosAnt;

            $billAntDia = (float) ($datosAnt['bill_slots'] ?? 0);
            $rulAntDia = (float) ($datosAnt['bill_rul'] ?? 0);
            if (abs($billAntDia) > 0.00001 || abs($rulAntDia) > 0.00001) {
                $inputs['drop_bill_ant'] = round($billAntDia, 2);
                $inputs['drop_rul_ant'] = round($rulAntDia, 2);
            }

            // QR de mañana: MontoNetoQR / ImpuestoQR de la lectura D-1 (como el C)
            if (RendicionMaquinaTurno::esManiana($turnoNorm)) {
                $inputs['dropqr_rodillo'] = round((float) ($datosAnt['monto_neto_qr'] ?? 0), 2);
                $inputs['impuesto_qr'] = round((float) ($datosAnt['impuesto_qr'] ?? 0), 2);
            }

            // Impuesto drop del C anterior solo en M (Anita T/N = 0)
            $previas = RendicionMaquinaPreviasSupport::resolver($empresaId, $fechaYmd, $turnoNorm);
            if (RendicionMaquinaTurno::esManiana($turnoNorm)
                && abs((float) $previas['impuesto_drop']) > 0.00001) {
                $inputs['impuesto_drop'] = round((float) $previas['impuesto_drop'], 2);
            } else {
                $inputs['impuesto_drop'] = 0.0;
            }
            $crudo['previas_origen_fondo'] = $previas['origen_fondo'];
            $crudo['previas_origen_impuesto'] = $previas['origen_impuesto_drop'];
        }

        $out = [
            'inputs' => $inputs,
            'wigos_json' => $inputs,
            'crudo' => $crudo,
            'meta' => [
                'modo_wigos' => RendicionMaquinaTurno::modoWigos($turnoNorm),
                'turno_wigos' => $letraWigos,
                'stub' => false,
                'es_cierre' => $esCierre,
                'fecha_wigos' => $fechaWigos,
                'mensaje' => $esCierre
                    ? 'Completo: drop/QR del día (WIGOS) + consolidado M/T/N (lee_rendiciones_del_dia).'
                    : 'Datos WIGOS importados (calc_datos_wigos / on_line).',
            ],
        ];
        if ($valores !== null) {
            $out['valores'] = $valores;
        }
        if ($gastos !== null) {
            $out['gastos'] = $gastos;
        }
        if ($orquestadorCompleto !== null) {
            $out['calc_orquestador'] = $orquestadorCompleto;
        }

        return $out;
    }

    /**
     * @param  array<string, float>  $inputs
     * @param  array<string, float|int>  $datos
     */
    private static function mapearSesionYVentas(array &$inputs, array $datos): void
    {
        // RENDM_lee_on_line ← on_line.fc ← calc_datos_wigos
        $inputs['venta_ficha'] = round((float) ($datos['venta_slots'] ?? 0), 2);
        $inputs['pago_manual'] = round((float) ($datos['pagos_manuales'] ?? 0), 2);
        $inputs['tito'] = round((float) ($datos['tito_slots'] ?? 0), 2);
        // Siguen en motor (B40/C10/D50) aunque no se muestran en pantalla.
        $inputs['hopper'] = round((float) ($datos['llenados'] ?? $datos['hoppers'] ?? 0), 2);
        $inputs['venta_ruleta'] = round((float) ($datos['venta_ruletas'] ?? 0), 2);
        $inputs['salida_ruleta'] = round((float) ($datos['salida_ruletas'] ?? $datos['salidas_rul'] ?? 0), 2);
        $inputs['tito_ruleta'] = round((float) ($datos['tito_rul'] ?? 0), 2);
    }

    /**
     * @return array<string, float>
     */
    private static function inputsEnCero(): array
    {
        $out = [];
        foreach (RendicionMaquinaVariables::INPUTS as $ruta) {
            $clave = str_starts_with($ruta, 'inputs.')
                ? substr($ruta, 7)
                : $ruta;
            $out[$clave] = 0.0;
        }

        return $out;
    }
}
