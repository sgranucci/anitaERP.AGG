<?php

namespace App\Services\Ventas;

use App\Queries\Ventas\GastronomiaVentasArticulosConciliacionQuery;
use App\Support\Contable\CuentaAutomaticaClaves;
use App\Support\Contable\CuentaAutomaticaResolver;
use App\Support\Database\SqlDialectSupport;
use App\Support\Ventas\Gastronomia\CierreJornadaProcesoConfigSupport;
use App\Support\Ventas\GastronomiaVentasArticulosReporteFiltros;
use Illuminate\Support\Facades\DB;

/**
 * Cruza la columna «Importe total vta. externa» del informe de ventas de artículos
 * contra las cuentas de alimentos y bebidas, tabaco e IVA de los asientos de gastronomía.
 *
 * Esa columna es el total facturado (con IVA) de las ventas sin descuento.
 * La nota de crédito resta. Las cuentas de venta están sin IVA.
 * Invitación y staff no entran en la columna y sí entran en las cuentas, por el total del comprobante.
 */
final class GastronomiaVentasArticulosConciliacionService
{
    private const TOLERANCIA = 1.0;

    public function __construct(
        private readonly GastronomiaVentasArticulosConciliacionQuery $query,
    ) {}

    /**
     * @param  array<string, mixed>  $filtros
     * @return array<string, mixed>
     */
    public function generar(array $filtros): array
    {
        $empresaId = (int) ($filtros['empresa_id'] ?? 0);
        [$desde, $hasta] = GastronomiaVentasArticulosReporteFiltros::normalizarRangoFechas(
            (string) ($filtros['fecha_desde'] ?? ''),
            (string) ($filtros['fecha_hasta'] ?? ''),
        );

        $informe = $this->query->resumen($filtros);
        $cuentas = $this->cuentas($empresaId);
        $contable = $this->contablePeriodo($empresaId, $desde, $hasta, $cuentas);
        $vending = $this->saldoCuenta($empresaId, $desde, $hasta, (int) ($cuentas['vending']['id'] ?? 0));

        $importeInforme = (float) $informe['importe_externa_informe'];
        $bar = (float) $contable['alimentos'];
        $tabaco = (float) $contable['tabaco'];
        $iva = (float) $contable['iva'];
        $invitacion = (float) $informe['facturas_invitacion'];
        $staff = (float) $informe['facturas_staff'];

        $bruto = round($bar + $tabaco + $iva, 2);
        $comparable = round($bruto - $invitacion - $staff, 2);
        $diferencia = round($importeInforme - $comparable, 2);

        $tabacoInforme = (float) $informe['importe_externa_tabaco'];
        $barInforme = round($importeInforme - $tabacoInforme, 2);

        $diasInforme = $this->query->porDia($filtros);
        $diasContable = $contable['por_dia'];
        $dias = $this->unirDias($diasInforme, $diasContable);

        $incluyeVending = (int) $informe['ventas_puntoventa_vending'] > 0;

        return [
            'tolerancia' => self::TOLERANCIA,
            'cierra' => abs($diferencia) <= self::TOLERANCIA,
            'diferencia' => $diferencia,
            'informe' => [
                'importe_externa' => $importeInforme,
                'importe_externa_firmado' => (float) $informe['importe_externa_firmado'],
                'importe_externa_tabaco' => $tabacoInforme,
                'importe_externa_bar' => $barInforme,
                'facturas_externa' => (float) $informe['facturas_externa'],
                'facturas_invitacion' => $invitacion,
                'facturas_staff' => $staff,
                'notas_credito_en_positivo' => round(
                    $importeInforme - (float) $informe['importe_externa_firmado'],
                    2,
                ),
            ],
            'cuentas' => $cuentas,
            'contable' => [
                'alimentos' => $bar,
                'tabaco' => $tabaco,
                'iva' => $iva,
                'bruto' => $bruto,
                'comparable' => $comparable,
                'observaciones' => $contable['observaciones'],
            ],
            'vending' => [
                'incluido_en_informe' => $incluyeVending,
                'ventas' => (int) $informe['ventas_puntoventa_vending'],
                'saldo' => $vending,
            ],
            'dias' => $dias,
            'periodo_texto' => GastronomiaVentasArticulosReporteFiltros::formatearPeriodoTexto($filtros),
        ];
    }

    /**
     * @return array<string, array{id: int, codigo: string, nombre: string}>
     */
    private function cuentas(int $empresaId): array
    {
        $cfg = CierreJornadaProcesoConfigSupport::paraEmpresa($empresaId);
        $vendingId = (int) (CuentaAutomaticaResolver::resolverId(
            $empresaId,
            CuentaAutomaticaClaves::CIERRE_VENDING_VENTAS,
        ) ?? 0);

        $ids = [
            'alimentos' => (int) ($cfg['cuenta_ventas_id'] ?? 0),
            'tabaco' => (int) ($cfg['cuenta_ventas_kiosco_id'] ?? 0),
            'iva' => (int) ($cfg['cuenta_iva_id'] ?? 0),
            'vending' => $vendingId,
        ];

        $filas = DB::table('cuentacontable')
            ->whereIn('id', array_values(array_filter($ids)))
            ->get(['id', 'codigo', 'nombre'])
            ->keyBy('id');

        $out = [];
        foreach ($ids as $clave => $id) {
            $fila = $filas->get($id);
            $out[$clave] = [
                'id' => $id,
                'codigo' => $fila !== null ? trim((string) $fila->codigo) : '',
                'nombre' => $fila !== null ? trim((string) $fila->nombre) : '',
            ];
        }

        return $out;
    }

    /**
     * @param  array<string, array{id: int, codigo: string, nombre: string}>  $cuentas
     * @return array{
     *   alimentos: float,
     *   tabaco: float,
     *   iva: float,
     *   observaciones: list<array{observacion: string, neto: float}>,
     *   por_dia: array<string, array{alimentos: float, tabaco: float, iva: float}>
     * }
     */
    private function contablePeriodo(int $empresaId, string $desde, string $hasta, array $cuentas): array
    {
        $vacio = [
            'alimentos' => 0.0,
            'tabaco' => 0.0,
            'iva' => 0.0,
            'observaciones' => [],
            'por_dia' => [],
        ];

        $idBar = (int) ($cuentas['alimentos']['id'] ?? 0);
        $idTabaco = (int) ($cuentas['tabaco']['id'] ?? 0);
        $idIva = (int) ($cuentas['iva']['id'] ?? 0);
        $idsVenta = array_values(array_filter([$idBar, $idTabaco]));
        if ($empresaId <= 0 || $desde === '' || $hasta === '' || $idsVenta === []) {
            return $vacio;
        }

        $idsLeer = array_values(array_unique(array_filter([$idBar, $idTabaco, $idIva])));
        $dia = SqlDialectSupport::fecha('a.fecha');

        $base = DB::table('asiento as a')
            ->join('asiento_movimiento as am', 'am.asiento_id', '=', 'a.id')
            ->where('a.empresa_id', $empresaId)
            ->whereDate('a.fecha', '>=', $desde)
            ->whereDate('a.fecha', '<=', $hasta)
            ->whereIn('am.cuentacontable_id', $idsLeer)
            ->whereExists(function ($sub) use ($idsVenta) {
                $sub->select(DB::raw(1))
                    ->from('asiento_movimiento as amv')
                    ->whereColumn('amv.asiento_id', 'a.id')
                    ->whereIn('amv.cuentacontable_id', $idsVenta);
            });

        $totales = (clone $base)
            ->select('am.cuentacontable_id')
            ->selectRaw('ROUND(SUM(-am.monto), 2) as neto')
            ->groupBy('am.cuentacontable_id')
            ->get();

        $porCuenta = [];
        foreach ($totales as $row) {
            $porCuenta[(int) $row->cuentacontable_id] = round((float) $row->neto, 2);
        }

        $observaciones = (clone $base)
            ->selectRaw("COALESCE(NULLIF(TRIM(a.observacion), ''), '(sin observación)') as observacion")
            ->selectRaw('ROUND(SUM(-am.monto), 2) as neto')
            ->groupByRaw("COALESCE(NULLIF(TRIM(a.observacion), ''), '(sin observación)')")
            ->orderByDesc('neto')
            ->get();

        $obs = [];
        foreach ($observaciones as $row) {
            $obs[] = [
                'observacion' => (string) $row->observacion,
                'neto' => round((float) $row->neto, 2),
            ];
        }

        $porDiaRows = (clone $base)
            ->selectRaw("{$dia} as dia")
            ->addSelect('am.cuentacontable_id')
            ->selectRaw('ROUND(SUM(-am.monto), 2) as neto')
            ->groupByRaw("{$dia}, am.cuentacontable_id")
            ->get();

        $porDia = [];
        foreach ($porDiaRows as $row) {
            $fecha = substr(trim((string) $row->dia), 0, 10);
            if (! isset($porDia[$fecha])) {
                $porDia[$fecha] = ['alimentos' => 0.0, 'tabaco' => 0.0, 'iva' => 0.0];
            }
            $cuentaId = (int) $row->cuentacontable_id;
            $neto = round((float) $row->neto, 2);
            if ($cuentaId === $idBar) {
                $porDia[$fecha]['alimentos'] = $neto;
            } elseif ($cuentaId === $idTabaco) {
                $porDia[$fecha]['tabaco'] = $neto;
            } elseif ($cuentaId === $idIva) {
                $porDia[$fecha]['iva'] = $neto;
            }
        }

        return [
            'alimentos' => (float) ($porCuenta[$idBar] ?? 0),
            'tabaco' => (float) ($porCuenta[$idTabaco] ?? 0),
            'iva' => (float) ($porCuenta[$idIva] ?? 0),
            'observaciones' => $obs,
            'por_dia' => $porDia,
        ];
    }

    private function saldoCuenta(int $empresaId, string $desde, string $hasta, int $cuentaId): float
    {
        if ($empresaId <= 0 || $cuentaId <= 0 || $desde === '' || $hasta === '') {
            return 0.0;
        }

        $neto = DB::table('asiento as a')
            ->join('asiento_movimiento as am', 'am.asiento_id', '=', 'a.id')
            ->where('a.empresa_id', $empresaId)
            ->whereDate('a.fecha', '>=', $desde)
            ->whereDate('a.fecha', '<=', $hasta)
            ->where('am.cuentacontable_id', $cuentaId)
            ->selectRaw('ROUND(SUM(-am.monto), 2) as neto')
            ->value('neto');

        return round((float) $neto, 2);
    }

    /**
     * @param  list<array{dia: string, importe_externa_informe: float, facturas_invitacion: float, facturas_staff: float}>  $informe
     * @param  array<string, array{alimentos: float, tabaco: float, iva: float}>  $contable
     * @return list<array<string, mixed>>
     */
    private function unirDias(array $informe, array $contable): array
    {
        $porDia = [];
        foreach ($informe as $row) {
            $porDia[$row['dia']] = $row;
        }
        foreach ($contable as $dia => $row) {
            if (! isset($porDia[$dia])) {
                $porDia[$dia] = [
                    'dia' => $dia,
                    'importe_externa_informe' => 0.0,
                    'facturas_invitacion' => 0.0,
                    'facturas_staff' => 0.0,
                ];
            }
            $porDia[$dia]['alimentos'] = $row['alimentos'];
            $porDia[$dia]['tabaco'] = $row['tabaco'];
            $porDia[$dia]['iva'] = $row['iva'];
        }

        ksort($porDia);
        $out = [];
        foreach ($porDia as $row) {
            $alimentos = round((float) ($row['alimentos'] ?? 0), 2);
            $tabaco = round((float) ($row['tabaco'] ?? 0), 2);
            $iva = round((float) ($row['iva'] ?? 0), 2);
            $inv = round((float) ($row['facturas_invitacion'] ?? 0), 2);
            $staff = round((float) ($row['facturas_staff'] ?? 0), 2);
            $informeDia = round((float) ($row['importe_externa_informe'] ?? 0), 2);
            $comparable = round($alimentos + $tabaco + $iva - $inv - $staff, 2);
            $out[] = [
                'dia' => (string) $row['dia'],
                'importe_externa' => $informeDia,
                'facturas_invitacion' => $inv,
                'facturas_staff' => $staff,
                'alimentos' => $alimentos,
                'tabaco' => $tabaco,
                'iva' => $iva,
                'comparable' => $comparable,
                'diferencia' => round($informeDia - $comparable, 2),
            ];
        }

        return $out;
    }
}
