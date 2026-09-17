<?php

namespace App\Services\Ventas\Ferli;

use App\Services\Stock\PrecioServiceFerli;
use App\Support\Ventas\Ferli\FerliL8ReaderSupport;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

/**
 * Completa en L12 las líneas (combinación/talle/estado) que existen en L8
 * para pedidos cuya cabecera ya está en L12 pero quedó incompleta o vacía.
 *
 * Si el id de L8 ya está usado por otro pedido en L12, inserta con id nuevo
 * (autoincrement) y remapea talles/estados.
 */
class PedidoRepararLineasHuerfanasDesdeL8Service
{
    public function __construct(
        private readonly FerliL8ReaderSupport $reader,
        private readonly PrecioServiceFerli $precioService,
    ) {
    }

    /**
     * @return array{
     *   fuente: string,
     *   pedidos: int,
     *   insert_combinacion: int,
     *   insert_talle: int,
     *   insert_estado: int,
     *   precios_aplicados: int,
     *   ot_omitidas: int,
     *   detalle: list<string>,
     *   errores: list<string>
     * }
     */
    public function reparar(bool $dryRun = true, ?int $soloPedidoId = null): array
    {
        FerliL8ReaderSupport::assertFerli();

        $fuente = $this->reader->resolverFuente();
        if ($fuente['fuente'] !== 'mysql_l8' || $fuente['conexion'] === null) {
            throw new RuntimeException('La reparación de huérfanos requiere conexión mysql_l8 directa.');
        }

        /** @var \Illuminate\Database\ConnectionInterface $l8 */
        $l8 = $fuente['conexion'];

        $stats = [
            'fuente' => $fuente['fuente'],
            'pedidos' => 0,
            'insert_combinacion' => 0,
            'insert_talle' => 0,
            'insert_estado' => 0,
            'precios_aplicados' => 0,
            'ot_omitidas' => 0,
            'detalle' => [],
            'errores' => [],
        ];

        $casos = $this->detectarCasos($l8, $soloPedidoId);
        $stats['pedidos'] = count($casos);

        if ($casos === []) {
            $stats['detalle'][] = 'No hay pedidos L12 con menos líneas que L8.';

            return $stats;
        }

        foreach ($casos as $caso) {
            $stats['detalle'][] = sprintf(
                'Pedido %d (cod %s): L12=%d L8=%d → faltan %d líneas',
                $caso['pedido_id'],
                $caso['codigo'],
                $caso['n12'],
                $caso['n8'],
                count($caso['faltantes'])
            );
            foreach ($caso['faltantes'] as $f) {
                $stats['detalle'][] = sprintf(
                    '  pc L8=%d item=%d comb=%d art=%d ot=%s → %s',
                    $f['pc_id'],
                    $f['numeroitem'],
                    $f['combinacion_id'],
                    $f['articulo_id'],
                    $f['ot_id'] > 0 ? (string) $f['ot_id'] : '-',
                    $f['usar_id_libre'] ? 'mismo id' : 'id nuevo (colisión)'
                );
                if ($f['ot_omitida']) {
                    $stats['ot_omitidas']++;
                    $stats['detalle'][] = '    ot_id omitido: ya está ligado a otro pedido en L8/L12';
                } elseif (! empty($f['ot_reclamar_de_pc_id'])) {
                    $stats['detalle'][] = sprintf(
                        '    ot_id %d se reclama de pc L12 %d (asignación errónea)',
                        $f['ot_id'],
                        $f['ot_reclamar_de_pc_id']
                    );
                }
            }
        }

        if ($dryRun) {
            return $stats;
        }

        DB::beginTransaction();
        try {
            DB::statement('SET FOREIGN_KEY_CHECKS=0');

            foreach ($casos as $caso) {
                foreach ($caso['faltantes'] as $f) {
                    $resultado = $this->insertarLineaDesdeL8($l8, $caso['pedido_id'], $f);
                    $stats['insert_combinacion'] += $resultado['combinacion'];
                    $stats['insert_talle'] += $resultado['talle'];
                    $stats['insert_estado'] += $resultado['estado'];
                    $stats['precios_aplicados'] += $resultado['precios'];
                }
            }

            DB::statement('SET FOREIGN_KEY_CHECKS=1');
            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            try {
                DB::statement('SET FOREIGN_KEY_CHECKS=1');
            } catch (\Throwable) {
            }
            Log::error('ferli.l8.reparar_huerfanos.fallo', ['error' => $e->getMessage()]);
            throw new RuntimeException('Error reparando huérfanos desde L8: '.$e->getMessage(), 0, $e);
        }

        Log::info('ferli.l8.reparar_huerfanos.ok', $stats);

        return $stats;
    }

    /**
     * @param  \Illuminate\Database\ConnectionInterface  $l8
     * @return list<array{
     *   pedido_id: int,
     *   codigo: string,
     *   n12: int,
     *   n8: int,
     *   faltantes: list<array{
     *     pc_id: int,
     *     numeroitem: int,
     *     combinacion_id: int,
     *     articulo_id: int,
     *     ot_id: int,
     *     usar_id_libre: bool,
     *     ot_omitida: bool,
     *     row: array<string, mixed>
     *   }>
     * }>
     */
    private function detectarCasos($l8, ?int $soloPedidoId): array
    {
        $query = DB::table('pedido')->select(['id', 'codigo']);
        if ($soloPedidoId !== null) {
            $query->where('id', $soloPedidoId);
        }
        $pedidos = $query->orderBy('id')->get();

        $casos = [];
        foreach ($pedidos->chunk(400) as $chunk) {
            $ids = $chunk->pluck('id')->map(static fn ($id) => (int) $id)->all();
            $counts12 = DB::table('pedido_combinacion')
                ->whereIn('pedido_id', $ids)
                ->select('pedido_id', DB::raw('count(*) as c'))
                ->groupBy('pedido_id')
                ->pluck('c', 'pedido_id');
            $counts8 = $l8->table('pedido_combinacion')
                ->whereIn('pedido_id', $ids)
                ->select('pedido_id', DB::raw('count(*) as c'))
                ->groupBy('pedido_id')
                ->pluck('c', 'pedido_id');

            foreach ($chunk as $p) {
                $pedidoId = (int) $p->id;
                $n8 = (int) ($counts8[$pedidoId] ?? 0);
                $n12 = (int) ($counts12[$pedidoId] ?? 0);
                if ($n8 <= $n12) {
                    continue;
                }

                $faltantes = $this->lineasFaltantes($l8, $pedidoId);
                if ($faltantes === []) {
                    continue;
                }

                $casos[] = [
                    'pedido_id' => $pedidoId,
                    'codigo' => (string) $p->codigo,
                    'n12' => $n12,
                    'n8' => $n8,
                    'faltantes' => $faltantes,
                ];
            }
        }

        return $casos;
    }

    /**
     * @param  \Illuminate\Database\ConnectionInterface  $l8
     * @return list<array{
     *   pc_id: int,
     *   numeroitem: int,
     *   combinacion_id: int,
     *   articulo_id: int,
     *   ot_id: int,
     *   usar_id_libre: bool,
     *   ot_omitida: bool,
     *   ot_reclamar_de_pc_id: int|null,
     *   row: array<string, mixed>
     * }>
     */
    private function lineasFaltantes($l8, int $pedidoId): array
    {
        $existentesL12 = DB::table('pedido_combinacion')
            ->where('pedido_id', $pedidoId)
            ->get(['id', 'numeroitem', 'combinacion_id']);

        $idsPresentes = $existentesL12->pluck('id')->map(static fn ($id) => (int) $id)->flip()->all();
        $clavesPresentes = [];
        foreach ($existentesL12 as $ex) {
            $clavesPresentes[(int) $ex->numeroitem.'|'.(int) $ex->combinacion_id] = true;
        }

        $faltantes = [];
        foreach ($l8->table('pedido_combinacion')->where('pedido_id', $pedidoId)->orderBy('id')->get() as $row) {
            $pcId = (int) $row->id;
            $clave = (int) $row->numeroitem.'|'.(int) $row->combinacion_id;
            if (isset($idsPresentes[$pcId]) || isset($clavesPresentes[$clave])) {
                continue;
            }

            $existente = DB::table('pedido_combinacion')->where('id', $pcId)->first();
            $usarIdLibre = $existente === null;
            $otId = (int) ($row->ot_id ?? 0);
            $otOmitida = false;
            $otReclamarDePcId = null;
            if ($otId > 0) {
                $dueño = DB::table('pedido_combinacion')
                    ->where('ot_id', $otId)
                    ->where('pedido_id', '!=', $pedidoId)
                    ->first(['id', 'pedido_id']);
                if ($dueño) {
                    // Si en L8 el dueño L12 no tiene esa OT, es asignación errónea → reclamar.
                    $dueñoTieneEnL8 = $l8->table('pedido_combinacion')
                        ->where('pedido_id', (int) $dueño->pedido_id)
                        ->where('ot_id', $otId)
                        ->exists();
                    if ($dueñoTieneEnL8) {
                        $otOmitida = true;
                        $otId = 0;
                    } else {
                        $otReclamarDePcId = (int) $dueño->id;
                    }
                }
            }

            $faltantes[] = [
                'pc_id' => $pcId,
                'numeroitem' => (int) $row->numeroitem,
                'combinacion_id' => (int) $row->combinacion_id,
                'articulo_id' => (int) $row->articulo_id,
                'ot_id' => $otId,
                'usar_id_libre' => $usarIdLibre,
                'ot_omitida' => $otOmitida,
                'ot_reclamar_de_pc_id' => $otReclamarDePcId,
                'row' => (array) $row,
            ];
        }

        return $faltantes;
    }

    /**
     * @param  \Illuminate\Database\ConnectionInterface  $l8
     * @param  array{
     *   pc_id: int,
     *   numeroitem: int,
     *   combinacion_id: int,
     *   articulo_id: int,
     *   ot_id: int,
     *   usar_id_libre: bool,
     *   ot_omitida: bool,
     *   ot_reclamar_de_pc_id: int|null,
     *   row: array<string, mixed>
     * }  $faltante
     * @return array{combinacion: int, talle: int, estado: int, precios: int}
     */
    private function insertarLineaDesdeL8($l8, int $pedidoId, array $faltante): array
    {
        $colsPc = Schema::getColumnListing('pedido_combinacion');
        $row = $faltante['row'];
        $row['pedido_id'] = $pedidoId;
        // ot_id ya viene resuelto en $faltante (0 si L8 lo tiene en otro pedido real).
        $row['ot_id'] = $faltante['ot_id'];

        if (! empty($faltante['ot_reclamar_de_pc_id'])) {
            DB::table('pedido_combinacion')->where('id', (int) $faltante['ot_reclamar_de_pc_id'])->update([
                'ot_id' => 0,
                'updated_at' => now(),
            ]);
        }

        $clean = FerliL8ImportRowSupport::normalize('pedido_combinacion', $row, $colsPc);
        if (! $faltante['usar_id_libre']) {
            unset($clean['id']);
        }

        DB::table('pedido_combinacion')->insert($clean);
        $nuevoPcId = $faltante['usar_id_libre']
            ? (int) $faltante['pc_id']
            : (int) DB::getPdo()->lastInsertId();

        $nTalle = 0;
        $colsTalle = Schema::getColumnListing('pedido_combinacion_talle');
        $tallesL8 = $l8->table('pedido_combinacion_talle')
            ->where('pedido_combinacion_id', $faltante['pc_id'])
            ->get();

        foreach ($tallesL8 as $talle) {
            $tRow = (array) $talle;
            $tRow['pedido_combinacion_id'] = $nuevoPcId;
            $talleIdL8 = (int) ($tRow['id'] ?? 0);
            $idLibre = $talleIdL8 > 0 && ! DB::table('pedido_combinacion_talle')->where('id', $talleIdL8)->exists();
            $cleanT = FerliL8ImportRowSupport::normalize('pedido_combinacion_talle', $tRow, $colsTalle);
            if (! $idLibre) {
                unset($cleanT['id']);
            }
            DB::table('pedido_combinacion_talle')->insert($cleanT);
            $nTalle++;
        }

        $nEstado = 0;
        if (Schema::hasTable('pedido_combinacion_estado')) {
            $colsEst = Schema::getColumnListing('pedido_combinacion_estado');
            $estadosL8 = $l8->table('pedido_combinacion_estado')
                ->where('pedido_combinacion_id', $faltante['pc_id'])
                ->get();
            foreach ($estadosL8 as $est) {
                $eRow = (array) $est;
                $eRow['pedido_combinacion_id'] = $nuevoPcId;
                $estId = (int) ($eRow['id'] ?? 0);
                $idLibre = $estId > 0 && ! DB::table('pedido_combinacion_estado')->where('id', $estId)->exists();
                $cleanE = FerliL8ImportRowSupport::normalize('pedido_combinacion_estado', $eRow, $colsEst);
                if (! $idLibre) {
                    unset($cleanE['id']);
                }
                DB::table('pedido_combinacion_estado')->insert($cleanE);
                $nEstado++;
            }
        }

        $nPrecios = $this->aplicarPreciosSiCorresponde($nuevoPcId, (int) $faltante['articulo_id'], (int) $faltante['combinacion_id']);

        return [
            'combinacion' => 1,
            'talle' => $nTalle,
            'estado' => $nEstado,
            'precios' => $nPrecios,
        ];
    }

    private function aplicarPreciosSiCorresponde(int $pedidoCombinacionId, int $articuloId, int $combinacionId): int
    {
        $pc = DB::table('pedido_combinacion')->where('id', $pedidoCombinacionId)->first();
        if (! $pc) {
            return 0;
        }

        $talles = DB::table('pedido_combinacion_talle')
            ->where('pedido_combinacion_id', $pedidoCombinacionId)
            ->get();
        if ($talles->isEmpty()) {
            return 0;
        }

        $hoy = date('Y-m-d');
        $precioCabecera = 0.0;
        $listaCabecera = (int) ($pc->listaprecio_id ?? 0);
        $incluyeCabecera = null;
        $n = 0;

        foreach ($talles as $talle) {
            if ((float) $talle->precio > 0 && (float) $pc->precio > 0) {
                continue;
            }
            $res = $this->precioService->asignaPrecio($articuloId, $combinacionId, (int) $talle->talle_id, $hoy);
            $precio = (float) ($res[0]['precio'] ?? 0);
            $lista = (int) ($res[0]['listaprecio_id'] ?? 0);
            if ($precio <= 0) {
                continue;
            }
            DB::table('pedido_combinacion_talle')->where('id', $talle->id)->update([
                'precio' => $precio,
                'updated_at' => now(),
            ]);
            if ($precioCabecera <= 0) {
                $precioCabecera = $precio;
                $listaCabecera = $lista > 0 ? $lista : $listaCabecera;
                $incluyeCabecera = $res[0]['incluyeimpuesto'] ?? null;
            }
            $n++;
        }

        if ($precioCabecera > 0 && (float) $pc->precio <= 0) {
            $upd = [
                'precio' => $precioCabecera,
                'updated_at' => now(),
            ];
            if ($listaCabecera > 0) {
                $upd['listaprecio_id'] = $listaCabecera;
            }
            if ($incluyeCabecera !== null && Schema::hasColumn('pedido_combinacion', 'incluyeimpuesto')) {
                $upd['incluyeimpuesto'] = $incluyeCabecera;
            }
            DB::table('pedido_combinacion')->where('id', $pedidoCombinacionId)->update($upd);
        }

        return $n;
    }
}
