<?php

namespace App\Support\Contable;

use App\Models\Caja\RendicionMaquina;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use InvalidArgumentException;

/**
 * Armado de asientos del cierre máquinas (réplica genera_asiento en p-vtamaquina.c).
 */
final class CierreRendicionMaquinaAsientoSupport
{
    public const DESCRIPCION_ASIENTO = 'Cierre rendición máquinas';

    private const TOLERANCIA_CUADRE = 0.02;

    /**
     * @param  Collection<int, RendicionMaquina>  $rendiciones
     * @return array{
     *   asientos: list<array{leyenda: string, lineas: list<array<string, mixed>>}>,
     *   lineas: list<array<string, mixed>>,
     *   advertencias: list<string>,
     *   resumen_debe: float,
     *   resumen_haber: float,
     *   titulo: string,
     *   totales: array<string, mixed>,
     *   fsl_monto: float,
     *   cantidad_rendiciones: int,
     *   rendicion_ids: list<int>
     * }
     */
    public static function generarPreviewGrupo(Collection $rendiciones, array $config, int $empresaId, string $fechaDia): array
    {
        if ($rendiciones->isEmpty()) {
            throw new InvalidArgumentException('No hay rendiciones en el grupo.');
        }

        foreach ($rendiciones as $rendicion) {
            self::assertRendicionCerrable($rendicion);
        }

        $primera = $rendiciones->first();
        foreach ($rendiciones as $rendicion) {
            if ((int) $rendicion->empresa_id !== $empresaId) {
                throw new InvalidArgumentException('El grupo mezcla empresas distintas.');
            }
            if (CierreRendicionMaquinaGrupoSupport::fechaDiaDesdeRendicion($rendicion) !== $fechaDia) {
                throw new InvalidArgumentException('El grupo mezcla fechas distintas.');
            }
            if ((string) ($rendicion->turno ?? '') !== CierreRendicionMaquinaGrupoSupport::TURNO_CIERRE) {
                throw new InvalidArgumentException('El grupo incluye rendiciones que no son turno C.');
            }
        }

        $tot = CierreRendicionMaquinaTotalesSupport::calcular($rendiciones, $empresaId, $fechaDia);
        $asientos = self::armarAsientos($tot, $config);

        $lineasFlat = [];
        $advertencias = [];
        $debe = 0.0;
        $haber = 0.0;

        foreach ($asientos as $asiento) {
            $lineasFlat[] = [
                'concepto' => '--- '.$asiento['leyenda'].' ---',
                'debe' => 0.0,
                'haber' => 0.0,
                'separador' => true,
            ];
            foreach ($asiento['lineas'] as $ln) {
                $lineasFlat[] = $ln;
                $debe += (float) ($ln['debe'] ?? 0);
                $haber += (float) ($ln['haber'] ?? 0);
            }
        }

        $fechaFmt = Carbon::parse($fechaDia)->format('d/m/Y');
        $pv = CierreRendicionMaquinaConfigSupport::puntoventaFsl($empresaId);
        $ids = $rendiciones->pluck('id')->map(fn ($id) => (int) $id)->values()->all();
        $titulo = 'Cierre máquinas — '.$fechaFmt.' — PV FSL '.$pv.' — '.$rendiciones->count().' rendición(es)';

        if (abs($debe - $haber) > self::TOLERANCIA_CUADRE) {
            $advertencias[] = 'Los asientos no cuadran en conjunto: debe '
                .number_format($debe, 2, ',', '.').' vs haber '.number_format($haber, 2, ',', '.').'.';
        }

        return [
            'asientos' => $asientos,
            'lineas' => $lineasFlat,
            'advertencias' => $advertencias,
            'resumen_debe' => round($debe, 2),
            'resumen_haber' => round($haber, 2),
            'titulo' => $titulo,
            'totales' => $tot,
            'fsl_monto' => round((float) ($tot['fsl_monto'] ?? 0), 2),
            'cantidad_rendiciones' => $rendiciones->count(),
            'rendicion_ids' => $ids,
        ];
    }

    /**
     * @param  array<string, mixed>  $tot
     * @param  array<string, int>  $config
     * @return list<array{leyenda: string, lineas: list<array<string, mixed>>}>
     */
    public static function armarAsientos(array $tot, array $config): array
    {
        $asientos = [];

        $cCajaPesos = (int) ($config['cuenta_caja_pesos_id'] ?? 0);
        $cTarjetas = (int) ($config['cuenta_tarjetas_id'] ?? 0);
        $cMep = (int) ($config['cuenta_mep_id'] ?? 0);
        $cDolares = (int) ($config['cuenta_dolares_id'] ?? 0);
        $cEuros = (int) ($config['cuenta_euros_id'] ?? 0);
        $cCripto = (int) ($config['cuenta_cripto_id'] ?? 0);
        $cTotalcoin = (int) ($config['cuenta_totalcoin_id'] ?? 0);
        $cImpuestoEsp = (int) ($config['cuenta_impuesto_esp_id'] ?? 0);
        $cGastos = (int) ($config['cuenta_gastos_id'] ?? 0);
        $cTicketGastro = (int) ($config['cuenta_ticket_gastro_id'] ?? 0);
        $cPago24 = (int) ($config['cuenta_pago24_id'] ?? 0);
        $cTicketPromDebe = (int) ($config['cuenta_ticket_prom_debe_id'] ?? 0);
        $cTicketPromHaber = (int) ($config['cuenta_ticket_prom_haber_id'] ?? 0);
        $cCajaTrans = (int) ($config['cuenta_caja_transitoria_id'] ?? 0);
        $cFfMaquina = (int) ($config['cuenta_ff_maquina_id'] ?? 0);
        $cVentas = (int) ($config['cuenta_ventas_id'] ?? 0);
        $cVentasRuleta = (int) ($config['cuenta_ventas_ruleta_id'] ?? 0);
        $cPoderPublico = (int) ($config['cuenta_poder_publico_id'] ?? 0);
        $cDifCaja = (int) ($config['cuenta_diferencia_caja_id'] ?? 0);
        $cPartidaPend = (int) ($config['cuenta_partida_pendiente_id'] ?? 0);
        $cCanonLoteria = (int) ($config['cuenta_canon_loteria_id'] ?? 0);
        $cContCanonLoteria = (int) ($config['cuenta_cont_canon_loteria_id'] ?? 0);
        $cCanonHospital = (int) ($config['cuenta_canon_hospital_id'] ?? 0);
        $cContCanonHospital = (int) ($config['cuenta_cont_canon_hospital_id'] ?? 0);

        $lineas1 = [];

        self::agregarLineaSigned($lineas1, (float) ($tot['efectivo'] ?? 0), 'Venta maquinas — Caja pesos', $cCajaPesos);
        self::agregarLineaSigned($lineas1, (float) ($tot['tarjetas'] ?? 0), 'Venta maquinas — Tarjetas', $cTarjetas);
        self::agregarLineaSigned($lineas1, (float) ($tot['mep'] ?? 0), 'Venta maquinas — MEP', $cMep);

        foreach ($tot['valores_cuenta'] ?? [] as $valorCuenta) {
            $monto = round((float) ($valorCuenta['monto'] ?? 0), 2);
            $cuentaId = (int) ($valorCuenta['cuentacontable_id'] ?? 0);
            $concepto = trim((string) ($valorCuenta['concepto'] ?? 'Valor cuenta'));
            if (abs($monto) <= 0.0001 || $cuentaId <= 0) {
                continue;
            }
            self::agregarLineaSigned($lineas1, $monto, 'Venta maquinas — '.$concepto, $cuentaId);
        }

        self::agregarLineaSigned($lineas1, (float) ($tot['dolares_en_pesos'] ?? 0), 'Venta maquinas — Dólares', $cDolares);
        self::agregarLineaSigned($lineas1, (float) ($tot['euros_en_pesos'] ?? 0), 'Venta maquinas — Euros', $cEuros);
        self::agregarLineaSigned($lineas1, (float) ($tot['cripto_en_pesos'] ?? 0), 'Venta maquinas — Cripto', $cCripto);

        $totalcoin = round((float) ($tot['totalcoin'] ?? 0), 2);
        if (abs($totalcoin) > 0.0001) {
            $lineas1[] = self::linea($totalcoin <= 0 ? 'D' : 'H', 'Venta maquinas — Totalcoin', $cTotalcoin, abs($totalcoin));
        }

        $impuestoEsp = round((float) ($tot['impuesto_esp'] ?? 0), 2);
        if (abs($impuestoEsp) > 0.0001) {
            $lineas1[] = self::linea($impuestoEsp < 0 ? 'D' : 'H', 'Venta maquinas — Impuesto especial', $cImpuestoEsp, abs($impuestoEsp));
        }

        $gastosVales = round((float) ($tot['vales'] ?? 0) + (float) ($tot['reintegros'] ?? 0), 2);
        if (abs($gastosVales) > 0.0001) {
            $lineas1[] = self::linea($gastosVales > 0 ? 'D' : 'H', 'Venta maquinas — Vales y reintegros', $cGastos, abs($gastosVales));
        }

        foreach ($tot['gastos_apertura'] ?? [] as $gasto) {
            $monto = round((float) ($gasto['monto'] ?? 0), 2);
            $cuentaId = (int) ($gasto['cuentacontable_id'] ?? 0);
            $contrapartidaId = (int) ($gasto['contrapartida_id'] ?? 0);
            $desc = trim((string) ($gasto['descripcion'] ?? 'Gasto apertura'));
            if (abs($monto) <= 0.0001 || $cuentaId <= 0) {
                continue;
            }
            $lineas1[] = self::linea($monto > 0 ? 'D' : 'H', 'Venta maquinas — '.$desc, $cuentaId, abs($monto));
            if ($contrapartidaId > 0) {
                $lineas1[] = self::linea($monto > 0 ? 'H' : 'D', 'Venta maquinas — '.$desc.' (contrapartida)', $contrapartidaId, abs($monto));
            }
        }

        $ticketGastro = round((float) ($tot['ticket_gastro'] ?? 0), 2);
        if (abs($ticketGastro) > 0.0001) {
            $lineas1[] = self::linea('H', 'Venta maquinas — Ticket gastronomía', $cTicketGastro, abs($ticketGastro));
        }

        $vtaAntGastro = round((float) ($tot['vta_ant_gastro'] ?? 0), 2);
        if (abs($vtaAntGastro) > 0.0001) {
            $lineas1[] = self::linea('D', 'Venta maquinas — Pago 24', $cPago24, abs($vtaAntGastro));
        }

        $ticketProm = round((float) ($tot['ticket_prom'] ?? 0), 2);
        if ($ticketProm > 0.0001) {
            $lineas1[] = self::linea('D', 'Venta maquinas — Ticket promocional', $cTicketPromDebe, $ticketProm);
            $lineas1[] = self::linea('H', 'Venta maquinas — Ticket promocional (contrapartida)', $cTicketPromHaber, $ticketProm);
        }

        if ($ticketGastro > 0.0001) {
            $lineas1[] = self::linea('D', 'Venta maquinas — Caja transitoria (ticket gastro)', $cCajaTrans, $ticketGastro);
        }

        $varFf = round((float) ($tot['variacion_ff'] ?? 0), 2);
        if (abs($varFf) > 0.0001) {
            $lineas1[] = self::linea($varFf > 0 ? 'D' : 'H', 'Venta maquinas — Variación FF', $cFfMaquina, abs($varFf));
        }

        $cajaTrans = round((float) ($tot['tot_caja_trans'] ?? 0), 2);
        if (abs($cajaTrans) > 0.0001) {
            $lineas1[] = self::linea($cajaTrans < 0 ? 'D' : 'H', 'Venta maquinas — Caja transitoria', $cCajaTrans, abs($cajaTrans));
        }

        $maqOnline = round((float) ($tot['maquinas_online'] ?? 0), 2);
        if (abs($maqOnline) > 0.0001) {
            $lineas1[] = self::linea($maqOnline > 0 ? 'H' : 'D', 'Venta maquinas — Venta máquinas online', $cVentas, abs($maqOnline));
        }

        $rulOnline = round((float) ($tot['ruletas_online'] ?? 0), 2);
        if (abs($rulOnline) > 0.0001) {
            $lineas1[] = self::linea($rulOnline > 0 ? 'H' : 'D', 'Venta maquinas — Venta ruletas online', $cVentasRuleta, abs($rulOnline));
        }

        $pagoDiferido = round((float) ($tot['pago_diferido'] ?? 0), 2);
        if (abs($pagoDiferido) > 0.0001) {
            $lineas1[] = self::linea($pagoDiferido > 0 ? 'H' : 'D', 'Venta maquinas — Pago diferido', $cPoderPublico, abs($pagoDiferido));
        }

        $neto = self::sumDebeMenosHaber($lineas1);
        $neto = round(
            $neto
            + self::contribucionAjusteOnlineReal($maqOnline)
            + self::contribucionAjusteOnlineReal($rulOnline)
            - self::contribucionAjusteOnlineReal((float) ($tot['maquinas_real'] ?? 0))
            - self::contribucionAjusteOnlineReal((float) ($tot['ruletas_real'] ?? 0))
            + $totalcoin,
            2,
        );

        if (abs($neto) > 0.0001) {
            $lineas1[] = self::linea($neto > 0 ? 'H' : 'D', 'Venta maquinas — Diferencia de caja', $cDifCaja, abs($neto));
        }

        if (abs($totalcoin) > 0.0001) {
            self::agregarLineaSigned($lineas1, $totalcoin, 'Venta maquinas — Totalcoin a caja pesos', $cCajaPesos);
        }

        $descuadre = self::sumDebeMenosHaber($lineas1);
        if (abs($descuadre) > 0.01 && $cPartidaPend > 0) {
            $lineas1[] = self::linea($descuadre > 0 ? 'H' : 'D', 'Venta maquinas — Partida pendiente', $cPartidaPend, abs($descuadre));
        }

        if ($lineas1 !== []) {
            $asientos[] = ['leyenda' => 'Venta maquinas', 'lineas' => $lineas1];
        }

        if (abs($pagoDiferido) > 0.0001) {
            // Legacy: poder público ↔ cuenta financiera del valor (fallback caja pesos).
            $cBancoPd = (int) ($tot['pago_diferido_cuenta_id'] ?? 0);
            if ($cBancoPd <= 0) {
                $cBancoPd = $cCajaPesos;
            }
            $lineasPd = [
                self::linea($pagoDiferido > 0 ? 'D' : 'H', 'Pago diferido', $cPoderPublico, abs($pagoDiferido)),
                self::linea($pagoDiferido > 0 ? 'H' : 'D', 'Pago diferido — banco / caja', $cBancoPd, abs($pagoDiferido)),
            ];
            $netoPd = self::sumDebeMenosHaber($lineasPd);
            if (abs($netoPd) > 0.0001) {
                $lineasPd[] = self::linea($netoPd > 0 ? 'H' : 'D', 'Pago diferido — Diferencia de caja', $cDifCaja, abs($netoPd));
            }
            $asientos[] = ['leyenda' => 'Pago diferido', 'lineas' => $lineasPd];
        }

        $baseCanon = round($maqOnline + $rulOnline, 2);
        $pctLoteria = (float) config('rendicion_maquina_anita.cierre_rendicion_contable.canon_loteria_porcentaje', 34);
        $canonLoteria = round($baseCanon * $pctLoteria / 100, 2);
        if (abs($canonLoteria) > 0.0001) {
            $asientos[] = [
                'leyenda' => 'Canon lotería y casinos',
                'lineas' => [
                    self::linea($canonLoteria > 0 ? 'D' : 'H', 'Canon lotería y casinos', $cCanonLoteria, abs($canonLoteria)),
                    self::linea($canonLoteria > 0 ? 'H' : 'D', 'Canon lotería y casinos — contrapartida', $cContCanonLoteria, abs($canonLoteria)),
                ],
            ];
        }

        $pctHospital = (float) config('rendicion_maquina_anita.cierre_rendicion_contable.canon_hospital_porcentaje', 1);
        $canonHospital = round($baseCanon * $pctHospital / 100, 2);
        if ($canonHospital > 0.0001) {
            $asientos[] = [
                'leyenda' => 'Canon ent. de bien publico',
                'lineas' => [
                    self::linea('D', 'Canon ent. de bien publico', $cCanonHospital, $canonHospital),
                    self::linea('H', 'Canon ent. de bien publico — contrapartida', $cContCanonHospital, $canonHospital),
                ],
            ];
        }

        return $asientos;
    }

    /**
     * @param  list<array<string, mixed>>  $lineasAsiento
     */
    public static function armarPayloadAsiento(
        array $lineasAsiento,
        int $empresaId,
        string $fecha,
        string $leyenda,
    ): array {
        $cuentacontableIds = [];
        $debes = [];
        $haberes = [];
        $monedaIds = [];
        $centrocostoIds = [];
        $cotizaciones = [];
        $observaciones = [];

        foreach ($lineasAsiento as $ln) {
            if (! empty($ln['separador'])) {
                continue;
            }
            $debe = round((float) ($ln['debe'] ?? 0), 2);
            $haber = round((float) ($ln['haber'] ?? 0), 2);
            if (abs($debe) <= 0.0001 && abs($haber) <= 0.0001) {
                continue;
            }

            $cuentaId = (int) ($ln['cuenta_id'] ?? 0);
            if ($cuentaId <= 0) {
                throw new InvalidArgumentException('Línea sin cuenta: '.trim((string) ($ln['concepto'] ?? '')));
            }

            $cuentacontableIds[] = $cuentaId;
            $debes[] = $debe > 0.0001 ? $debe : '';
            $haberes[] = $haber > 0.0001 ? $haber : '';
            $monedaIds[] = 1;
            $centrocostoIds[] = null;
            $cotizaciones[] = 1.;
            $observaciones[] = $leyenda;
        }

        $sumDebe = round(array_sum(array_filter($debes, 'is_numeric')), 2);
        $sumHaber = round(array_sum(array_filter($haberes, 'is_numeric')), 2);
        if (abs($sumDebe - $sumHaber) > self::TOLERANCIA_CUADRE) {
            throw new InvalidArgumentException(
                'El asiento «'.$leyenda.'» no cuadra (debe '.$sumDebe.' vs haber '.$sumHaber.').',
            );
        }

        return [
            'empresa_id' => $empresaId,
            'fecha' => $fecha,
            'observacion' => $leyenda,
            'cuentacontable_ids' => $cuentacontableIds,
            'debes' => $debes,
            'haberes' => $haberes,
            'moneda_ids' => $monedaIds,
            'centrocosto_ids' => $centrocostoIds,
            'cotizaciones' => $cotizaciones,
            'observaciones' => $observaciones,
            // No setear path_sistema (no pisar ANITA_BDD_PATH).
        ];
    }

    public static function assertRendicionCerrable(RendicionMaquina $rendicion): void
    {
        if (CierreRendicionMaquinaGrupoSupport::tieneCierreContable($rendicion)) {
            throw new InvalidArgumentException(
                'La rendición #'.$rendicion->id.' ya tiene cierre contable registrado.',
            );
        }
    }

    /**
     * @param  list<array<string, mixed>>  $lineas
     */
    private static function agregarLineaSigned(array &$lineas, float $monto, string $concepto, int $cuentaId): void
    {
        if (abs($monto) <= 0.0001 || $cuentaId <= 0) {
            return;
        }
        $lineas[] = self::linea($monto > 0 ? 'D' : 'H', $concepto, $cuentaId, abs($monto));
    }

    private static function contribucionAjusteOnlineReal(float $monto): float
    {
        if (abs($monto) <= 0.0001) {
            return 0.0;
        }

        return round(abs($monto), 2);
    }

    /**
     * @return array<string, mixed>
     */
    private static function linea(string $dh, string $concepto, int $cuentaId, float $monto): array
    {
        $monto = round($monto, 2);

        return [
            'concepto' => $concepto,
            'cuenta_id' => $cuentaId,
            'debe' => $dh === 'D' ? $monto : 0.0,
            'haber' => $dh === 'H' ? $monto : 0.0,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $lineas
     */
    private static function sumDebeMenosHaber(array $lineas): float
    {
        $debe = 0.0;
        $haber = 0.0;
        foreach ($lineas as $ln) {
            $debe += (float) ($ln['debe'] ?? 0);
            $haber += (float) ($ln['haber'] ?? 0);
        }

        return round($debe - $haber, 2);
    }
}
