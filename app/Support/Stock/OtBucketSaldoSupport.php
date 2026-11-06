<?php

namespace App\Support\Stock;

use App\Models\Ventas\Ordentrabajo;
use Illuminate\Support\Facades\DB;

/**
 * Saldo de un bucket de OT/lote por depósito: punto único.
 *
 * Stock por OT, el picking, el consumo de OT stock, el modal F1 y el control de
 * egresos leen el saldo de acá. El bucket es el que arma OtBucketStockSupport y
 * el saldo es la suma de talles (`articulo_movimiento_talle`), sin la factura ni
 * la devolución de un compañero de OT.
 */
final class OtBucketSaldoSupport
{
    /**
     * Buckets del lote y de la OT, por depósito.
     *
     * @return array<int, array{codigo: string, nombre: string, buckets: list<array{clave: string, lote: int|string, ordentrabajo_id: int, saldo: float, talles: array<string, float>}>}>
     */
    public static function porDeposito(
        int $articuloId,
        int $combinacionId,
        string $loteCodigo,
        int $otId,
        int $excluirPedidoCombinacionId = 0,
        int $excluirMovimientoStockId = 0
    ): array {
        $rows = DB::table('articulo_movimiento as am')
            ->join('articulo_movimiento_talle as amt', 'amt.articulo_movimiento_id', '=', 'am.id')
            ->join('talle as t', 't.id', '=', 'amt.talle_id')
            ->leftJoin('depmae as d', 'd.id', '=', 'am.deposito_id')
            ->where('am.articulo_id', $articuloId)
            ->where('am.combinacion_id', $combinacionId)
            ->where(function ($q) use ($loteCodigo, $otId) {
                $q->where('am.lote', $loteCodigo);
                if ($otId > 0) {
                    $q->orWhere('am.ordentrabajo_id', $otId);
                }
            });

        if ($excluirPedidoCombinacionId > 0) {
            $rows->where(function ($w) use ($excluirPedidoCombinacionId) {
                $w->whereNull('am.pedido_combinacion_id')
                    ->orWhere('am.pedido_combinacion_id', '<>', $excluirPedidoCombinacionId);
            });
        }
        if ($excluirMovimientoStockId > 0) {
            $rows->where(function ($w) use ($excluirMovimientoStockId) {
                $w->whereNull('am.movimientostock_id')
                    ->orWhere('am.movimientostock_id', '<>', $excluirMovimientoStockId);
            });
        }

        OtClienteCompartidoStockSupport::excluirConsumoFacturaCompanero($rows, 'am');

        $rows = $rows
            ->groupBy('am.deposito_id', 'd.codigo', 'd.nombre', 'am.lote', 'am.ordentrabajo_id', 't.nombre')
            ->select([
                'am.deposito_id',
                'd.codigo as deposito_codigo',
                'd.nombre as deposito_nombre',
                'am.lote',
                'am.ordentrabajo_id',
                't.nombre as talle',
                DB::raw('SUM(amt.cantidad) as saldo'),
            ])
            ->get();

        $out = [];
        foreach ($rows as $row) {
            $depId = (int) ($row->deposito_id ?? 0);
            if ($depId <= 0) {
                continue;
            }
            if (! isset($out[$depId])) {
                $out[$depId] = [
                    'codigo' => (string) ($row->deposito_codigo ?? ''),
                    'nombre' => (string) ($row->deposito_nombre ?? ''),
                    'buckets' => [],
                ];
            }

            $loteRaw = $row->lote ?? 0;
            $otKey = (int) ($row->ordentrabajo_id ?? 0);
            // El consumo desde otra OT guarda la OT de origen en lote y la que consume en
            // ordentrabajo_id. Unificado contra el alta para que el saldo del bucket sea el neto.
            $otBucket = OtBucketStockSupport::otIdDeLote($loteRaw, $articuloId, $combinacionId);
            $loteKey = ($otBucket <= 0 && ReporteStockOtSituacionSupport::esLoteImportado($loteRaw))
                ? trim((string) $loteRaw)
                : '0';
            $clave = OtBucketStockSupport::claveBucket($loteRaw, $otKey, $articuloId, $combinacionId);
            if (! isset($out[$depId]['buckets'][$clave])) {
                // Lote importado: ot_id solo si es la OT del código (mismo efecto visual L:codigo).
                // OT: el ordentrabajo_id del bucket, no el de la OT que consume.
                $otWrite = 0;
                if ($otBucket > 0) {
                    $otWrite = $otBucket;
                } elseif ($loteKey === '0') {
                    $otWrite = $otKey;
                } elseif ($otKey === $otId && $otId > 0) {
                    $otWrite = $otKey;
                }
                $out[$depId]['buckets'][$clave] = [
                    'clave' => $clave,
                    'lote' => $loteKey === '0' ? 0 : $loteKey,
                    'ordentrabajo_id' => $otWrite,
                    'saldo' => 0.0,
                    'talles' => [],
                ];
            }
            $cant = (float) ($row->saldo ?? 0);
            $out[$depId]['buckets'][$clave]['saldo'] += $cant;
            $talleNom = trim((string) ($row->talle ?? ''));
            if ($talleNom !== '' && abs($cant) > 0.0001) {
                $out[$depId]['buckets'][$clave]['talles'][$talleNom] =
                    (float) (($out[$depId]['buckets'][$clave]['talles'][$talleNom] ?? 0) + $cant);
            }
        }

        foreach ($out as $depId => $info) {
            $out[$depId]['buckets'] = array_values($info['buckets']);
        }

        return $out;
    }

    /**
     * Saldo, por depósito, del bucket que recibe un movimiento grabado con ese lote y esa OT.
     *
     * @return array<int, array{clave: string, saldo: float, talles: array<string, float>, codigo: string, nombre: string}>
     */
    public static function saldosBucketPorDeposito(
        int $articuloId,
        int $combinacionId,
        mixed $loteGrabar,
        int $otGrabar,
        int $excluirPedidoCombinacionId = 0,
        int $excluirMovimientoStockId = 0
    ): array {
        if ($articuloId <= 0 || $combinacionId <= 0) {
            return [];
        }

        $otBucket = OtBucketStockSupport::ordentrabajoIdDelBucket($loteGrabar, $otGrabar, $articuloId, $combinacionId);
        $clave = OtBucketStockSupport::claveDesdeOtResuelta($loteGrabar, $otBucket);
        $loteConsulta = self::loteConsultaDelBucket($loteGrabar, $otBucket);
        if ($loteConsulta === null) {
            return [];
        }

        $porDeposito = self::porDeposito(
            $articuloId,
            $combinacionId,
            $loteConsulta,
            $otBucket,
            $excluirPedidoCombinacionId,
            $excluirMovimientoStockId
        );

        $out = [];
        foreach ($porDeposito as $depId => $info) {
            foreach ($info['buckets'] as $bucket) {
                if ($bucket['clave'] !== $clave) {
                    continue;
                }
                $out[$depId] = [
                    'clave' => $clave,
                    'saldo' => (float) $bucket['saldo'],
                    'talles' => $bucket['talles'],
                    'codigo' => (string) $info['codigo'],
                    'nombre' => (string) $info['nombre'],
                ];
                break;
            }
        }

        return $out;
    }

    /**
     * @return array{clave: string, saldo: float, talles: array<string, float>}
     */
    public static function saldoBucket(
        int $articuloId,
        int $combinacionId,
        mixed $loteGrabar,
        int $otGrabar,
        int $depositoId,
        int $excluirPedidoCombinacionId = 0,
        int $excluirMovimientoStockId = 0
    ): array {
        $porDeposito = self::saldosBucketPorDeposito(
            $articuloId,
            $combinacionId,
            $loteGrabar,
            $otGrabar,
            $excluirPedidoCombinacionId,
            $excluirMovimientoStockId
        );
        $fila = $porDeposito[$depositoId] ?? null;

        return [
            'clave' => $fila['clave'] ?? OtBucketStockSupport::claveBucket($loteGrabar, $otGrabar, $articuloId, $combinacionId),
            'saldo' => (float) ($fila['saldo'] ?? 0),
            'talles' => $fila['talles'] ?? [],
        ];
    }

    /**
     * Depósito del que sale un consumo: el de mayor saldo (a igual saldo, el del alta).
     * Sin saldo en ninguno, el del alta (el control de saldo lo rechaza).
     *
     * @param  array<int, array{saldo: float}>  $saldosPorDeposito
     */
    public static function depositoParaConsumir(array $saldosPorDeposito, int $depositoAlta): int
    {
        $elegido = 0;
        $mayor = 0.0001;
        foreach ($saldosPorDeposito as $depId => $info) {
            $saldo = (float) ($info['saldo'] ?? 0);
            if ($saldo > $mayor) {
                $mayor = $saldo;
                $elegido = (int) $depId;
            }
        }
        if ($depositoAlta > 0 && (float) ($saldosPorDeposito[$depositoAlta]['saldo'] ?? 0) >= $mayor) {
            return $depositoAlta;
        }

        return $elegido > 0 ? $elegido : $depositoAlta;
    }

    /**
     * Stock por OT muestra el bucket si el picking lo puede consumir: lote importado,
     * OT con talles del cliente STOCK o saldo positivo (p. ej. alta de Excel de una OT
     * comercial). Una OT comercial en cero o negativo es el consumo de su propio pedido.
     */
    public static function bucketVisibleEnStockPorOt(string $clave, bool $otEsStock, float $saldo): bool
    {
        if (! str_starts_with($clave, 'OT:')) {
            return true;
        }

        return $otEsStock || $saldo > 0.0001;
    }

    /**
     * OT con al menos un talle asignado al cliente STOCK.
     *
     * @param  list<int>  $otIds
     * @return array<int, bool>
     */
    public static function otsDeClienteStock(array $otIds): array
    {
        $otIds = array_values(array_unique(array_filter(array_map('intval', $otIds), static fn (int $id) => $id > 0)));
        $out = array_fill_keys($otIds, false);
        if ($otIds === []) {
            return $out;
        }

        $stockId = ReporteStockOtSituacionSupport::clienteStockId();
        foreach (array_chunk($otIds, 1000) as $lote) {
            $ids = DB::table('ordentrabajo_combinacion_talle')
                ->whereIn('ordentrabajo_id', $lote)
                ->where('cliente_id', $stockId)
                ->distinct()
                ->pluck('ordentrabajo_id');
            foreach ($ids as $id) {
                $out[(int) $id] = true;
            }
        }

        return $out;
    }

    /**
     * Valor de `lote` con el que se buscan los movimientos del bucket. null = no hay bucket.
     */
    private static function loteConsultaDelBucket(mixed $loteGrabar, int $otBucket): ?string
    {
        if (ReporteStockOtSituacionSupport::esLoteImportado($loteGrabar)) {
            return trim((string) $loteGrabar);
        }
        if ($otBucket <= 0) {
            return null;
        }
        $codigo = trim((string) (Ordentrabajo::query()->whereKey($otBucket)->value('codigo') ?? ''));

        return $codigo !== '' && ctype_digit($codigo) ? $codigo : '0';
    }
}
