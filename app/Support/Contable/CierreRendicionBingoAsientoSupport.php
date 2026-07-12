<?php

namespace App\Support\Contable;

use App\Models\Caja\Bingo\RendicionBingoCaja;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use InvalidArgumentException;

/**
 * Armado de asientos BIN del cierre bingo (réplica genera_asiento en p-vtabingo.c).
 */
final class CierreRendicionBingoAsientoSupport
{
    public const DESCRIPCION_ASIENTO = 'Cierre rendición bingo';

    private const TOLERANCIA_CUADRE = 0.02;

    /**
     * @param  Collection<int, RendicionBingoCaja>  $rendiciones
     * @return array{
     *   asientos: list<array{leyenda: string, lineas: list<array<string, mixed>>}>,
     *   lineas: list<array<string, mixed>>,
     *   advertencias: list<string>,
     *   resumen_debe: float,
     *   resumen_haber: float,
     *   titulo: string,
     *   totales: array<string, mixed>,
     *   fbi_monto: float,
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
            if (CierreRendicionBingoGrupoSupport::fechaDiaDesdeRendicion($rendicion) !== $fechaDia) {
                throw new InvalidArgumentException('El grupo mezcla fechas distintas.');
            }
        }

        $tot = CierreRendicionBingoTotalesSupport::calcular($rendiciones, $empresaId, $fechaDia);
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
        $pv = CierreRendicionBingoConfigSupport::puntoventaFbi($empresaId);
        $ids = $rendiciones->pluck('id')->map(fn ($id) => (int) $id)->values()->all();
        $titulo = 'Cierre bingo — '.$fechaFmt.' — PV FBI '.$pv.' — '.$rendiciones->count().' rendición(es)';

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
            'fbi_monto' => round((float) ($tot['tot_recaudacion'] ?? 0), 2),
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

        $cEfectivo = (int) ($config['cuenta_efectivo_id'] ?? 0);
        $cPremio53 = (int) ($config['cuenta_premio53_id'] ?? 0);
        $cPozo = (int) ($config['cuenta_pozo_bingo_id'] ?? 0);
        $cPantalla = (int) ($config['cuenta_pantalla_id'] ?? 0);
        $cOtros = (int) ($config['cuenta_otros_premios_id'] ?? 0);
        $cDif = (int) ($config['cuenta_diferencia_caja_id'] ?? 0);
        $cVentas = (int) ($config['cuenta_ventas_id'] ?? 0);
        $cPozo58 = (int) ($config['cuenta_pozo58_id'] ?? 0);
        $cHospital = (int) ($config['cuenta_pago_hospital_id'] ?? 0);
        $cContHospital = (int) ($config['cuenta_cont_hospital_id'] ?? 0);

        $lineas1 = [];
        $montoCaja = round((float) ($tot['in_monto'] ?? 0) + (float) ($tot['tot_sobrante'] ?? 0) + (float) ($tot['tot_redondeo'] ?? 0), 2);
        if (abs($montoCaja) > 0.0001) {
            $lineas1[] = self::linea($montoCaja > 0 ? 'D' : 'H', 'Pago de premios — Caja', $cEfectivo, abs($montoCaja));
        }

        $premioBingo = round((float) ($tot['tot_premio'] ?? 0) + (float) ($tot['tot_bingo'] ?? 0), 2);
        if (abs($premioBingo) > 0.0001) {
            $lineas1[] = self::linea($premioBingo > 0 ? 'D' : 'H', 'Pago de premios — Premio 53%', $cPremio53, abs($premioBingo));
        }

        $pozo = round((float) ($tot['tot_pozo'] ?? 0), 2);
        if (abs($pozo) > 0.0001) {
            $lineas1[] = self::linea($pozo > 0 ? 'D' : 'H', 'Pago de premios — Pozo bingo', $cPozo, abs($pozo));
        }

        $pantalla = round((float) ($tot['tot_pantalla'] ?? 0), 2);
        if (abs($pantalla) > 0.0001) {
            $lineas1[] = self::linea($pantalla > 0 ? 'D' : 'H', 'Pago de premios — Pantalla', $cPantalla, abs($pantalla));
        }

        $otros = round((float) ($tot['otros_premios'] ?? 0), 2);
        if (abs($otros) > 0.0001) {
            $lineas1[] = self::linea($otros > 0 ? 'D' : 'H', 'Pago de premios — Otros premios', $cOtros, abs($otros));
        }

        $dif = round((float) ($tot['dif_caja_asiento'] ?? 0), 2);
        if (abs($dif) > 0.0001) {
            $lineas1[] = self::linea($dif > 0 ? 'D' : 'H', 'Pago de premios — Dif. caja / refuerzo', $cDif, abs($dif));
        }

        $totAsiento1 = self::sumDebeMenosHaber($lineas1);
        if (abs($totAsiento1) > 0.0001) {
            $lineas1[] = self::linea('H', 'Pago de premios — Ventas sala bingo', $cVentas, abs($totAsiento1));
        }

        if ($lineas1 !== []) {
            $asientos[] = ['leyenda' => 'Pago de premios', 'lineas' => $lineas1];
        }

        $lineas2 = [];
        $devPozo = round((float) ($tot['tot_premio'] ?? 0) + (float) ($tot['tot_bingo'] ?? 0) + (float) ($tot['tot_porc_recaud'] ?? 0), 2);
        if (abs($devPozo) > 0.0001) {
            $lineas2[] = self::linea($devPozo > 0 ? 'D' : 'H', 'Dev. pozo acum. — Pozo 58%', $cPozo58, abs($devPozo));
        }

        $premioDev = round((float) ($tot['tot_premio'] ?? 0) + (float) ($tot['tot_bingo'] ?? 0), 2);
        if (abs($premioDev) > 0.0001) {
            $lineas2[] = self::linea($premioDev > 0 ? 'H' : 'D', 'Dev. pozo acum. — Premio 53%', $cPremio53, abs($premioDev));
        }

        $porcRec = round((float) ($tot['tot_porc_recaud'] ?? 0), 2);
        if (abs($porcRec) > 0.0001) {
            $lineas2[] = self::linea($porcRec > 0 ? 'H' : 'D', 'Dev. pozo acum. — % recaudación', $cPozo, abs($porcRec));
        }

        if ($lineas2 !== []) {
            $asientos[] = ['leyenda' => 'Dev. pozo acum.', 'lineas' => $lineas2];
        }

        foreach ($tot['canones'] ?? [] as $canon) {
            $monto = round((float) ($canon['total'] ?? 0), 2);
            if (abs($monto) <= 0.0001) {
                continue;
            }
            $desc = 'Canon '.trim((string) ($canon['desc'] ?? ''));
            $debeId = (int) ($canon['cuenta_debe_id'] ?? 0);
            $haberId = (int) ($canon['cuenta_haber_id'] ?? 0);
            $asientos[] = [
                'leyenda' => $desc,
                'lineas' => [
                    self::linea($monto > 0 ? 'D' : 'H', $desc, $debeId, abs($monto)),
                    self::linea($monto > 0 ? 'H' : 'D', $desc.' — contrapartida', $haberId, abs($monto)),
                ],
            ];
        }

        $hospital = round((float) ($tot['tot_pago_hospital'] ?? 0), 2);
        if (abs($hospital) > 0.0001) {
            $asientos[] = [
                'leyenda' => 'Canon ent. de bien publico',
                'lineas' => [
                    self::linea($hospital > 0 ? 'D' : 'H', 'Canon ent. de bien publico', $cHospital, abs($hospital)),
                    self::linea($hospital > 0 ? 'H' : 'D', 'Canon ent. de bien publico — contrapartida', $cContHospital, abs($hospital)),
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
            'path_sistema' => 'C',
        ];
    }

    public static function assertRendicionCerrable(RendicionBingoCaja $rendicion): void
    {
        if ($rendicion->tieneCierreContable()) {
            throw new InvalidArgumentException(
                'La rendición #'.$rendicion->id.' ya tiene cierre contable registrado.',
            );
        }
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
