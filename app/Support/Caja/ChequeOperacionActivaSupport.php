<?php

declare(strict_types=1);

namespace App\Support\Caja;

use App\Models\Caja\Caja_Movimiento;
use App\Models\Caja\Cheque;
use App\Models\Caja\Cobranza;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Un cheque no opera en cierre/cartera si su cobranza o movimiento de caja está anulado.
 * La cobranza crea el valor con cobranza_id (sin caja_movimiento_id): al revertir el
 * movimiento hay que mirar esa cobranza, no solo cheque.caja_movimiento_id.
 */
final class ChequeOperacionActivaSupport
{
    /**
     * @param  Builder<Cheque>  $q
     * @param  list<string>  $estadosChequeExcluir
     */
    public static function aplicarFiltroQuery(Builder $q, array $estadosChequeExcluir = ['A', 'R', '*']): void
    {
        if ($estadosChequeExcluir !== []) {
            $q->where(function (Builder $w) use ($estadosChequeExcluir) {
                $w->whereNull('estado')->orWhereNotIn('estado', $estadosChequeExcluir);
            });
        }

        $q->where(function (Builder $w) {
            $w->where(function (Builder $sinCob) {
                $sinCob->whereNull('cobranza_id')->orWhere('cobranza_id', 0);
            })->orWhereHas('cobranzas', function (Builder $c) {
                $c->whereNotIn('estado', ['BAJA', 'REVERTIDA'])
                    ->whereDoesntHave('caja_movimientos', function (Builder $mov) {
                        $mov->where(function (Builder $est) {
                            self::whereUltimoEstadoEn($est, ['R', 'B', 'S']);
                        });
                    });
            });
        });

        $q->where(function (Builder $w) {
            $w->where(function (Builder $sinMov) {
                $sinMov->whereNull('caja_movimiento_id')->orWhere('caja_movimiento_id', 0);
            })->orWhereHas('caja_movimientos', function (Builder $mov) {
                $mov->where(function (Builder $est) {
                    $est->whereNotExists(function ($sub) {
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
            });
        });
    }

    /**
     * @param  list<string>  $estados
     */
    private static function whereUltimoEstadoEn(Builder $q, array $estados): void
    {
        $q->whereExists(function ($sub) use ($estados) {
            $sub->select(DB::raw(1))
                ->from('caja_movimiento_estado as e')
                ->whereColumn('e.caja_movimiento_id', 'caja_movimiento.id')
                ->whereIn('e.estado', $estados)
                ->whereRaw('e.id = (select max(e2.id) from caja_movimiento_estado e2 where e2.caja_movimiento_id = caja_movimiento.id)');
        });
    }

    public static function anularPorCobranza(int $cobranzaId): int
    {
        if ($cobranzaId <= 0) {
            return 0;
        }

        $n = 0;
        Cheque::query()
            ->where('cobranza_id', $cobranzaId)
            ->where(function (Builder $w) {
                $w->whereNull('estado')->orWhereNotIn('estado', ['A']);
            })
            ->get()
            ->each(function (Cheque $cheque) use (&$n) {
                $cheque->estado = 'A';
                $cheque->save();
                $n++;
            });

        return $n;
    }

    public static function anularPorCajaMovimiento(Caja_Movimiento $movimiento): int
    {
        $ids = [];
        foreach ($movimiento->cheques as $cheque) {
            $ids[(int) $cheque->id] = $cheque;
        }

        $cobranzaId = (int) ($movimiento->cobranza_id ?? 0);
        if ($cobranzaId > 0) {
            foreach (Cheque::query()->where('cobranza_id', $cobranzaId)->get() as $cheque) {
                $ids[(int) $cheque->id] = $cheque;
            }
        }

        $n = 0;
        foreach ($ids as $cheque) {
            $origen = strtoupper((string) ($cheque->origen ?? ''));
            if ($origen === 'E') {
                $cheque->estado = 'A';
                $cheque->save();
                $n++;
                continue;
            }

            // Recibido creado por la cobranza / el movimiento: no vuelve a cartera.
            if ((int) ($cheque->cobranza_id ?? 0) > 0 || empty($cheque->nro_interno_anita)) {
                $cheque->estado = 'A';
                $cheque->save();
                $n++;
                continue;
            }

            // Valor de cartera usado en el movimiento: se desvincula.
            $cheque->update([
                'caja_movimiento_id' => null,
                'pagoproveedor_id' => null,
                'caja_id' => null,
            ]);
        }

        return $n;
    }

    public static function marcarCobranzaRevertida(int $cobranzaId): void
    {
        if ($cobranzaId <= 0) {
            return;
        }

        $cobranza = Cobranza::query()->find($cobranzaId);
        if ($cobranza && ! in_array((string) $cobranza->estado, ['BAJA', 'REVERTIDA'], true)) {
            $cobranza->estado = 'REVERTIDA';
            $cobranza->save();
        }

        self::anularPorCobranza($cobranzaId);
    }
}
