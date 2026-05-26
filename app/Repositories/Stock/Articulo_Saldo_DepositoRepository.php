<?php

namespace App\Repositories\Stock;

use App\Models\Stock\Articulo_Saldo_Deposito;
use Illuminate\Support\Facades\DB;

class Articulo_Saldo_DepositoRepository implements Articulo_Saldo_DepositoRepositoryInterface
{
    public function saldo(int $articuloId, int $depositoId): float
    {
        $row = Articulo_Saldo_Deposito::query()
            ->where('articulo_id', $articuloId)
            ->where('deposito_id', $depositoId)
            ->first();

        return $row ? (float) $row->cantidad : 0.0;
    }

    public function saldosArticuloPorDeposito(int $articuloId, array $depositoIds = []): array
    {
        $query = Articulo_Saldo_Deposito::query()
            ->where('articulo_id', $articuloId);

        if (! empty($depositoIds)) {
            $query->whereIn('deposito_id', $depositoIds);
        }

        $rows = $query->get();

        $resultado = [];
        if (! empty($depositoIds)) {
            foreach ($depositoIds as $id) {
                $resultado[(int) $id] = 0.0;
            }
        }
        foreach ($rows as $row) {
            $resultado[(int) $row->deposito_id] = (float) $row->cantidad;
        }

        return $resultado;
    }

    public function saldosDeposito(int $depositoId)
    {
        return Articulo_Saldo_Deposito::query()
            ->with(['articulos:id,sku,descripcion'])
            ->where('deposito_id', $depositoId)
            ->orderBy('articulo_id')
            ->get();
    }

    public function reconstruir(?int $depositoId = null): int
    {
        $registros = 0;
        DB::transaction(function () use ($depositoId, &$registros) {
            if ($depositoId) {
                Articulo_Saldo_Deposito::where('deposito_id', $depositoId)->delete();
                $rows = DB::table('articulo_movimiento')
                    ->selectRaw('articulo_id, deposito_id,
                        SUM(cantidad) AS total,
                        MAX(fecha) AS ultima')
                    ->whereNotNull('articulo_id')
                    ->where('deposito_id', $depositoId)
                    ->whereNull('deleted_at')
                    ->groupBy('articulo_id', 'deposito_id')
                    ->get();
            } else {
                Articulo_Saldo_Deposito::query()->delete();
                $rows = DB::table('articulo_movimiento')
                    ->selectRaw('articulo_id, deposito_id,
                        SUM(cantidad) AS total,
                        MAX(fecha) AS ultima')
                    ->whereNotNull('articulo_id')
                    ->whereNotNull('deposito_id')
                    ->whereNull('deleted_at')
                    ->groupBy('articulo_id', 'deposito_id')
                    ->get();
            }

            $batch = [];
            $now = now();
            foreach ($rows as $row) {
                $batch[] = [
                    'articulo_id' => (int) $row->articulo_id,
                    'deposito_id' => (int) $row->deposito_id,
                    'cantidad' => (float) $row->total,
                    'fecha_ult_movimiento' => $row->ultima ? (string) $row->ultima.' 00:00:00' : null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
                $registros++;

                if (count($batch) >= 1000) {
                    DB::table('articulo_saldo_deposito')->insert($batch);
                    $batch = [];
                }
            }
            if (! empty($batch)) {
                DB::table('articulo_saldo_deposito')->insert($batch);
            }
        });

        return $registros;
    }
}
