<?php

namespace App\Services\Ventas\Ferli;

use App\Support\Ventas\Ferli\FerliL8ReaderSupport;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

/**
 * Completa en L12 lo que falta respecto de L8, sin pisar pedidos divergidos
 * (mismo id, distinto cliente) ni los ~23k precios distintos.
 *
 * - Inserta OT, talles, tareas, movimientos y OCT faltantes.
 * - Liga ot_id en líneas con match lógico.
 * - Alinea cantidades de talle (y quita extras sin stock) en líneas matcheadas.
 * - No reescribe combinaciones/artículos de pedidos con cliente distinto.
 */
class PedidoSincronizarFaltantesDesdeL8Service
{
    public function __construct(
        private readonly FerliL8ReaderSupport $reader,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function sincronizar(bool $dryRun = true): array
    {
        FerliL8ReaderSupport::assertFerli();

        ini_set('max_execution_time', '600');
        ini_set('memory_limit', '1024M');

        $fuente = $this->reader->resolverFuente();
        if ($fuente['fuente'] !== 'mysql_l8' || $fuente['conexion'] === null) {
            throw new RuntimeException('La sincronización de faltantes requiere conexión mysql_l8 directa.');
        }

        /** @var ConnectionInterface $l8 */
        $l8 = $fuente['conexion'];

        $plan = $this->armarPlan($l8);
        $plan['fuente'] = $fuente['fuente'];
        $plan['dry_run'] = $dryRun;

        if ($dryRun) {
            return $plan;
        }

        $fkPrevio = (int) (DB::select('select @@FOREIGN_KEY_CHECKS as v')[0]->v ?? 0);

        DB::beginTransaction();
        try {
            DB::statement('SET FOREIGN_KEY_CHECKS=0');

            $this->aplicarPlan($l8, $plan);

            DB::statement('SET FOREIGN_KEY_CHECKS='.(int) $fkPrevio);
            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            try {
                DB::statement('SET FOREIGN_KEY_CHECKS='.(int) $fkPrevio);
            } catch (\Throwable) {
            }
            Log::error('ferli.l8.sincronizar_faltantes.fallo', ['error' => $e->getMessage()]);
            throw new RuntimeException('Error sincronizando faltantes L8→L12: '.$e->getMessage(), 0, $e);
        }

        $plan['dry_run'] = false;
        try {
            Log::info('ferli.l8.sincronizar_faltantes.ok', [
                'insert_ot' => $plan['insert_ot'],
                'link_ot' => $plan['link_ot'],
                'insert_talle' => $plan['insert_talle'],
                'update_qty' => $plan['update_qty'],
                'delete_talle_extra' => $plan['delete_talle_extra'],
                'insert_tarea' => $plan['insert_tarea'],
                'insert_movimiento' => $plan['insert_movimiento'],
                'insert_oct' => $plan['insert_oct'],
                'delete_oct_huerfano' => $plan['delete_oct_huerfano'],
                'omitidos_divergidos' => $plan['omitidos_divergidos'],
            ]);
        } catch (\Throwable) {
        }

        return $plan;
    }

    /**
     * @return array<string, mixed>
     */
    private function armarPlan(ConnectionInterface $l8): array
    {
        $plan = [
            'insert_ot' => 0,
            'link_ot' => 0,
            'insert_talle' => 0,
            'update_qty' => 0,
            'delete_talle_extra' => 0,
            'update_cantidad_pc' => 0,
            'insert_pc' => 0,
            'insert_tarea' => 0,
            'insert_movimiento' => 0,
            'insert_oct' => 0,
            'delete_oct_huerfano' => 0,
            'omitidos_divergidos' => 0,
            'detalle' => [],
            'errores' => [],
            'acciones' => [
                'ots' => [],
                'links' => [],
                'talles' => [],
                'qty' => [],
                'extras' => [],
                'pcs' => [],
                'tareas' => [],
                'movimientos' => [],
                'octs' => [],
                'oct_huerfanos' => [],
            ],
        ];

        $cli12 = DB::table('pedido')->pluck('cliente_id', 'id')->map(static fn ($v) => (int) $v)->all();
        $cli8 = $l8->table('pedido')->pluck('cliente_id', 'id')->map(static fn ($v) => (int) $v)->all();

        $pc12ById = [];
        $pc12ByLogico = [];
        foreach (DB::table('pedido_combinacion')->get([
            'id', 'pedido_id', 'numeroitem', 'articulo_id', 'combinacion_id', 'ot_id', 'cantidad',
        ]) as $r) {
            $pc12ById[(int) $r->id] = $r;
            $k = $this->claveLogica($r);
            if (! isset($pc12ByLogico[$k])) {
                $pc12ByLogico[$k] = $r;
            }
        }

        $ot12Ids = array_flip(
            DB::table('ordentrabajo')->pluck('id')->map(static fn ($v) => (int) $v)->all()
        );
        $pct12Ids = array_flip(
            DB::table('pedido_combinacion_talle')->pluck('id')->map(static fn ($v) => (int) $v)->all()
        );

        $talles12 = [];
        foreach (DB::table('pedido_combinacion_talle')->get(['id', 'pedido_combinacion_id', 'talle_id', 'cantidad']) as $t) {
            $talles12[(int) $t->pedido_combinacion_id][(int) $t->talle_id] = $t;
        }
        $talles8 = [];
        foreach ($l8->table('pedido_combinacion_talle')->get(['id', 'pedido_combinacion_id', 'talle_id', 'cantidad']) as $t) {
            $talles8[(int) $t->pedido_combinacion_id][(int) $t->talle_id] = $t;
        }

        $pcsConStock = array_flip(
            DB::table('articulo_movimiento')
                ->whereNotNull('pedido_combinacion_id')
                ->where('pedido_combinacion_id', '>', 0)
                ->distinct()
                ->pluck('pedido_combinacion_id')
                ->map(static fn ($v) => (int) $v)
                ->all()
        );

        $otIdsL8PorInsertar = [];
        $otsAfectadas = [];
        $pedidosAfectados = [];
        $mapaPcL8aL12 = [];

        foreach ($l8->table('pedido_combinacion')->orderBy('id')->cursor() as $pc8) {
            $pc8Id = (int) $pc8->id;
            $pedidoId = (int) $pc8->pedido_id;
            $art8 = (int) $pc8->articulo_id;
            $ot8 = (int) ($pc8->ot_id ?? 0);
            $cliL12 = (int) ($cli12[$pedidoId] ?? 0);
            $cliL8 = (int) ($cli8[$pedidoId] ?? 0);

            if ($cliL12 > 0 && $cliL8 > 0 && $cliL12 !== $cliL8) {
                $plan['omitidos_divergidos']++;
                continue;
            }

            $match = null;
            if (isset($pc12ById[$pc8Id]) && (int) $pc12ById[$pc8Id]->pedido_id === $pedidoId) {
                if ((int) $pc12ById[$pc8Id]->articulo_id !== $art8) {
                    $plan['omitidos_divergidos']++;
                    continue;
                }
                $match = $pc12ById[$pc8Id];
            } elseif (isset($pc12ByLogico[$this->claveLogica($pc8)])) {
                $match = $pc12ByLogico[$this->claveLogica($pc8)];
            }

            if ($match === null) {
                if ($cliL12 <= 0) {
                    continue;
                }
                $plan['acciones']['pcs'][] = [
                    'pc8' => $pc8Id,
                    'pedido_id' => $pedidoId,
                    'row' => (array) $pc8,
                ];
                $plan['insert_pc']++;
                $pedidosAfectados[$pedidoId] = true;
                if ($ot8 > 0 && ! isset($ot12Ids[$ot8])) {
                    $otIdsL8PorInsertar[$ot8] = true;
                }
                continue;
            }

            $pc12Id = (int) $match->id;
            $mapaPcL8aL12[$pc8Id] = $pc12Id;

            if ($ot8 > 0 && (int) ($match->ot_id ?? 0) !== $ot8) {
                $dueño = DB::table('pedido_combinacion')
                    ->where('ot_id', $ot8)
                    ->where('id', '!=', $pc12Id)
                    ->first(['id', 'pedido_id']);
                $reclamar = false;
                $omitirOt = false;
                if ($dueño) {
                    $dueñoTieneEnL8 = $l8->table('pedido_combinacion')
                        ->where('pedido_id', (int) $dueño->pedido_id)
                        ->where('ot_id', $ot8)
                        ->exists();
                    if ($dueñoTieneEnL8) {
                        $omitirOt = true;
                    } else {
                        $reclamar = true;
                    }
                }
                if (! $omitirOt) {
                    $plan['acciones']['links'][] = [
                        'pc12' => $pc12Id,
                        'pedido_id' => $pedidoId,
                        'ot8' => $ot8,
                        'ot12' => (int) ($match->ot_id ?? 0),
                        'reclamar_pc' => $reclamar ? (int) $dueño->id : 0,
                    ];
                    $plan['link_ot']++;
                    if (! isset($ot12Ids[$ot8])) {
                        $otIdsL8PorInsertar[$ot8] = true;
                    }
                    $otsAfectadas[$ot8] = true;
                    $pedidosAfectados[$pedidoId] = true;
                }
            }

            $t8 = $talles8[$pc8Id] ?? [];
            $t12 = $talles12[$pc12Id] ?? [];
            $huboTalle = false;
            foreach ($t8 as $talleId => $row8) {
                if (! isset($t12[$talleId])) {
                    $plan['acciones']['talles'][] = [
                        'pc12' => $pc12Id,
                        'pc8' => $pc8Id,
                        'pedido_id' => $pedidoId,
                        'talle_id' => (int) $talleId,
                        'row' => (array) $row8,
                    ];
                    $plan['insert_talle']++;
                    $huboTalle = true;
                } elseif ((float) $t12[$talleId]->cantidad !== (float) $row8->cantidad) {
                    $plan['acciones']['qty'][] = [
                        'pct12' => (int) $t12[$talleId]->id,
                        'pc12' => $pc12Id,
                        'pedido_id' => $pedidoId,
                        'talle_id' => (int) $talleId,
                        'l12' => (float) $t12[$talleId]->cantidad,
                        'l8' => (float) $row8->cantidad,
                    ];
                    $plan['update_qty']++;
                    $huboTalle = true;
                }
            }
            if (! isset($pcsConStock[$pc12Id])) {
                foreach ($t12 as $talleId => $row12) {
                    if (! isset($t8[$talleId])) {
                        $plan['acciones']['extras'][] = [
                            'pct12' => (int) $row12->id,
                            'pc12' => $pc12Id,
                            'pedido_id' => $pedidoId,
                            'talle_id' => (int) $talleId,
                            'cantidad' => (float) $row12->cantidad,
                        ];
                        $plan['delete_talle_extra']++;
                        $huboTalle = true;
                    }
                }
            }
            if ($huboTalle) {
                $pedidosAfectados[$pedidoId] = true;
                if ($ot8 > 0) {
                    $otsAfectadas[$ot8] = true;
                }
            }
        }

        foreach (array_keys($otIdsL8PorInsertar) as $otId) {
            $row = $l8->table('ordentrabajo')->where('id', $otId)->first();
            if (! $row) {
                continue;
            }
            if (! empty($row->deleted_at) && $row->deleted_at !== '0000-00-00 00:00:00') {
                $plan['detalle'][] = "OT {$otId} omitida: borrada en L8.";
                continue;
            }
            $plan['acciones']['ots'][] = (array) $row;
            $plan['insert_ot']++;
            $otsAfectadas[$otId] = true;
        }

        $tarea12PorClave = [];
        $tarea12Ids = [];
        foreach (DB::table('ordentrabajo_tarea')->get(['id', 'ordentrabajo_id', 'tarea_id', 'pedido_combinacion_id']) as $r) {
            $pc = max(0, (int) ($r->pedido_combinacion_id ?? 0));
            $tarea12PorClave[(int) $r->ordentrabajo_id.'|'.(int) $r->tarea_id.'|'.$pc] = (int) $r->id;
            $tarea12Ids[(int) $r->id] = true;
        }
        foreach ($l8->table('ordentrabajo_tarea')->cursor() as $r) {
            $otId = (int) $r->ordentrabajo_id;
            if (! isset($ot12Ids[$otId]) && ! isset($otIdsL8PorInsertar[$otId])) {
                continue;
            }
            $pc = max(0, (int) ($r->pedido_combinacion_id ?? 0));
            $clave = $otId.'|'.(int) $r->tarea_id.'|'.$pc;
            if (isset($tarea12PorClave[$clave])) {
                continue;
            }
            $plan['acciones']['tareas'][] = (array) $r;
            $plan['insert_tarea']++;
            $otsAfectadas[$otId] = true;
        }

        $mov12Ids = array_flip(
            DB::table('movimientoordentrabajo')->pluck('id')->map(static fn ($v) => (int) $v)->all()
        );
        $tareasNuevasIds = [];
        foreach ($plan['acciones']['tareas'] as $t) {
            $tid = (int) ($t['id'] ?? 0);
            if ($tid > 0) {
                $tareasNuevasIds[$tid] = true;
            }
        }
        foreach ($l8->table('movimientoordentrabajo')->cursor() as $r) {
            $id = (int) $r->id;
            if (isset($mov12Ids[$id])) {
                continue;
            }
            $otId = (int) $r->ordentrabajo_id;
            if (! isset($ot12Ids[$otId]) && ! isset($otIdsL8PorInsertar[$otId])) {
                continue;
            }
            $ottId = (int) ($r->ordentrabajo_tarea_id ?? 0);
            if ($ottId > 0 && ! isset($tarea12Ids[$ottId]) && ! isset($tareasNuevasIds[$ottId])) {
                continue;
            }
            $plan['acciones']['movimientos'][] = (array) $r;
            $plan['insert_movimiento']++;
        }

        $pctIdsNuevosL8 = [];
        foreach ($plan['acciones']['talles'] as $t) {
            $id8 = (int) ($t['row']['id'] ?? 0);
            if ($id8 > 0 && ! isset($pct12Ids[$id8])) {
                $pctIdsNuevosL8[$id8] = true;
            }
        }

        $mapaPctL8aL12 = [];
        foreach ($talles8 as $pc8Id => $byTalle) {
            $pc12Id = $mapaPcL8aL12[$pc8Id] ?? 0;
            if ($pc12Id <= 0) {
                continue;
            }
            foreach ($byTalle as $talleId => $row8) {
                $id8 = (int) $row8->id;
                if (isset($pct12Ids[$id8]) || isset($pctIdsNuevosL8[$id8])) {
                    $mapaPctL8aL12[$id8] = $id8;
                    continue;
                }
                $t12 = $talles12[$pc12Id][$talleId] ?? null;
                if ($t12) {
                    $mapaPctL8aL12[$id8] = (int) $t12->id;
                }
            }
        }

        $oct12Par = [];
        foreach (DB::table('ordentrabajo_combinacion_talle')->get(['id', 'ordentrabajo_id', 'pedido_combinacion_talle_id']) as $r) {
            $pctId = (int) $r->pedido_combinacion_talle_id;
            $oct12Par[(int) $r->ordentrabajo_id.'|'.$pctId] = (int) $r->id;
            if (! isset($pct12Ids[$pctId]) && ! isset($pctIdsNuevosL8[$pctId])) {
                $plan['acciones']['oct_huerfanos'][] = (int) $r->id;
            }
        }
        $plan['delete_oct_huerfano'] = count($plan['acciones']['oct_huerfanos']);

        if ($otsAfectadas !== []) {
            $octsL8 = $l8->table('ordentrabajo_combinacion_talle')
                ->whereIn('ordentrabajo_id', array_keys($otsAfectadas))
                ->get();
            foreach ($octsL8 as $oct8) {
                $otId = (int) $oct8->ordentrabajo_id;
                $pct8 = (int) $oct8->pedido_combinacion_talle_id;
                $pct12 = $mapaPctL8aL12[$pct8] ?? 0;
                if ($pct12 <= 0) {
                    continue;
                }
                $par = $otId.'|'.$pct12;
                if (isset($oct12Par[$par])) {
                    continue;
                }
                $row = (array) $oct8;
                $row['pedido_combinacion_talle_id'] = $pct12;
                $plan['acciones']['octs'][] = $row;
                $plan['insert_oct']++;
                $oct12Par[$par] = true;
            }
        }

        $plan['detalle'][] = sprintf(
            'OT +%d · ot_id ligar %d · PC nuevas %d · talles +%d · qty %d · extras a quitar %d · tareas +%d · mov +%d · OCT +%d · OCT huérfanos %d · líneas L8 omitidas (pedido divergido) %d.',
            $plan['insert_ot'],
            $plan['link_ot'],
            $plan['insert_pc'],
            $plan['insert_talle'],
            $plan['update_qty'],
            $plan['delete_talle_extra'],
            $plan['insert_tarea'],
            $plan['insert_movimiento'],
            $plan['insert_oct'],
            $plan['delete_oct_huerfano'],
            $plan['omitidos_divergidos']
        );

        foreach (array_slice($plan['acciones']['talles'], 0, 20) as $t) {
            $plan['detalle'][] = sprintf(
                '  talle faltante pedido %d pc L12 %d talle_id %d cant %s (pct L8 %s)',
                $t['pedido_id'],
                $t['pc12'],
                $t['talle_id'],
                $t['row']['cantidad'] ?? '?',
                $t['row']['id'] ?? '?'
            );
        }
        foreach (array_slice($plan['acciones']['qty'], 0, 15) as $q) {
            $plan['detalle'][] = sprintf(
                '  qty pedido %d pc %d talle %d L12=%s → L8=%s',
                $q['pedido_id'],
                $q['pc12'],
                $q['talle_id'],
                $q['l12'],
                $q['l8']
            );
        }
        foreach (array_slice($plan['acciones']['extras'], 0, 10) as $e) {
            $plan['detalle'][] = sprintf(
                '  extra L12 pedido %d pc %d talle %d cant %s (pct %d)',
                $e['pedido_id'],
                $e['pc12'],
                $e['talle_id'],
                $e['cantidad'],
                $e['pct12']
            );
        }
        foreach (array_slice($plan['acciones']['links'], 0, 15) as $lk) {
            $plan['detalle'][] = sprintf(
                '  ligar ot_id %d en pc L12 %d (pedido %d)%s',
                $lk['ot8'],
                $lk['pc12'],
                $lk['pedido_id'],
                $lk['reclamar_pc'] ? ' [reclama pc '.$lk['reclamar_pc'].']' : ''
            );
        }
        if ($plan['omitidos_divergidos'] > 0) {
            $plan['detalle'][] = 'Omitidas líneas L8 de pedidos con distinto cliente (p.ej. 5091/5099/5101): no se mezclan con el pedido L12.';
        }

        return $plan;
    }

    /**
     * @param  array<string, mixed>  $plan
     */
    private function aplicarPlan(ConnectionInterface $l8, array &$plan): void
    {
        $colsOt = Schema::getColumnListing('ordentrabajo');
        foreach ($plan['acciones']['ots'] as $row) {
            $clean = FerliL8ImportRowSupport::normalize('ordentrabajo', $row, $colsOt);
            $id = (int) ($clean['id'] ?? 0);
            if ($id <= 0 || DB::table('ordentrabajo')->where('id', $id)->exists()) {
                continue;
            }
            DB::table('ordentrabajo')->insert($clean);
        }

        foreach ($plan['acciones']['links'] as $lk) {
            if ($lk['reclamar_pc'] > 0) {
                DB::table('pedido_combinacion')->where('id', $lk['reclamar_pc'])->update([
                    'ot_id' => 0,
                    'updated_at' => now(),
                ]);
            }
            DB::table('pedido_combinacion')->where('id', $lk['pc12'])->update([
                'ot_id' => $lk['ot8'],
                'updated_at' => now(),
            ]);
        }

        $colsPc = Schema::getColumnListing('pedido_combinacion');
        $colsTalle = Schema::getColumnListing('pedido_combinacion_talle');
        $pcsRecalc = [];

        foreach ($plan['acciones']['pcs'] as $f) {
            $row = $f['row'];
            $clean = FerliL8ImportRowSupport::normalize('pedido_combinacion', $row, $colsPc);
            $id8 = (int) ($clean['id'] ?? 0);
            if ($id8 > 0 && DB::table('pedido_combinacion')->where('id', $id8)->exists()) {
                unset($clean['id']);
            }
            DB::table('pedido_combinacion')->insert($clean);
            $nuevoId = isset($clean['id']) ? (int) $clean['id'] : (int) DB::getPdo()->lastInsertId();
            foreach ($l8->table('pedido_combinacion_talle')->where('pedido_combinacion_id', $f['pc8'])->get() as $talle) {
                $tRow = (array) $talle;
                $tRow['pedido_combinacion_id'] = $nuevoId;
                $cleanT = FerliL8ImportRowSupport::normalize('pedido_combinacion_talle', $tRow, $colsTalle);
                $tid = (int) ($cleanT['id'] ?? 0);
                if ($tid > 0 && DB::table('pedido_combinacion_talle')->where('id', $tid)->exists()) {
                    unset($cleanT['id']);
                }
                DB::table('pedido_combinacion_talle')->insert($cleanT);
            }
            $pcsRecalc[$nuevoId] = true;
        }

        foreach ($plan['acciones']['talles'] as $t) {
            $row = $t['row'];
            $row['pedido_combinacion_id'] = $t['pc12'];
            $cleanT = FerliL8ImportRowSupport::normalize('pedido_combinacion_talle', $row, $colsTalle);
            $tid = (int) ($cleanT['id'] ?? 0);
            if ($tid > 0 && DB::table('pedido_combinacion_talle')->where('id', $tid)->exists()) {
                unset($cleanT['id']);
            }
            $ya = DB::table('pedido_combinacion_talle')
                ->where('pedido_combinacion_id', $t['pc12'])
                ->where('talle_id', $t['talle_id'])
                ->exists();
            if ($ya) {
                continue;
            }
            DB::table('pedido_combinacion_talle')->insert($cleanT);
            $pcsRecalc[$t['pc12']] = true;
        }

        foreach ($plan['acciones']['qty'] as $q) {
            DB::table('pedido_combinacion_talle')->where('id', $q['pct12'])->update([
                'cantidad' => $q['l8'],
                'updated_at' => now(),
            ]);
            $pcsRecalc[$q['pc12']] = true;
        }

        foreach ($plan['acciones']['extras'] as $e) {
            DB::table('ordentrabajo_combinacion_talle')
                ->where('pedido_combinacion_talle_id', $e['pct12'])
                ->delete();
            DB::table('pedido_combinacion_talle')->where('id', $e['pct12'])->delete();
            $pcsRecalc[$e['pc12']] = true;
        }

        foreach (array_keys($pcsRecalc) as $pcId) {
            $suma = (float) DB::table('pedido_combinacion_talle')
                ->where('pedido_combinacion_id', $pcId)
                ->sum('cantidad');
            DB::table('pedido_combinacion')->where('id', $pcId)->update([
                'cantidad' => $suma,
                'updated_at' => now(),
            ]);
            $plan['update_cantidad_pc']++;
        }

        $colsTarea = Schema::getColumnListing('ordentrabajo_tarea');
        foreach ($plan['acciones']['tareas'] as $row) {
            $clean = FerliL8ImportRowSupport::normalize('ordentrabajo_tarea', $row, $colsTarea);
            $otId = (int) ($clean['ordentrabajo_id'] ?? 0);
            $tareaId = (int) ($clean['tarea_id'] ?? 0);
            if ($otId <= 0 || $tareaId <= 0) {
                continue;
            }
            $pcId = max(0, (int) ($clean['pedido_combinacion_id'] ?? 0));
            $qExiste = DB::table('ordentrabajo_tarea')
                ->where('ordentrabajo_id', $otId)
                ->where('tarea_id', $tareaId);
            if ($pcId > 0) {
                $qExiste->where('pedido_combinacion_id', $pcId);
            } else {
                $qExiste->where(function ($w) {
                    $w->whereNull('pedido_combinacion_id')
                        ->orWhere('pedido_combinacion_id', 0);
                });
            }
            if ($qExiste->exists()) {
                continue;
            }
            $id = (int) ($clean['id'] ?? 0);
            if ($id > 0 && DB::table('ordentrabajo_tarea')->where('id', $id)->exists()) {
                unset($clean['id']);
            }
            if (! isset($clean['created_at']) && in_array('created_at', $colsTarea, true)) {
                $clean['created_at'] = now();
            }
            if (in_array('updated_at', $colsTarea, true)) {
                $clean['updated_at'] = now();
            }
            DB::table('ordentrabajo_tarea')->insert($clean);
        }

        $colsMov = Schema::getColumnListing('movimientoordentrabajo');
        foreach ($plan['acciones']['movimientos'] as $row) {
            $clean = FerliL8ImportRowSupport::normalize('movimientoordentrabajo', $row, $colsMov);
            $id = (int) ($clean['id'] ?? 0);
            if ($id <= 0 || DB::table('movimientoordentrabajo')->where('id', $id)->exists()) {
                continue;
            }
            $ottId = (int) ($clean['ordentrabajo_tarea_id'] ?? 0);
            if ($ottId > 0 && ! DB::table('ordentrabajo_tarea')->where('id', $ottId)->exists()) {
                continue;
            }
            DB::table('movimientoordentrabajo')->insert($clean);
        }

        if ($plan['acciones']['oct_huerfanos'] !== []) {
            foreach (array_chunk($plan['acciones']['oct_huerfanos'], 500) as $chunk) {
                $vivos = DB::table('ordentrabajo_combinacion_talle as oct')
                    ->join('pedido_combinacion_talle as pct', 'pct.id', '=', 'oct.pedido_combinacion_talle_id')
                    ->whereIn('oct.id', $chunk)
                    ->pluck('oct.id')
                    ->map(static fn ($v) => (int) $v)
                    ->all();
                $borrar = array_values(array_diff($chunk, $vivos));
                if ($borrar !== []) {
                    DB::table('ordentrabajo_combinacion_talle')->whereIn('id', $borrar)->delete();
                }
            }
        }

        $colsOct = Schema::getColumnListing('ordentrabajo_combinacion_talle');
        foreach ($plan['acciones']['octs'] as $row) {
            $clean = FerliL8ImportRowSupport::normalize('ordentrabajo_combinacion_talle', $row, $colsOct);
            $otId = (int) ($clean['ordentrabajo_id'] ?? 0);
            $pctId = (int) ($clean['pedido_combinacion_talle_id'] ?? 0);
            if ($otId <= 0 || $pctId <= 0) {
                continue;
            }
            if (! DB::table('pedido_combinacion_talle')->where('id', $pctId)->exists()) {
                continue;
            }
            if (DB::table('ordentrabajo_combinacion_talle')
                ->where('ordentrabajo_id', $otId)
                ->where('pedido_combinacion_talle_id', $pctId)
                ->exists()) {
                continue;
            }
            $id = (int) ($clean['id'] ?? 0);
            if ($id > 0 && DB::table('ordentrabajo_combinacion_talle')->where('id', $id)->exists()) {
                unset($clean['id']);
            }
            DB::table('ordentrabajo_combinacion_talle')->insert($clean);
        }
    }

    private function claveLogica(object $r): string
    {
        return (int) $r->pedido_id.'|'.(int) $r->numeroitem.'|'.(int) $r->articulo_id.'|'.(int) $r->combinacion_id;
    }
}
