<?php

namespace App\Repositories\Contable;

use App\Models\Contable\Cuentacontable_Saldo_Mes;
use App\Support\Contable\CuentacontableSaldoMesSupport;
use Illuminate\Support\Facades\DB;

class Cuentacontable_Saldo_MesRepository implements Cuentacontable_Saldo_MesRepositoryInterface
{
    public function saldoMonedaOrigenHastaMes(
        int $empresaId,
        int $cuentacontableId,
        int $anioMesHasta,
        int $monedaId,
        ?int $centrocostoId = null,
    ): float {
        $query = Cuentacontable_Saldo_Mes::query()
            ->where('empresa_id', $empresaId)
            ->where('cuentacontable_id', $cuentacontableId)
            ->where('moneda_id', $monedaId)
            ->where('anio_mes', '<=', $anioMesHasta);

        $this->aplicarFiltroCentrocosto($query, $centrocostoId);

        return (float) ($query->sum('monto') ?? 0);
    }

    public function saldoMonedaLocalHastaMes(
        int $empresaId,
        int $cuentacontableId,
        int $anioMesHasta,
        ?int $centrocostoId = null,
    ): float {
        $query = Cuentacontable_Saldo_Mes::query()
            ->where('empresa_id', $empresaId)
            ->where('cuentacontable_id', $cuentacontableId)
            ->where('anio_mes', '<=', $anioMesHasta);

        $this->aplicarFiltroCentrocosto($query, $centrocostoId);

        return (float) ($query->sum('monto_local') ?? 0);
    }

    public function netoMesMonedaOrigen(
        int $empresaId,
        int $cuentacontableId,
        int $anioMes,
        int $monedaId,
        ?int $centrocostoId = null,
    ): float {
        $query = Cuentacontable_Saldo_Mes::query()
            ->where('empresa_id', $empresaId)
            ->where('cuentacontable_id', $cuentacontableId)
            ->where('moneda_id', $monedaId)
            ->where('anio_mes', $anioMes);

        $this->aplicarFiltroCentrocosto($query, $centrocostoId);

        $row = $query->first();

        return $row ? (float) $row->monto : 0.0;
    }

    public function reconstruir(?int $empresaId = null): int
    {
        $registros = 0;

        DB::transaction(function () use ($empresaId, &$registros) {
            $deleteQuery = Cuentacontable_Saldo_Mes::query();
            if ($empresaId) {
                $deleteQuery->where('empresa_id', $empresaId);
            }
            $deleteQuery->delete();

            /** @var array<string, array{
             *   monto: float,
             *   monto_local: float,
             *   debe: float,
             *   haber: float,
             *   debe_local: float,
             *   haber_local: float,
             *   attrs: array<string, mixed>
             * }> $aggregates */
            $aggregates = [];

            $query = DB::table('asiento_movimiento as am')
                ->join('asiento as a', 'a.id', '=', 'am.asiento_id')
                ->whereNotNull('am.cuentacontable_id')
                ->whereNotNull('am.moneda_id')
                ->select([
                    'a.empresa_id',
                    'am.cuentacontable_id',
                    'am.centrocosto_id',
                    'a.fecha',
                    'am.moneda_id',
                    'am.monto',
                    'am.cotizacion',
                ])
                ->orderBy('am.id');

            if ($empresaId) {
                $query->where('a.empresa_id', $empresaId);
            }

            foreach ($query->cursor() as $row) {
                $centrocostoId = CuentacontableSaldoMesSupport::normalizarCentrocostoId($row->centrocosto_id);
                $anioMes = CuentacontableSaldoMesSupport::anioMesDesdeFecha($row->fecha);
                $monedaId = (int) $row->moneda_id;
                $monto = (float) $row->monto;

                if ($anioMes <= 0 || abs($monto) < 1e-9) {
                    continue;
                }

                $ccKey = $centrocostoId === null ? 'null' : (string) $centrocostoId;
                $key = implode('|', [
                    (int) $row->empresa_id,
                    (int) $row->cuentacontable_id,
                    $ccKey,
                    $anioMes,
                    $monedaId,
                ]);

                if (! isset($aggregates[$key])) {
                    $aggregates[$key] = [
                        'monto' => 0.0,
                        'monto_local' => 0.0,
                        'debe' => 0.0,
                        'haber' => 0.0,
                        'debe_local' => 0.0,
                        'haber_local' => 0.0,
                        'attrs' => [
                            'empresa_id' => (int) $row->empresa_id,
                            'cuentacontable_id' => (int) $row->cuentacontable_id,
                            'centrocosto_id' => $centrocostoId,
                            'anio_mes' => $anioMes,
                            'moneda_id' => $monedaId,
                        ],
                    ];
                }

                $montoLocal = CuentacontableSaldoMesSupport::convertirMontoLocal(
                    $monto,
                    $monedaId,
                    $row->cotizacion,
                );

                $aggregates[$key]['monto'] += $monto;
                $aggregates[$key]['monto_local'] += $montoLocal;

                if ($monto > 0) {
                    $aggregates[$key]['debe'] += $monto;
                    $aggregates[$key]['debe_local'] += $montoLocal;
                } else {
                    $aggregates[$key]['haber'] += abs($monto);
                    $aggregates[$key]['haber_local'] += abs($montoLocal);
                }
            }

            $now = now();
            $batch = [];

            foreach ($aggregates as $item) {
                $batch[] = array_merge($item['attrs'], [
                    'debe' => $item['debe'],
                    'haber' => $item['haber'],
                    'debe_local' => $item['debe_local'],
                    'haber_local' => $item['haber_local'],
                    'monto' => $item['monto'],
                    'monto_local' => $item['monto_local'],
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
                $registros++;

                if (count($batch) >= 500) {
                    DB::table('cuentacontable_saldo_mes')->insert($batch);
                    $batch = [];
                }
            }

            if ($batch !== []) {
                DB::table('cuentacontable_saldo_mes')->insert($batch);
            }
        });

        return $registros;
    }

    private function aplicarFiltroCentrocosto($query, ?int $centrocostoId): void
    {
        $normalizado = CuentacontableSaldoMesSupport::normalizarCentrocostoId($centrocostoId);
        if ($normalizado === null) {
            $query->whereNull('centrocosto_id');
        } else {
            $query->where('centrocosto_id', $normalizado);
        }
    }
}
