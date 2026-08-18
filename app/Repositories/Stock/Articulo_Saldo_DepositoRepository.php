<?php

namespace App\Repositories\Stock;

use App\Support\Database\SqlDialectSupport;
use App\Models\Stock\Articulo_Saldo_Deposito;
use App\Support\Stock\ArticuloStockColorTalleSupport;
use Illuminate\Support\Facades\DB;

class Articulo_Saldo_DepositoRepository implements Articulo_Saldo_DepositoRepositoryInterface
{
    public function saldo(int $articuloId, int $depositoId): float
    {
        // Total del artículo en el depósito (suma todas las variantes).
        $total = Articulo_Saldo_Deposito::query()
            ->where('articulo_id', $articuloId)
            ->where('deposito_id', $depositoId)
            ->sum('cantidad');

        return (float) ($total ?? 0);
    }

    public function saldoVariante(int $articuloId, int $depositoId, ?int $colorId, ?int $talleId): float
    {
        [$colorKey, $talleKey] = ArticuloStockColorTalleSupport::claveSaldo($colorId, $talleId);

        $row = Articulo_Saldo_Deposito::query()
            ->where('articulo_id', $articuloId)
            ->where('deposito_id', $depositoId)
            ->where('color_id', $colorKey)
            ->where('talle_id', $talleKey)
            ->first();

        return $row ? (float) $row->cantidad : 0.0;
    }

    public function saldoAFecha(int $articuloId, int $depositoId, string $fecha): float
    {
        $total = DB::table('articulo_movimiento')
            ->where('articulo_id', $articuloId)
            ->where('deposito_id', $depositoId)
            ->where('fecha', '<=', $fecha)
            ->sum('cantidad');

        return (float) ($total ?? 0);
    }

    public function saldoVarianteAFecha(
        int $articuloId,
        int $depositoId,
        string $fecha,
        ?int $colorId,
        ?int $talleId
    ): float {
        return $this->sumaVarianteMovimientos($articuloId, $depositoId, $colorId, $talleId, '<=', $fecha);
    }

    public function sumaVariantePosteriorAFecha(
        int $articuloId,
        int $depositoId,
        string $fecha,
        ?int $colorId,
        ?int $talleId
    ): float {
        return $this->sumaVarianteMovimientos($articuloId, $depositoId, $colorId, $talleId, '>', $fecha);
    }

    private function sumaVarianteMovimientos(
        int $articuloId,
        int $depositoId,
        ?int $colorId,
        ?int $talleId,
        string $operadorFecha,
        string $fecha
    ): float {
        [$colorKey, $talleKey] = ArticuloStockColorTalleSupport::claveSaldo($colorId, $talleId);

        $query = DB::table('articulo_movimiento')
            ->where('articulo_id', $articuloId)
            ->where('deposito_id', $depositoId)
            ->where('fecha', $operadorFecha, $fecha);

        if ($colorKey === ArticuloStockColorTalleSupport::SIN_VARIANTE) {
            $query->where(function ($q) {
                $q->whereNull('color_id')->orWhere('color_id', 0);
            });
        } else {
            $query->where('color_id', $colorKey);
        }

        if ($talleKey === ArticuloStockColorTalleSupport::SIN_VARIANTE) {
            $query->where(function ($q) {
                $q->whereNull('talle_id')->orWhere('talle_id', 0);
            });
        } else {
            $query->where('talle_id', $talleKey);
        }

        return (float) ($query->sum('cantidad') ?? 0);
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
            $depId = (int) $row->deposito_id;
            $resultado[$depId] = ($resultado[$depId] ?? 0.0) + (float) $row->cantidad;
        }

        return $resultado;
    }

    public function saldosDeposito(int $depositoId)
    {
        return Articulo_Saldo_Deposito::query()
            ->with(['articulos:id,sku,descripcion'])
            ->where('deposito_id', $depositoId)
            ->orderBy('articulo_id')
            ->orderBy('color_id')
            ->orderBy('talle_id')
            ->get();
    }

    public function reconstruir(?int $depositoId = null): int
    {
        $registros = 0;
        DB::transaction(function () use ($depositoId, &$registros) {
            $colorExpr = SqlDialectSupport::coalesce('NULLIF(color_id, 0)', '0');
            $talleExpr = SqlDialectSupport::coalesce('NULLIF(talle_id, 0)', '0');

            if ($depositoId) {
                Articulo_Saldo_Deposito::where('deposito_id', $depositoId)->delete();
                $rows = DB::table('articulo_movimiento')
                    ->selectRaw("articulo_id, deposito_id,
                        {$colorExpr} AS color_id,
                        {$talleExpr} AS talle_id,
                        SUM(cantidad) AS total,
                        MAX(fecha) AS ultima")
                    ->whereNotNull('articulo_id')
                    ->where('deposito_id', $depositoId)
                    ->groupByRaw("articulo_id, deposito_id, {$colorExpr}, {$talleExpr}")
                    ->get();
            } else {
                Articulo_Saldo_Deposito::query()->delete();
                $rows = DB::table('articulo_movimiento')
                    ->selectRaw("articulo_id, deposito_id,
                        {$colorExpr} AS color_id,
                        {$talleExpr} AS talle_id,
                        SUM(cantidad) AS total,
                        MAX(fecha) AS ultima")
                    ->whereNotNull('articulo_id')
                    ->whereNotNull('deposito_id')
                    ->groupByRaw("articulo_id, deposito_id, {$colorExpr}, {$talleExpr}")
                    ->get();
            }

            $batch = [];
            $now = now();
            foreach ($rows as $row) {
                $batch[] = [
                    'articulo_id' => (int) $row->articulo_id,
                    'deposito_id' => (int) $row->deposito_id,
                    'color_id' => (int) $row->color_id,
                    'talle_id' => (int) $row->talle_id,
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
