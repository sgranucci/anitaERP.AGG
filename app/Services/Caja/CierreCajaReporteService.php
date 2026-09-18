<?php

declare(strict_types=1);

namespace App\Services\Caja;

use App\Models\Caja\Caja_Movimiento_Cuentacaja;
use App\Models\Caja\Cheque;
use App\Models\Caja\Cuentacaja;
use App\Models\Configuracion\Empresa;
use App\Support\Caja\CierreCajaReporteFiltros;
use App\Support\Caja\CierreCajaReporteSecciones;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Informe de cierre de caja diaria (Anita l-ciecaja.c) sobre ERP.
 *
 * Secciones: resumen por cuenta, cheques emitidos, depósitos, cheques recibidos,
 * rechazados y en caución; totales cobro/pago del período.
 */
class CierreCajaReporteService
{
    /**
     * @param  array<string, mixed>  $filtros
     * @return array<string, mixed>
     */
    public function generar(array $filtros): array
    {
        $empresaIds = array_values(array_map('intval', $filtros['empresa_ids'] ?? []));
        $desde = (string) ($filtros['fecha_desde'] ?? date('Y-m-d'));
        $hasta = (string) ($filtros['fecha_hasta'] ?? date('Y-m-d'));
        $cuentaFiltro = (int) ($filtros['cuentacaja_id'] ?? 0);
        $incluirSinMov = ! empty($filtros['incluir_sin_movimiento']);

        $nombresEmpresa = $empresaIds === []
            ? []
            : Empresa::query()->whereIn('id', $empresaIds)->orderBy('nombre')->pluck('nombre')->all();

        $cuentas = $this->cuentasAlcance($empresaIds, $cuentaFiltro);
        $saldos = $this->resumenCuentas($cuentas, $empresaIds, $desde, $hasta, $incluirSinMov);
        $chequesEmitidos = $this->chequesEmitidos($empresaIds, $desde, $hasta, $cuentaFiltro);
        $depositos = $this->depositos($empresaIds, $desde, $hasta, $cuentaFiltro);
        $chequesRecibidos = $this->chequesRecibidos($empresaIds, $desde, $hasta, $cuentaFiltro);
        $chequesRechazados = $this->chequesRechazados($empresaIds, $desde, $hasta, $cuentaFiltro);
        $chequesCaucion = $this->chequesCaucion($empresaIds, $desde, $hasta, $cuentaFiltro);
        $cobroPago = $this->totalesCobroPago($empresaIds, $desde, $hasta, $cuentaFiltro);

        $filasPreview = $this->armarFilasPlanas(
            $saldos,
            $chequesEmitidos,
            $depositos,
            $cobroPago,
            $chequesRecibidos,
            $chequesRechazados,
            $chequesCaucion,
            $nombresEmpresa
        );

        return [
            'filas' => $filasPreview,
            'saldos' => $saldos,
            'cheques_emitidos' => $chequesEmitidos,
            'depositos' => $depositos,
            'cheques_recibidos' => $chequesRecibidos,
            'cheques_rechazados' => $chequesRechazados,
            'cheques_caucion' => $chequesCaucion,
            'cobro_pago' => $cobroPago,
            'total_registros' => count(array_filter(
                $filasPreview,
                static fn (array $f) => ($f['tipo_fila'] ?? '') === 'dato'
            )),
            'subtitulo' => CierreCajaReporteFiltros::subtitulo($filtros, $nombresEmpresa),
            'filtros' => $filtros,
            'nombreempresa' => $nombresEmpresa[0] ?? (config('app.empresa') ?: ''),
        ];
    }

    /**
     * @param  list<int>  $empresaIds
     * @return \Illuminate\Support\Collection<int, Cuentacaja>
     */
    public function cuentasFiltro(array $empresaIds)
    {
        return $this->cuentasAlcance($empresaIds, 0);
    }

    /**
     * @param  list<int>  $empresaIds
     * @return Collection<int, Cuentacaja>
     */
    private function cuentasAlcance(array $empresaIds, int $cuentaFiltro): Collection
    {
        $q = Cuentacaja::query()->orderBy('codigo');
        if ($empresaIds !== []) {
            $q->where(function ($w) use ($empresaIds) {
                $w->whereNull('empresa_id')->orWhereIn('empresa_id', $empresaIds);
            });
        }
        if ($cuentaFiltro > 0) {
            $q->whereKey($cuentaFiltro);
        }

        return $q->get(['id', 'codigo', 'nombre', 'empresa_id', 'banco_id', 'tipocuenta']);
    }

    /**
     * @param  Collection<int, Cuentacaja>  $cuentas
     * @param  list<int>  $empresaIds
     * @return list<array<string, mixed>>
     */
    private function resumenCuentas(
        Collection $cuentas,
        array $empresaIds,
        string $desde,
        string $hasta,
        bool $incluirSinMov
    ): array {
        if ($cuentas->isEmpty()) {
            return [];
        }

        $ids = $cuentas->pluck('id')->map(static fn ($id) => (int) $id)->all();
        $saldoAnteriorMap = $this->sumasPorCuenta($ids, $empresaIds, null, $this->diaAnterior($desde));
        $movPeriodo = $this->sumasIngresoEgresoPorCuenta($ids, $empresaIds, $desde, $hasta);

        $filas = [];
        $totSaldoAnt = $totIng = $totEgr = $totSaldoAct = 0.0;

        foreach ($cuentas as $cuenta) {
            $id = (int) $cuenta->id;
            $saldoAnt = (float) ($saldoAnteriorMap[$id] ?? 0);
            $ing = (float) ($movPeriodo[$id]['ingreso'] ?? 0);
            $egr = (float) ($movPeriodo[$id]['egreso'] ?? 0);
            $mov = round($ing - $egr, 2);
            $saldoAct = round($saldoAnt + $mov, 2);

            if (! $incluirSinMov
                && abs($saldoAnt) < 0.0001
                && abs($ing) < 0.0001
                && abs($egr) < 0.0001
            ) {
                continue;
            }

            $totSaldoAnt += $saldoAnt;
            $totIng += $ing;
            $totEgr += $egr;
            $totSaldoAct += $saldoAct;

            $filas[] = [
                'tipo_fila' => 'dato',
                'seccion' => 'saldos',
                'cuenta_id' => $id,
                'codigo' => (string) $cuenta->codigo,
                'nombre' => (string) $cuenta->nombre,
                'saldo_anterior' => round($saldoAnt, 2),
                'ingresos' => round($ing, 2),
                'egresos' => round($egr, 2),
                'total_movimiento' => $mov,
                'saldo_actual' => $saldoAct,
                'nombreempresa' => '',
            ];
        }

        if ($filas !== []) {
            $filas[] = [
                'tipo_fila' => 'total',
                'seccion' => 'saldos',
                'codigo' => '',
                'nombre' => 'Total cuentas',
                'saldo_anterior' => round($totSaldoAnt, 2),
                'ingresos' => round($totIng, 2),
                'egresos' => round($totEgr, 2),
                'total_movimiento' => round($totIng - $totEgr, 2),
                'saldo_actual' => round($totSaldoAct, 2),
            ];
        }

        return $filas;
    }

    /**
     * @param  list<int>  $cuentaIds
     * @param  list<int>  $empresaIds
     * @return array<int, float>
     */
    private function sumasPorCuenta(array $cuentaIds, array $empresaIds, ?string $desde, ?string $hasta): array
    {
        $q = Caja_Movimiento_Cuentacaja::query()
            ->select('caja_movimiento_cuentacaja.cuentacaja_id')
            ->selectRaw('SUM(caja_movimiento_cuentacaja.monto * CASE WHEN COALESCE(caja_movimiento_cuentacaja.moneda_id, 1) > 1 THEN CASE WHEN COALESCE(caja_movimiento_cuentacaja.cotizacion, 0) > 0 THEN caja_movimiento_cuentacaja.cotizacion ELSE 1 END ELSE 1 END) as total')
            ->join('caja_movimiento', 'caja_movimiento.id', '=', 'caja_movimiento_cuentacaja.caja_movimiento_id')
            ->whereIn('caja_movimiento_cuentacaja.cuentacaja_id', $cuentaIds)
            ->where(function ($w) {
                $w->whereNotExists(function ($sub) {
                    $sub->select(DB::raw(1))
                        ->from('caja_movimiento_estado as e0')
                        ->whereColumn('e0.caja_movimiento_id', 'caja_movimiento.id');
                })->orWhereExists(function ($sub) {
                    $sub->select(DB::raw(1))
                        ->from('caja_movimiento_estado as e')
                        ->whereColumn('e.caja_movimiento_id', 'caja_movimiento.id')
                        ->where('e.estado', 'A')
                        ->whereRaw('e.id = (select max(e2.id) from caja_movimiento_estado e2 where e2.caja_movimiento_id = caja_movimiento.id)');
                });
            });

        if ($empresaIds !== []) {
            $q->whereIn('caja_movimiento.empresa_id', $empresaIds);
        }
        if ($desde !== null && $desde !== '') {
            $q->where('caja_movimiento_cuentacaja.fecha', '>=', $desde);
        }
        if ($hasta !== null && $hasta !== '') {
            $q->where('caja_movimiento_cuentacaja.fecha', '<=', $hasta);
        }

        $map = [];
        foreach ($q->groupBy('caja_movimiento_cuentacaja.cuentacaja_id')->get() as $row) {
            $map[(int) $row->cuentacaja_id] = round((float) $row->total, 2);
        }

        return $map;
    }

    /**
     * @param  list<int>  $cuentaIds
     * @param  list<int>  $empresaIds
     * @return array<int, array{ingreso: float, egreso: float}>
     */
    private function sumasIngresoEgresoPorCuenta(array $cuentaIds, array $empresaIds, string $desde, string $hasta): array
    {
        $q = Caja_Movimiento_Cuentacaja::query()
            ->select('caja_movimiento_cuentacaja.cuentacaja_id')
            ->selectRaw('SUM(CASE WHEN (caja_movimiento_cuentacaja.monto * CASE WHEN COALESCE(caja_movimiento_cuentacaja.moneda_id, 1) > 1 THEN CASE WHEN COALESCE(caja_movimiento_cuentacaja.cotizacion, 0) > 0 THEN caja_movimiento_cuentacaja.cotizacion ELSE 1 END ELSE 1 END) > 0 THEN (caja_movimiento_cuentacaja.monto * CASE WHEN COALESCE(caja_movimiento_cuentacaja.moneda_id, 1) > 1 THEN CASE WHEN COALESCE(caja_movimiento_cuentacaja.cotizacion, 0) > 0 THEN caja_movimiento_cuentacaja.cotizacion ELSE 1 END ELSE 1 END) ELSE 0 END) as ingreso')
            ->selectRaw('SUM(CASE WHEN (caja_movimiento_cuentacaja.monto * CASE WHEN COALESCE(caja_movimiento_cuentacaja.moneda_id, 1) > 1 THEN CASE WHEN COALESCE(caja_movimiento_cuentacaja.cotizacion, 0) > 0 THEN caja_movimiento_cuentacaja.cotizacion ELSE 1 END ELSE 1 END) < 0 THEN ABS(caja_movimiento_cuentacaja.monto * CASE WHEN COALESCE(caja_movimiento_cuentacaja.moneda_id, 1) > 1 THEN CASE WHEN COALESCE(caja_movimiento_cuentacaja.cotizacion, 0) > 0 THEN caja_movimiento_cuentacaja.cotizacion ELSE 1 END ELSE 1 END) ELSE 0 END) as egreso')
            ->join('caja_movimiento', 'caja_movimiento.id', '=', 'caja_movimiento_cuentacaja.caja_movimiento_id')
            ->whereIn('caja_movimiento_cuentacaja.cuentacaja_id', $cuentaIds)
            ->whereBetween('caja_movimiento_cuentacaja.fecha', [$desde, $hasta])
            ->where(function ($w) {
                $w->whereNotExists(function ($sub) {
                    $sub->select(DB::raw(1))
                        ->from('caja_movimiento_estado as e0')
                        ->whereColumn('e0.caja_movimiento_id', 'caja_movimiento.id');
                })->orWhereExists(function ($sub) {
                    $sub->select(DB::raw(1))
                        ->from('caja_movimiento_estado as e')
                        ->whereColumn('e.caja_movimiento_id', 'caja_movimiento.id')
                        ->where('e.estado', 'A')
                        ->whereRaw('e.id = (select max(e2.id) from caja_movimiento_estado e2 where e2.caja_movimiento_id = caja_movimiento.id)');
                });
            });

        if ($empresaIds !== []) {
            $q->whereIn('caja_movimiento.empresa_id', $empresaIds);
        }

        $map = [];
        foreach ($q->groupBy('caja_movimiento_cuentacaja.cuentacaja_id')->get() as $row) {
            $map[(int) $row->cuentacaja_id] = [
                'ingreso' => round((float) $row->ingreso, 2),
                'egreso' => round((float) $row->egreso, 2),
            ];
        }

        return $map;
    }

    /**
     * @param  list<int>  $empresaIds
     * @return list<array<string, mixed>>
     */
    private function chequesEmitidos(array $empresaIds, string $desde, string $hasta, int $cuentaFiltro): array
    {
        $q = Cheque::query()
            ->with(['cuentacajas', 'bancos', 'empresas', 'monedas'])
            ->where('origen', 'E')
            ->whereBetween('fechaemision', [$desde, $hasta])
            ->where(function ($w) {
                $w->whereNull('estado')->orWhereNotIn('estado', ['A']);
            });
        $this->filtrarChequeEmpresaCuenta($q, $empresaIds, $cuentaFiltro);

        return $this->mapearChequesAgrupadosPorCuenta($q->orderBy('fechaemision')->orderBy('id')->get(), 'emitidos');
    }

    /**
     * @param  list<int>  $empresaIds
     * @return list<array<string, mixed>>
     */
    private function depositos(array $empresaIds, string $desde, string $hasta, int $cuentaFiltro): array
    {
        $q = Cheque::query()
            ->with(['cuentacajas', 'cuentacajaDeposito', 'bancos', 'empresas', 'monedas', 'clientes'])
            ->whereNotNull('fecha_deposito')
            ->whereBetween('fecha_deposito', [$desde, $hasta]);
        if ($empresaIds !== []) {
            $q->whereIn('empresa_id', $empresaIds);
        }
        if ($cuentaFiltro > 0) {
            $q->where(function ($w) use ($cuentaFiltro) {
                $w->where('cuentacaja_deposito_id', $cuentaFiltro)
                    ->orWhere('cuentacaja_id', $cuentaFiltro);
            });
        }

        $filas = [];
        $total = 0.0;
        foreach ($q->orderBy('fecha_deposito')->orderBy('id')->get() as $ch) {
            $importe = $this->importeChequeMn($ch);
            $total += $importe;
            $dep = $ch->cuentacajaDeposito ?? $ch->cuentacajas;
            $filas[] = [
                'tipo_fila' => 'dato',
                'seccion' => 'depositos',
                'id' => (int) $ch->id,
                'fecha' => $this->fechaDmy($ch->fecha_deposito),
                'numerocheque' => (string) ($ch->numerocheque ?? ''),
                'banco' => (string) ($ch->bancos->nombre ?? ''),
                'cuenta_codigo' => (string) ($dep->codigo ?? ''),
                'cuenta_nombre' => (string) ($dep->nombre ?? ''),
                'boleta' => (string) ($ch->nro_boleta_deposito ?? ''),
                'importe' => $importe,
                'nombreempresa' => (string) ($ch->empresas->nombre ?? ''),
            ];
        }
        if ($filas !== []) {
            $filas[] = [
                'tipo_fila' => 'total',
                'seccion' => 'depositos',
                'cuenta_nombre' => 'Total depósitos',
                'importe' => round($total, 2),
            ];
        }

        return $filas;
    }

    /**
     * @param  list<int>  $empresaIds
     * @return list<array<string, mixed>>
     */
    private function chequesRecibidos(array $empresaIds, string $desde, string $hasta, int $cuentaFiltro): array
    {
        $q = Cheque::query()
            ->with(['cuentacajas', 'bancos', 'empresas', 'monedas', 'clientes'])
            ->where('origen', 'R')
            ->whereBetween('fechaemision', [$desde, $hasta])
            ->where(function ($w) {
                $w->whereNull('estado')->orWhereNotIn('estado', ['A', 'R']);
            });
        $this->filtrarChequeEmpresaCuenta($q, $empresaIds, $cuentaFiltro);

        return $this->mapearChequesDetalle($q->orderBy('fechaemision')->orderBy('id')->get(), 'recibidos');
    }

    /**
     * @param  list<int>  $empresaIds
     * @return list<array<string, mixed>>
     */
    private function chequesRechazados(array $empresaIds, string $desde, string $hasta, int $cuentaFiltro): array
    {
        $q = Cheque::query()
            ->with(['cuentacajas', 'bancos', 'empresas', 'monedas', 'clientes'])
            ->where(function ($w) use ($desde, $hasta) {
                $w->where(function ($a) use ($desde, $hasta) {
                    $a->whereNotNull('fecha_rechazo')->whereBetween('fecha_rechazo', [$desde, $hasta]);
                })->orWhere(function ($b) use ($desde, $hasta) {
                    $b->where('estado', 'R')
                        ->whereNull('fecha_rechazo')
                        ->whereBetween('fechaemision', [$desde, $hasta]);
                });
            });
        $this->filtrarChequeEmpresaCuenta($q, $empresaIds, $cuentaFiltro);

        return $this->mapearChequesDetalle($q->orderBy('fecha_rechazo')->orderBy('id')->get(), 'rechazados');
    }

    /**
     * @param  list<int>  $empresaIds
     * @return list<array<string, mixed>>
     */
    private function chequesCaucion(array $empresaIds, string $desde, string $hasta, int $cuentaFiltro): array
    {
        $q = Cheque::query()
            ->with(['cuentacajas', 'bancos', 'empresas', 'monedas', 'clientes'])
            ->whereNotNull('fecha_caucion')
            ->whereBetween('fecha_caucion', [$desde, $hasta]);
        $this->filtrarChequeEmpresaCuenta($q, $empresaIds, $cuentaFiltro);

        return $this->mapearChequesDetalle($q->orderBy('fecha_caucion')->orderBy('id')->get(), 'caucion');
    }

    /**
     * @param  list<int>  $empresaIds
     * @return array{cobro: float, pago: float, filas: list<array<string, mixed>>}
     */
    private function totalesCobroPago(array $empresaIds, string $desde, string $hasta, int $cuentaFiltro): array
    {
        $q = Caja_Movimiento_Cuentacaja::query()
            ->join('caja_movimiento', 'caja_movimiento.id', '=', 'caja_movimiento_cuentacaja.caja_movimiento_id')
            ->join('tipotransaccion_caja', 'tipotransaccion_caja.id', '=', 'caja_movimiento.tipotransaccion_caja_id')
            ->whereBetween('caja_movimiento_cuentacaja.fecha', [$desde, $hasta])
            ->whereIn('tipotransaccion_caja.abreviatura', ['COB', 'OPP', 'DEV'])
            ->where(function ($w) {
                $w->whereNotExists(function ($sub) {
                    $sub->select(DB::raw(1))
                        ->from('caja_movimiento_estado as e0')
                        ->whereColumn('e0.caja_movimiento_id', 'caja_movimiento.id');
                })->orWhereExists(function ($sub) {
                    $sub->select(DB::raw(1))
                        ->from('caja_movimiento_estado as e')
                        ->whereColumn('e.caja_movimiento_id', 'caja_movimiento.id')
                        ->where('e.estado', 'A')
                        ->whereRaw('e.id = (select max(e2.id) from caja_movimiento_estado e2 where e2.caja_movimiento_id = caja_movimiento.id)');
                });
            })
            ->select('tipotransaccion_caja.abreviatura')
            ->selectRaw('SUM(caja_movimiento_cuentacaja.monto * CASE WHEN COALESCE(caja_movimiento_cuentacaja.moneda_id, 1) > 1 THEN CASE WHEN COALESCE(caja_movimiento_cuentacaja.cotizacion, 0) > 0 THEN caja_movimiento_cuentacaja.cotizacion ELSE 1 END ELSE 1 END) as total');

        if ($empresaIds !== []) {
            $q->whereIn('caja_movimiento.empresa_id', $empresaIds);
        }
        if ($cuentaFiltro > 0) {
            $q->where('caja_movimiento_cuentacaja.cuentacaja_id', $cuentaFiltro);
        }

        $cobro = 0.0;
        $pago = 0.0;
        $filas = [];
        foreach ($q->groupBy('tipotransaccion_caja.abreviatura')->get() as $row) {
            $abrev = (string) $row->abreviatura;
            $total = round((float) $row->total, 2);
            if (in_array($abrev, ['COB'], true)) {
                $cobro += abs($total);
            } else {
                $pago += abs($total);
            }
            $filas[] = [
                'tipo_fila' => 'dato',
                'seccion' => 'cobro_pago',
                'aplicacion' => $abrev,
                'cobro' => in_array($abrev, ['COB'], true) ? abs($total) : 0.0,
                'pago' => ! in_array($abrev, ['COB'], true) ? abs($total) : 0.0,
            ];
        }
        if ($filas !== []) {
            $filas[] = [
                'tipo_fila' => 'total',
                'seccion' => 'cobro_pago',
                'aplicacion' => 'Total',
                'cobro' => round($cobro, 2),
                'pago' => round($pago, 2),
            ];
        }

        return [
            'cobro' => round($cobro, 2),
            'pago' => round($pago, 2),
            'filas' => $filas,
        ];
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<\App\Models\Caja\Cheque>  $q
     * @param  list<int>  $empresaIds
     */
    private function filtrarChequeEmpresaCuenta($q, array $empresaIds, int $cuentaFiltro): void
    {
        if ($empresaIds !== []) {
            $q->whereIn('empresa_id', $empresaIds);
        }
        if ($cuentaFiltro > 0) {
            $q->where('cuentacaja_id', $cuentaFiltro);
        }
    }

    /**
     * @param  Collection<int, Cheque>  $cheques
     * @return list<array<string, mixed>>
     */
    private function mapearChequesAgrupadosPorCuenta(Collection $cheques, string $seccion): array
    {
        $grupos = $cheques->groupBy(static fn (Cheque $c) => (int) ($c->cuentacaja_id ?? 0));
        $filas = [];
        $total = 0.0;

        foreach ($grupos as $cuentaId => $grupo) {
            /** @var Cheque $first */
            $first = $grupo->first();
            $cuenta = $first->cuentacajas;
            $sub = 0.0;
            foreach ($grupo as $ch) {
                $sub += $this->importeChequeMn($ch);
            }
            $total += $sub;
            $filas[] = [
                'tipo_fila' => 'dato',
                'seccion' => $seccion,
                'cuenta_id' => (int) $cuentaId,
                'codigo' => (string) ($cuenta->codigo ?? ''),
                'nombre' => (string) ($cuenta->nombre ?? ''),
                'cantidad' => $grupo->count(),
                'importe' => round($sub, 2),
                'nombreempresa' => (string) ($first->empresas->nombre ?? ''),
            ];
        }

        if ($filas !== []) {
            $filas[] = [
                'tipo_fila' => 'total',
                'seccion' => $seccion,
                'nombre' => 'Total',
                'importe' => round($total, 2),
            ];
        }

        return $filas;
    }

    /**
     * @param  Collection<int, Cheque>  $cheques
     * @return list<array<string, mixed>>
     */
    private function mapearChequesDetalle(Collection $cheques, string $seccion): array
    {
        $filas = [];
        $total = 0.0;
        foreach ($cheques as $ch) {
            $importe = $this->importeChequeMn($ch);
            $total += $importe;
            $filas[] = [
                'tipo_fila' => 'dato',
                'seccion' => $seccion,
                'id' => (int) $ch->id,
                'nro_interno' => (string) ($ch->nro_interno_anita ?? $ch->id),
                'cliente_codigo' => (string) ($ch->clientes->codigo ?? ''),
                'cliente_nombre' => (string) ($ch->clientes->nombre ?? ''),
                'fecha' => $this->fechaDmy($ch->fechaemision),
                'fecha_cheque' => $this->fechaDmy($ch->fechapago),
                'fecha_rechazo' => $this->fechaDmy($ch->fecha_rechazo),
                'fecha_caucion' => $this->fechaDmy($ch->fecha_caucion),
                'numerocheque' => (string) ($ch->numerocheque ?? ''),
                'banco' => (string) ($ch->bancos->nombre ?? ''),
                'importe' => $importe,
                'nombreempresa' => (string) ($ch->empresas->nombre ?? ''),
            ];
        }
        if ($filas !== []) {
            $filas[] = [
                'tipo_fila' => 'total',
                'seccion' => $seccion,
                'cliente_nombre' => 'Total',
                'importe' => round($total, 2),
            ];
        }

        return $filas;
    }

    private function importeChequeMn(Cheque $ch): float
    {
        $monto = (float) ($ch->monto ?? 0);
        $monedaId = (int) ($ch->moneda_id ?? 1);
        $cotizacion = (float) ($ch->cotizacion ?? 1);
        $coef = $monedaId > 1 ? ($cotizacion > 0 ? $cotizacion : 1.0) : 1.0;

        return round(abs($monto) * $coef, 2);
    }

    private function diaAnterior(string $ymd): string
    {
        return date('Y-m-d', strtotime($ymd.' -1 day'));
    }

    private function fechaDmy(mixed $fecha): string
    {
        if ($fecha instanceof \DateTimeInterface) {
            return $fecha->format('d/m/Y');
        }
        $s = trim((string) $fecha);
        if ($s === '') {
            return '';
        }
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $s, $m)) {
            return $m[3].'/'.$m[2].'/'.$m[1];
        }

        return $s;
    }

    /**
     * @param  list<array<string, mixed>>  $saldos
     * @param  list<array<string, mixed>>  $emitidos
     * @param  list<array<string, mixed>>  $depositos
     * @param  array{cobro: float, pago: float, filas: list<array<string, mixed>>}  $cobroPago
     * @param  list<array<string, mixed>>  $recibidos
     * @param  list<array<string, mixed>>  $rechazados
     * @param  list<array<string, mixed>>  $caucion
     * @param  list<string>  $nombresEmpresa
     * @return list<array<string, mixed>>
     */
    private function armarFilasPlanas(
        array $saldos,
        array $emitidos,
        array $depositos,
        array $cobroPago,
        array $recibidos,
        array $rechazados,
        array $caucion,
        array $nombresEmpresa
    ): array {
        $out = [];
        $nombreEmp = $nombresEmpresa[0] ?? '';
        $parcial = [
            'saldos' => $saldos,
            'cheques_emitidos' => $emitidos,
            'depositos' => $depositos,
            'cobro_pago' => $cobroPago,
            'cheques_recibidos' => $recibidos,
            'cheques_rechazados' => $rechazados,
            'cheques_caucion' => $caucion,
        ];

        foreach (CierreCajaReporteSecciones::bloques($parcial) as $bloque) {
            $out[] = [
                'tipo_fila' => 'seccion',
                'titulo' => $bloque['titulo'],
                'seccion' => $bloque['clave'],
                'nombreempresa' => $nombreEmp,
            ];
            foreach ($bloque['filas'] as $fila) {
                $fila['nombreempresa'] = $fila['nombreempresa'] ?? $nombreEmp;
                $out[] = $fila;
            }
        }

        return $out;
    }
}
