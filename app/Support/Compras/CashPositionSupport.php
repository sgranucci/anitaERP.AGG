<?php

namespace App\Support\Compras;

use App\Models\Caja\Cuentacaja;
use App\Models\Caja\InterbankingSaldoDiario;
use App\Models\Compras\PropuestaPago;
use App\Models\Compras\Proveedor_Cuentacorriente;
use App\Models\Compras\Proveedor_Cuentacorriente_Aplicacion;
use App\Support\Caja\InterbankingSaldoResolverSupport;
use App\Support\Database\SqlDialectSupport;
use Illuminate\Support\Collection;

/**
 * Cash position: saldos Interbanking + deuda vencida + propuestas abiertas.
 */
class CashPositionSupport
{
    /**
     * @return array{
     *   deuda_vencida: Collection,
     *   propuestas_abiertas: Collection,
     *   saldos_interbanking: Collection,
     *   total_deuda: float,
     *   total_propuestas: float,
     *   total_saldos_ib: float,
     *   disponible_vs_deuda: float,
     *   disponible_vs_propuestas: float
     * }
     */
    public static function resumir(?int $empresaId = null): array
    {
        $hoy = date('Y-m-d');

        $query = Proveedor_Cuentacorriente::query()
            ->with(['proveedores', 'empresas', 'monedas'])
            ->select('proveedor_cuentacorriente.*')
            ->addSelect([
                'aplicado' => Proveedor_Cuentacorriente_Aplicacion::query()
                    ->selectRaw('SUM(total)')
                    ->whereColumn('proveedor_cuentacorriente_id', 'proveedor_cuentacorriente.id'),
            ])
            ->whereNull('proveedor_cuentacorriente.deleted_at')
            ->whereNotNull('proveedor_cuentacorriente.comprobante_proveedor_id')
            ->whereDate('fechavencimiento', '<=', $hoy)
            ->havingRaw('abs('.SqlDialectSupport::coalesce('aplicado', '0').') < abs(proveedor_cuentacorriente.total)');

        if ($empresaId && $empresaId > 0) {
            $query->where('empresa_id', $empresaId);
        }

        $deuda = $query->orderBy('fechavencimiento')->limit(500)->get()->map(function ($cc) {
            $aplicado = (float) ($cc->aplicado ?? 0);
            $cc->saldo = abs((float) $cc->total + $aplicado);

            return $cc;
        })->filter(fn ($cc) => $cc->saldo > 0.0001)->values();

        $estadosAbiertos = ['BORRADOR', 'EN_APROBACION', 'AUTORIZADA', 'EJECUTADA_PARCIAL'];
        $propQuery = PropuestaPago::query()
            ->with(['empresas'])
            ->whereIn('estado', $estadosAbiertos)
            ->orderByDesc('fecha');
        if ($empresaId && $empresaId > 0) {
            $propQuery->where('empresa_id', $empresaId);
        }
        $propuestas = $propQuery->limit(100)->get();

        $saldosIb = self::saldosInterbanking($empresaId);
        $totalDeuda = (float) $deuda->sum('saldo');
        $totalProp = (float) $propuestas->sum(fn ($p) => (float) ($p->monto_autorizado ?: $p->monto_total));
        $totalIb = (float) $saldosIb->sum('saldo');

        return [
            'deuda_vencida' => $deuda,
            'propuestas_abiertas' => $propuestas,
            'saldos_interbanking' => $saldosIb,
            'total_deuda' => $totalDeuda,
            'total_propuestas' => $totalProp,
            'total_saldos_ib' => $totalIb,
            'disponible_vs_deuda' => round($totalIb - $totalDeuda, 2),
            'disponible_vs_propuestas' => round($totalIb - $totalProp, 2),
            'forecast' => self::forecastBuckets($empresaId, $totalIb),
        ];
    }

    /**
     * Cash forecast calendario: vencido + 7 / 15 / 30 días + >30.
     *
     * @return array{
     *   buckets: list<array{clave:string,etiqueta:string,monto:float,cantidad:int}>,
     *   saldo_ib: float,
     *   proyeccion: list<array{clave:string,saldo_proyectado:float}>
     * }
     */
    public static function forecastBuckets(?int $empresaId, ?float $saldoIb = null): array
    {
        $hoy = date('Y-m-d');
        $d7 = date('Y-m-d', strtotime('+7 days'));
        $d15 = date('Y-m-d', strtotime('+15 days'));
        $d30 = date('Y-m-d', strtotime('+30 days'));

        $query = Proveedor_Cuentacorriente::query()
            ->select('proveedor_cuentacorriente.*')
            ->addSelect([
                'aplicado' => Proveedor_Cuentacorriente_Aplicacion::query()
                    ->selectRaw('SUM(total)')
                    ->whereColumn('proveedor_cuentacorriente_id', 'proveedor_cuentacorriente.id'),
            ])
            ->whereNull('proveedor_cuentacorriente.deleted_at')
            ->whereNotNull('proveedor_cuentacorriente.comprobante_proveedor_id')
            ->whereNotNull('proveedor_cuentacorriente.fechavencimiento')
            ->havingRaw('abs('.SqlDialectSupport::coalesce('aplicado', '0').') < abs(proveedor_cuentacorriente.total)');

        if ($empresaId && $empresaId > 0) {
            $query->where('empresa_id', $empresaId);
        }

        $rows = $query->limit(2000)->get();

        $buckets = [
            'vencido' => ['clave' => 'vencido', 'etiqueta' => 'Vencido', 'monto' => 0.0, 'cantidad' => 0],
            'd7' => ['clave' => 'd7', 'etiqueta' => '0–7 días', 'monto' => 0.0, 'cantidad' => 0],
            'd15' => ['clave' => 'd15', 'etiqueta' => '8–15 días', 'monto' => 0.0, 'cantidad' => 0],
            'd30' => ['clave' => 'd30', 'etiqueta' => '16–30 días', 'monto' => 0.0, 'cantidad' => 0],
            'mas30' => ['clave' => 'mas30', 'etiqueta' => '>30 días', 'monto' => 0.0, 'cantidad' => 0],
        ];

        foreach ($rows as $cc) {
            $aplicado = (float) ($cc->aplicado ?? 0);
            $saldo = abs((float) $cc->total + $aplicado);
            if ($saldo <= 0.0001) {
                continue;
            }
            $fv = optional($cc->fechavencimiento)->format('Y-m-d') ?? '';
            if ($fv === '') {
                continue;
            }
            if ($fv <= $hoy) {
                $k = 'vencido';
            } elseif ($fv <= $d7) {
                $k = 'd7';
            } elseif ($fv <= $d15) {
                $k = 'd15';
            } elseif ($fv <= $d30) {
                $k = 'd30';
            } else {
                $k = 'mas30';
            }
            $buckets[$k]['monto'] += $saldo;
            $buckets[$k]['cantidad']++;
        }

        foreach ($buckets as &$b) {
            $b['monto'] = round($b['monto'], 2);
        }
        unset($b);

        $saldo = $saldoIb;
        if ($saldo === null) {
            $saldo = (float) self::saldosInterbanking($empresaId)->sum('saldo');
        }

        $corrido = (float) $saldo;
        $proyeccion = [];
        foreach (['vencido', 'd7', 'd15', 'd30', 'mas30'] as $clave) {
            $corrido = round($corrido - (float) $buckets[$clave]['monto'], 2);
            $proyeccion[] = [
                'clave' => $clave,
                'etiqueta' => $buckets[$clave]['etiqueta'],
                'saldo_proyectado' => $corrido,
            ];
        }

        return [
            'buckets' => array_values($buckets),
            'saldo_ib' => round((float) $saldo, 2),
            'proyeccion' => $proyeccion,
        ];
    }

    /**
     * Último saldo IB por cuenta Interbanking vinculada a cuentacaja.
     *
     * @return Collection<int, object{empresa_id:int,cuenta:string,nombre:string,saldo:float,fecha:?string}>
     */
    private static function saldosInterbanking(?int $empresaId): Collection
    {
        $cuentas = Cuentacaja::query()
            ->whereNotNull('cuenta_interbanking')
            ->where('cuenta_interbanking', '!=', '')
            ->when($empresaId && $empresaId > 0, function ($q) use ($empresaId) {
                // empresa vía pivote si existe; si no, todas las cuentas con IB
                if (\Illuminate\Support\Facades\Schema::hasColumn('cuentacaja', 'empresa_id')) {
                    $q->where('empresa_id', $empresaId);
                }
            })
            ->orderBy('codigo')
            ->limit(80)
            ->get();

        $out = collect();

        foreach ($cuentas as $cc) {
            $account = trim((string) $cc->cuenta_interbanking);
            if ($account === '') {
                continue;
            }
            $empId = (int) ($empresaId ?: ($cc->empresa_id ?? 0));
            if ($empId <= 0) {
                $row = InterbankingSaldoDiario::query()
                    ->where('account_number', $account)
                    ->orderByDesc('fecha')
                    ->first();
                $saldo = $row
                    ? (float) ($row->countable_balance ?? $row->current_operating_balance ?? $row->day_balance ?? 0)
                    : 0.0;
                $fecha = optional($row?->fecha)->format('Y-m-d');
            } else {
                $saldo = InterbankingSaldoResolverSupport::saldoEnFecha(
                    $empId,
                    $account,
                    \Carbon\Carbon::today()
                );
                $fecha = date('Y-m-d');
            }

            $out->push((object) [
                'empresa_id' => $empId,
                'cuenta' => $account,
                'nombre' => trim(($cc->codigo ?? '').' '.($cc->nombre ?? '')),
                'saldo' => $saldo,
                'fecha' => $fecha,
            ]);
        }

        if ($out->isEmpty() && $empresaId && $empresaId > 0) {
            $rows = InterbankingSaldoDiario::query()
                ->where('empresa_id', $empresaId)
                ->orderByDesc('fecha')
                ->limit(40)
                ->get()
                ->unique('account_number');
            foreach ($rows as $row) {
                $out->push((object) [
                    'empresa_id' => $empresaId,
                    'cuenta' => (string) $row->account_number,
                    'nombre' => (string) ($row->account_label ?? $row->account_name ?? $row->account_number),
                    'saldo' => (float) ($row->countable_balance ?? $row->current_operating_balance ?? $row->day_balance ?? 0),
                    'fecha' => optional($row->fecha)->format('Y-m-d'),
                ]);
            }
        }

        return $out->values();
    }
}
