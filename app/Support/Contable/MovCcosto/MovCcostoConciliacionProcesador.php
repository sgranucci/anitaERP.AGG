<?php

namespace App\Support\Contable\MovCcosto;

use App\Support\Contable\CuentacontableSaldoMesSupport;
use App\Support\Contable\MayorPlanoCuenta\MayorPlanoCuentaSupport;
use App\Support\Contable\MovCcostoListadoFiltros;
use App\Support\Contable\SumasSaldos\SumasSaldosProcesador;
use Illuminate\Support\Facades\DB;

/**
 * Conciliación de «Totales por centro de costo»: movimientos de cuentas que manejan centro de
 * costo (`cuentacontable.manejaccosto = 'S'`) grabados sin centro de costo, o con un id de
 * centro que ya no existe. Esos importes no entran en ninguna columna del informe.
 *
 * Mismo universo que el informe: empresas, período, rango de cuentas, moneda y modo de
 * inclusión de asientos de cierre / inflación.
 */
class MovCcostoConciliacionProcesador
{
    public function __construct(
        private readonly SumasSaldosProcesador $conversorMoneda,
    ) {
    }

    /**
     * @param  list<int>  $empresaIds
     * @param  array<string, mixed>  $filtros
     * @return array{cuentas: list<array<string, mixed>>, totales: array<string, mixed>, advertencias: list<string>}
     */
    public function generar(array $empresaIds, array $filtros): array
    {
        [$fechaDesde, $fechaHasta] = MovCcostoListadoFiltros::normalizarRangoFechas(
            (string) ($filtros['fecha_desde'] ?? ''),
            (string) ($filtros['fecha_hasta'] ?? ''),
        );
        if ($fechaDesde === '' || $fechaHasta === '' || $empresaIds === []) {
            return $this->resultadoVacio();
        }

        $consolidar = ! empty($filtros['consolidar_empresas']) || count($empresaIds) === 1;
        $monedaId = (int) ($filtros['moneda_id'] ?? 1);
        $monedaLocalId = CuentacontableSaldoMesSupport::monedaLocalId();
        $soloOrigen = ! empty($filtros['solo_moneda_origen']);
        $modoInclusion = (string) ($filtros['modo_inclusion_asientos'] ?? 'sin_cierre_ni_inflacion');
        $ymdDesde = MayorPlanoCuentaSupport::ymdDesdeFecha($fechaDesde);
        $ymdHasta = MayorPlanoCuentaSupport::ymdDesdeFecha($fechaHasta);

        // Mismo criterio que MovCcostoProcesador: asientos del período primero, movimientos después.
        $query = DB::table(DB::raw(MovCcostoProcesador::FROM_ASIENTO_MOVIMIENTO))
            ->join('cuentacontable as c', 'c.id', '=', 'am.cuentacontable_id')
            ->leftJoin('centrocosto as cc', 'cc.id', '=', 'am.centrocosto_id')
            ->leftJoin('tipoasiento as ta', 'ta.id', '=', 'a.tipoasiento_id')
            ->leftJoin('moneda as mo', 'mo.id', '=', 'am.moneda_id')
            ->whereIn('a.empresa_id', $empresaIds)
            ->where('a.fecha', '>=', $fechaDesde)
            ->where('a.fecha', '<=', $fechaHasta)
            ->where('c.manejaccosto', 'S')
            ->whereNull('cc.id')
            ->orderBy('c.codigo')
            ->orderBy('a.fecha')
            ->orderBy('a.numeroasiento')
            ->orderBy('am.id')
            ->select([
                'am.id as movimiento_id',
                'am.centrocosto_id',
                'am.monto',
                'am.moneda_id',
                'am.cotizacion',
                'am.observacion as mov_obs',
                'am.anita_tipo as mov_anita_tipo',
                'am.anita_letra as mov_anita_letra',
                'am.anita_sucursal as mov_anita_sucursal',
                'am.anita_nro as mov_anita_nro',
                'a.id as asiento_id',
                'a.empresa_id',
                'a.numeroasiento',
                'a.fecha',
                'a.observacion as asiento_obs',
                'a.anita_origen',
                'a.anita_sistema',
                'a.anita_tipo',
                'a.anita_letra',
                'a.anita_sucursal',
                'a.anita_nro',
                'c.id as cuentacontable_id',
                'c.codigo',
                'c.nombre as cuenta_nombre',
                'ta.abreviatura as tipo_abrev',
                'mo.abreviatura as moneda_abrev',
            ]);

        $desdeCuenta = (int) ($filtros['cuenta_desde'] ?? 0);
        $hastaCuenta = (int) ($filtros['cuenta_hasta'] ?? 0);
        if ($desdeCuenta > 0) {
            $query->where('c.codigo', '>=', $desdeCuenta);
        }
        if ($hastaCuenta > 0) {
            $query->where('c.codigo', '<=', $hastaCuenta);
        }
        if ($soloOrigen) {
            $query->where('am.moneda_id', $monedaId);
        }

        $nombresEmpresa = DB::table('empresa')->whereIn('id', $empresaIds)->pluck('nombre', 'id')->all();

        /** @var array<string, array<string, mixed>> $cuentas */
        $cuentas = [];
        $sinCotizacion = 0;
        $ccInexistente = 0;

        foreach ($query->cursor() as $row) {
            $tipo = (string) ($row->tipo_abrev ?? '');
            $descripcion = trim((string) ($row->mov_obs ?? ''));
            if ($descripcion === '') {
                $descripcion = trim((string) ($row->asiento_obs ?? ''));
            }

            $ymdFecha = MayorPlanoCuentaSupport::ymdDesdeFecha((string) $row->fecha);
            if (! MayorPlanoCuentaSupport::movimientoVisiblePorTipoAsiento($tipo, $modoInclusion, $descripcion)
                || MayorPlanoCuentaSupport::aperturaOmitidaPorCierreEnVentana(
                    $tipo,
                    $ymdFecha,
                    $modoInclusion,
                    $ymdDesde,
                    $ymdHasta,
                )) {
                continue;
            }

            $importe = $this->conversorMoneda->importeEnMonedaReporte(
                (float) $row->monto,
                (int) $row->moneda_id,
                $monedaId,
                $monedaLocalId,
                $soloOrigen,
                isset($row->cotizacion) ? (float) $row->cotizacion : null,
                (string) $row->fecha,
            );
            if (abs($importe) < 1e-9) {
                if (! $soloOrigen && abs((float) $row->monto) > 1e-9 && (int) $row->moneda_id !== $monedaId) {
                    $sinCotizacion++;
                }

                continue;
            }

            $empresaId = (int) $row->empresa_id;
            $codigo = (int) $row->codigo;
            $clave = ($consolidar ? 0 : $empresaId).':'.$codigo;

            $cuentas[$clave] ??= [
                'clave' => $clave,
                'empresa_id' => $consolidar ? 0 : $empresaId,
                'empresa_nombre' => $consolidar ? '' : (string) ($nombresEmpresa[$empresaId] ?? ('#'.$empresaId)),
                'cuentacontable_id' => (int) $row->cuentacontable_id,
                'codigo' => $codigo,
                'codigo_fmt' => MayorPlanoCuentaSupport::formatearCodigoCuenta($codigo),
                'nombre' => (string) $row->cuenta_nombre,
                'cantidad' => 0,
                'debe' => 0.0,
                'haber' => 0.0,
                'movimientos' => [],
            ];

            $debe = $importe > 0 ? $importe : 0.0;
            $haber = $importe < 0 ? abs($importe) : 0.0;
            if ($row->centrocosto_id !== null) {
                $ccInexistente++;
            }

            $cuentas[$clave]['cantidad']++;
            $cuentas[$clave]['debe'] += $debe;
            $cuentas[$clave]['haber'] += $haber;
            $cuentas[$clave]['movimientos'][] = [
                'movimiento_id' => (int) $row->movimiento_id,
                'asiento_id' => (int) $row->asiento_id,
                'empresa_id' => $empresaId,
                'nombreempresa' => (string) ($nombresEmpresa[$empresaId] ?? ''),
                'numeroasiento' => (int) $row->numeroasiento,
                'fecha' => (string) $row->fecha,
                'tipo_asiento' => $tipo,
                'origen' => $this->textoOrigen($row),
                'comprobante' => $this->textoComprobante($row),
                'descripcion' => $descripcion,
                'moneda' => (string) ($row->moneda_abrev ?? ''),
                'centrocosto_id_huerfano' => $row->centrocosto_id !== null ? (int) $row->centrocosto_id : null,
                'debe' => round($debe, 2),
                'haber' => round($haber, 2),
            ];
        }

        $lista = array_values($cuentas);
        usort($lista, static function (array $a, array $b): int {
            $cmp = strcasecmp((string) $a['empresa_nombre'], (string) $b['empresa_nombre']);

            return $cmp !== 0 ? $cmp : ($a['codigo'] <=> $b['codigo']);
        });

        $totDebe = 0.0;
        $totHaber = 0.0;
        $totMovs = 0;
        foreach ($lista as &$cuenta) {
            $cuenta['debe'] = round($cuenta['debe'], 2);
            $cuenta['haber'] = round($cuenta['haber'], 2);
            $totDebe += $cuenta['debe'];
            $totHaber += $cuenta['haber'];
            $totMovs += $cuenta['cantidad'];
        }
        unset($cuenta);

        $advertencias = [];
        if ($sinCotizacion > 0) {
            $advertencias[] = $sinCotizacion.' movimiento(s) sin centro de costo no se pudieron expresar en la moneda '
                .'del informe (sin cotización cargada) y no figuran en la lista.';
        }
        if ($ccInexistente > 0) {
            $advertencias[] = $ccInexistente.' movimiento(s) apuntan a un centro de costo que ya no existe '
                .'(se marcan con «CC inexistente»).';
        }

        return [
            'cuentas' => $lista,
            'totales' => [
                'cuentas' => count($lista),
                'movimientos' => $totMovs,
                'debe' => round($totDebe, 2),
                'haber' => round($totHaber, 2),
                'saldo' => round($totDebe - $totHaber, 2),
            ],
            'advertencias' => $advertencias,
        ];
    }

    private function textoOrigen(object $row): string
    {
        $origen = trim((string) ($row->anita_origen ?? ''));
        $sistema = trim((string) ($row->anita_sistema ?? ''));
        if ($origen === '') {
            return 'AnitaERP';
        }

        return 'Anita '.$origen.($sistema !== '' ? ' ('.$sistema.')' : '');
    }

    private function textoComprobante(object $row): string
    {
        $tipo = trim((string) ($row->mov_anita_tipo ?? ''));
        $letra = trim((string) ($row->mov_anita_letra ?? ''));
        $sucursal = $row->mov_anita_sucursal;
        $nro = $row->mov_anita_nro;
        if ($tipo === '' && (int) $nro <= 0) {
            $tipo = trim((string) ($row->anita_tipo ?? ''));
            $letra = trim((string) ($row->anita_letra ?? ''));
            $sucursal = $row->anita_sucursal;
            $nro = $row->anita_nro;
        }
        if ($tipo === '' && (int) $nro <= 0) {
            return '';
        }

        return trim($tipo.' '.$letra.' '.str_pad((string) (int) $sucursal, 4, '0', STR_PAD_LEFT)
            .'-'.str_pad((string) (int) $nro, 8, '0', STR_PAD_LEFT));
    }

    /**
     * @return array{cuentas: list<array<string, mixed>>, totales: array<string, mixed>, advertencias: list<string>}
     */
    private function resultadoVacio(): array
    {
        return [
            'cuentas' => [],
            'totales' => ['cuentas' => 0, 'movimientos' => 0, 'debe' => 0.0, 'haber' => 0.0, 'saldo' => 0.0],
            'advertencias' => [],
        ];
    }
}
