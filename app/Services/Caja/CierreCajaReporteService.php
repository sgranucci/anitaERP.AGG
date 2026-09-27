<?php

declare(strict_types=1);

namespace App\Services\Caja;

use App\Models\Caja\Caja_Movimiento_Cuentacaja;
use App\Models\Caja\Cheque;
use App\Models\Caja\Cuentacaja;
use App\Models\Configuracion\Empresa;
use App\Support\Caja\ChequeOperacionActivaSupport;
use App\Support\Caja\CierreCajaReporteFiltros;
use App\Support\Caja\CierreCajaReporteSecciones;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Informe de cierre de caja diaria (Anita l-ciecaja.c) sobre ERP.
 *
 * Secciones: resumen por cuenta (incluye línea CHT «Cheques de terceros» como
 * total_cter()), cheques emitidos, depósitos, cheques recibidos, rechazados y
 * en caución; totales cobro/pago del período.
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
        // Anita lista_cuenta → total_cter(): línea sintética de cartera CHT.
        $saldos = $this->incorporarChequesCarteraEnSaldos($saldos, $empresaIds, $desde, $hasta);
        $chequesEmitidos = $this->chequesEmitidos($empresaIds, $desde, $hasta, $cuentaFiltro);
        $depositos = $this->depositos($empresaIds, $desde, $hasta, $cuentaFiltro);
        $chequesRecibidos = $this->chequesRecibidos($empresaIds, $desde, $hasta, $cuentaFiltro);
        $chequesRechazados = $this->chequesRechazados($empresaIds, $desde, $hasta, $cuentaFiltro);
        $chequesCaucion = $this->chequesCaucion($empresaIds, $desde, $hasta, $cuentaFiltro);
        $cobroPago = $this->totalesCobroPago(
            $empresaIds,
            $desde,
            $hasta,
            $cuentaFiltro,
            $chequesRecibidos,
            $chequesEmitidos
        );

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
     * Agrega la línea «Cheques de terceros» al resumen (Anita total_cter / un_mov_ctermae).
     * No filtra por cuenta de caja: en Anita la CHT es una cuenta predefinida aparte.
     *
     * @param  list<array<string, mixed>>  $saldos
     * @param  list<int>  $empresaIds
     * @return list<array<string, mixed>>
     */
    private function incorporarChequesCarteraEnSaldos(
        array $saldos,
        array $empresaIds,
        string $desde,
        string $hasta
    ): array {
        $totales = $this->totalesChequesCartera($empresaIds, $desde, $hasta);
        if ($totales === null) {
            return $saldos;
        }

        $filaCht = [
            'tipo_fila' => 'dato',
            'seccion' => 'saldos',
            'cuenta_id' => 0,
            'codigo' => '',
            'nombre' => 'Cheques de terceros',
            'saldo_anterior' => $totales['saldo_anterior'],
            'ingresos' => $totales['ingresos'],
            'egresos' => $totales['egresos'],
            'total_movimiento' => $totales['total_movimiento'],
            'saldo_actual' => $totales['saldo_actual'],
            'nombreempresa' => '',
            'es_cheques_cartera' => true,
        ];

        $totalIdx = null;
        foreach ($saldos as $i => $fila) {
            if (($fila['tipo_fila'] ?? '') === 'total') {
                $totalIdx = $i;
                break;
            }
        }

        if ($totalIdx === null) {
            $saldos[] = $filaCht;
            $saldos[] = [
                'tipo_fila' => 'total',
                'seccion' => 'saldos',
                'codigo' => '',
                'nombre' => 'Total cuentas',
                'saldo_anterior' => $totales['saldo_anterior'],
                'ingresos' => $totales['ingresos'],
                'egresos' => $totales['egresos'],
                'total_movimiento' => $totales['total_movimiento'],
                'saldo_actual' => $totales['saldo_actual'],
            ];

            return $saldos;
        }

        $total = $saldos[$totalIdx];
        $total['saldo_anterior'] = round((float) ($total['saldo_anterior'] ?? 0) + $totales['saldo_anterior'], 2);
        $total['ingresos'] = round((float) ($total['ingresos'] ?? 0) + $totales['ingresos'], 2);
        $total['egresos'] = round((float) ($total['egresos'] ?? 0) + $totales['egresos'], 2);
        $total['total_movimiento'] = round(
            (float) ($total['ingresos'] ?? 0) - (float) ($total['egresos'] ?? 0),
            2
        );
        $total['saldo_actual'] = round((float) ($total['saldo_actual'] ?? 0) + $totales['saldo_actual'], 2);

        array_splice($saldos, $totalIdx, 0, [$filaCht]);
        $saldos[$totalIdx + 1] = $total;

        return $saldos;
    }

    /**
     * Stock de cheques de terceros (cartera) al estilo Anita un_mov_ctermae.
     *
     * - Ingreso: fechaemision en el período
     * - Egreso: fecha de baja efectiva (depósito / rechazo / caución / entrega / OP) en el período
     * - Saldo anterior: ingresados antes del desde y aún en cartera a esa fecha
     *
     * @param  list<int>  $empresaIds
     * @return array{saldo_anterior: float, ingresos: float, egresos: float, total_movimiento: float, saldo_actual: float}|null
     */
    private function totalesChequesCartera(array $empresaIds, string $desde, string $hasta): ?array
    {
        $q = Cheque::query()
            ->with(['pagoproveedores:id,fecha'])
            ->where('origen', 'R')
            ->whereNotNull('fechaemision')
            ->whereDate('fechaemision', '<=', $hasta);
        // Solo excluye anulados: depositados/rechazados deben entrar para el egreso.
        ChequeOperacionActivaSupport::aplicarFiltroQuery($q, ['A']);
        if ($empresaIds !== []) {
            $q->whereIn('empresa_id', $empresaIds);
        }

        $cheques = $q->get([
            'id',
            'origen',
            'estado',
            'fechaemision',
            'fecha_deposito',
            'fecha_rechazo',
            'fecha_caucion',
            'fecha_entrega',
            'pagoproveedor_id',
            'monto',
            'moneda_id',
            'cotizacion',
            'cobranza_id',
            'caja_movimiento_id',
        ]);

        if ($cheques->isEmpty()) {
            return null;
        }

        $saldoAnterior = 0.0;
        $saldoActual = 0.0;
        $ingresos = 0.0;
        $egresos = 0.0;
        $totalMovimiento = 0.0;

        foreach ($cheques as $ch) {
            $fechaIngreso = $this->fechaYmd($ch->fechaemision);
            if ($fechaIngreso === null) {
                continue;
            }

            $importe = $this->importeChequeMn($ch);
            $fechaBaja = $this->fechaBajaChequeTercero($ch);

            // Stock previo al rango (ingresó antes del desde).
            $incluyoAnterior = false;
            if ($fechaIngreso < $desde) {
                $saldoActual += $importe;
                $saldoAnterior += $importe;
                $incluyoAnterior = true;
            }

            // Ya había salido antes del desde → no forma parte del saldo anterior.
            if ($incluyoAnterior && $fechaBaja !== null && $fechaBaja < $desde) {
                $saldoActual -= $importe;
                $saldoAnterior -= $importe;
            }

            // Ingreso en el período.
            if ($fechaIngreso >= $desde && $fechaIngreso <= $hasta) {
                $totalMovimiento += $importe;
                $saldoActual += $importe;
                $ingresos += $importe;
            }

            // Egreso en el período (depósito, rechazo, caución, entrega / OP).
            if ($fechaBaja !== null && $fechaBaja >= $desde && $fechaBaja <= $hasta) {
                $totalMovimiento -= $importe;
                $saldoActual -= $importe;
                $egresos += $importe;
            }
        }

        return [
            'saldo_anterior' => round($saldoAnterior, 2),
            'ingresos' => round($ingresos, 2),
            'egresos' => round($egresos, 2),
            'total_movimiento' => round($totalMovimiento, 2),
            'saldo_actual' => round($saldoActual, 2),
        ];
    }

    /**
     * Fecha de baja efectiva (Anita: min(cter_fecha_baja, cter_fecha_deposito)).
     */
    private function fechaBajaChequeTercero(Cheque $ch): ?string
    {
        $candidatos = [];
        foreach (['fecha_deposito', 'fecha_rechazo', 'fecha_caucion', 'fecha_entrega'] as $campo) {
            $ymd = $this->fechaYmd($ch->{$campo} ?? null);
            if ($ymd !== null) {
                $candidatos[] = $ymd;
            }
        }

        if ((int) ($ch->pagoproveedor_id ?? 0) > 0) {
            $pagoFecha = $ch->pagoproveedores->fecha ?? null;
            $ymd = $this->fechaYmd($pagoFecha);
            if ($ymd !== null) {
                $candidatos[] = $ymd;
            }
        }

        if ($candidatos === []) {
            return null;
        }

        sort($candidatos);

        return $candidatos[0];
    }

    private function fechaYmd(mixed $fecha): ?string
    {
        if ($fecha instanceof \DateTimeInterface) {
            return $fecha->format('Y-m-d');
        }
        $s = trim((string) $fecha);
        if ($s === '' || $s === '0000-00-00') {
            return null;
        }
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $s, $m)) {
            return $m[1].'-'.$m[2].'-'.$m[3];
        }
        if (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})/', $s, $m)) {
            return $m[3].'-'.$m[2].'-'.$m[1];
        }

        return null;
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
            ->whereBetween('fechaemision', [$desde, $hasta]);
        ChequeOperacionActivaSupport::aplicarFiltroQuery($q);
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
        ChequeOperacionActivaSupport::aplicarFiltroQuery($q);
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
            ->whereBetween('fechaemision', [$desde, $hasta]);
        ChequeOperacionActivaSupport::aplicarFiltroQuery($q);
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
        ChequeOperacionActivaSupport::aplicarFiltroQuery($q, ['A']);
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
        ChequeOperacionActivaSupport::aplicarFiltroQuery($q);
        $this->filtrarChequeEmpresaCuenta($q, $empresaIds, $cuentaFiltro);

        return $this->mapearChequesDetalle($q->orderBy('fecha_caucion')->orderBy('id')->get(), 'caucion');
    }

    /**
     * Totales cobro/pago al estilo Anita lista_cobro_pago: por valor/cuenta
     * (no solo abreviatura COB/OPP). Los e-cheqs no generan línea en
     * caja_movimiento_cuentacaja; se suman como fila «E CHQS».
     *
     * @param  list<int>  $empresaIds
     * @param  list<array<string, mixed>>  $chequesRecibidos
     * @param  list<array<string, mixed>>  $chequesEmitidos
     * @return array{cobro: float, pago: float, filas: list<array<string, mixed>>}
     */
    private function totalesCobroPago(
        array $empresaIds,
        string $desde,
        string $hasta,
        int $cuentaFiltro,
        array $chequesRecibidos = [],
        array $chequesEmitidos = []
    ): array {
        $q = Caja_Movimiento_Cuentacaja::query()
            ->join('caja_movimiento', 'caja_movimiento.id', '=', 'caja_movimiento_cuentacaja.caja_movimiento_id')
            ->join('tipotransaccion_caja', 'tipotransaccion_caja.id', '=', 'caja_movimiento.tipotransaccion_caja_id')
            ->leftJoin('cuentacaja', 'cuentacaja.id', '=', 'caja_movimiento_cuentacaja.cuentacaja_id')
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
            ->select(
                'caja_movimiento_cuentacaja.cuentacaja_id',
                'cuentacaja.codigo',
                'cuentacaja.nombre',
                'tipotransaccion_caja.abreviatura'
            )
            ->selectRaw('SUM(caja_movimiento_cuentacaja.monto * CASE WHEN COALESCE(caja_movimiento_cuentacaja.moneda_id, 1) > 1 THEN CASE WHEN COALESCE(caja_movimiento_cuentacaja.cotizacion, 0) > 0 THEN caja_movimiento_cuentacaja.cotizacion ELSE 1 END ELSE 1 END) as total');

        if ($empresaIds !== []) {
            $q->whereIn('caja_movimiento.empresa_id', $empresaIds);
        }
        if ($cuentaFiltro > 0) {
            $q->where('caja_movimiento_cuentacaja.cuentacaja_id', $cuentaFiltro);
        }

        /** @var array<string, array{aplicacion: string, cobro: float, pago: float, orden: string}> $porValor */
        $porValor = [];
        foreach ($q->groupBy(
            'caja_movimiento_cuentacaja.cuentacaja_id',
            'cuentacaja.codigo',
            'cuentacaja.nombre',
            'tipotransaccion_caja.abreviatura'
        )->get() as $row) {
            $abrev = (string) $row->abreviatura;
            $total = abs(round((float) $row->total, 2));
            if ($total < 0.0001) {
                continue;
            }
            $codigo = trim((string) ($row->codigo ?? ''));
            $nombre = trim((string) ($row->nombre ?? ''));
            $etiqueta = $codigo !== ''
                ? ($nombre !== '' ? $codigo.' — '.$nombre : $codigo)
                : ($nombre !== '' ? $nombre : 'Sin cuenta');
            $clave = (string) ((int) ($row->cuentacaja_id ?? 0)).'|'.$etiqueta;
            if (! isset($porValor[$clave])) {
                $porValor[$clave] = [
                    'aplicacion' => $etiqueta,
                    'cobro' => 0.0,
                    'pago' => 0.0,
                    'orden' => str_pad($codigo !== '' ? $codigo : 'ZZZ', 20, '0', STR_PAD_LEFT),
                ];
            }
            if ($abrev === 'COB') {
                $porValor[$clave]['cobro'] += $total;
            } else {
                $porValor[$clave]['pago'] += $total;
            }
        }

        $totalEcheqs = $this->sumaImportesFilasCheque($chequesRecibidos, soloEcheq: true);
        if ($totalEcheqs > 0.0001 && $cuentaFiltro === 0) {
            $porValor['E_CHQS'] = [
                'aplicacion' => 'E CHQS',
                'cobro' => $totalEcheqs,
                'pago' => 0.0,
                'orden' => '0000E_CHQS',
            ];
        }

        $totalChtPapel = $this->sumaImportesFilasCheque($chequesRecibidos, soloEcheq: false, excluirEcheq: true);
        if ($totalChtPapel > 0.0001 && $cuentaFiltro === 0) {
            $porValor['CHT'] = [
                'aplicacion' => 'Cheques de terceros',
                'cobro' => $totalChtPapel,
                'pago' => 0.0,
                'orden' => '0000CHT',
            ];
        }

        $totalChp = $this->sumaImportesFilasCheque($chequesEmitidos);
        if ($totalChp > 0.0001 && $cuentaFiltro === 0) {
            $porValor['CHP'] = [
                'aplicacion' => 'Cheques propios emitidos',
                'cobro' => 0.0,
                'pago' => $totalChp,
                'orden' => '0000CHP',
            ];
        }

        uasort($porValor, static function (array $a, array $b): int {
            return strcmp($a['orden'], $b['orden']);
        });

        $cobro = 0.0;
        $pago = 0.0;
        $filas = [];
        foreach ($porValor as $valor) {
            $cobroFila = round((float) $valor['cobro'], 2);
            $pagoFila = round((float) $valor['pago'], 2);
            if ($cobroFila < 0.0001 && $pagoFila < 0.0001) {
                continue;
            }
            $cobro += $cobroFila;
            $pago += $pagoFila;
            $filas[] = [
                'tipo_fila' => 'dato',
                'seccion' => 'cobro_pago',
                'aplicacion' => $valor['aplicacion'],
                'cobro' => $cobroFila,
                'pago' => $pagoFila,
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
     * @param  list<array<string, mixed>>  $filas
     */
    private function sumaImportesFilasCheque(
        array $filas,
        bool $soloEcheq = false,
        bool $excluirEcheq = false
    ): float {
        $total = 0.0;
        foreach ($filas as $fila) {
            if (($fila['tipo_fila'] ?? '') !== 'dato') {
                continue;
            }
            $esEcheq = ! empty($fila['es_echeq']) || trim((string) ($fila['nro_echeq'] ?? '')) !== '';
            if ($soloEcheq && ! $esEcheq) {
                continue;
            }
            if ($excluirEcheq && $esEcheq) {
                continue;
            }
            $total += (float) ($fila['importe'] ?? 0);
        }

        return round($total, 2);
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
            $nroEcheq = trim((string) ($ch->nro_echeq ?? ''));
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
                'nro_echeq' => $nroEcheq,
                'es_echeq' => $nroEcheq !== '',
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
