<?php

namespace App\Services\Ventas\Ferli;

use App\Support\Ventas\Ferli\FerliL8ReaderSupport;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

/**
 * Trae un pedido puntual de L8 a L12 con código e IDs nuevos
 * (cuando el código/id de L8 ya están ocupados por otro cliente en L12).
 */
class PedidoImportarUnoRenumeradoDesdeL8Service
{
    public function __construct(
        private readonly FerliL8ReaderSupport $reader,
    ) {
    }

    /**
     * @return array{
     *   dry_run: bool,
     *   fuente: string,
     *   l8_id: int,
     *   l8_codigo: string,
     *   l12_codigo: string,
     *   l12_pedido_id: int|null,
     *   cliente: string,
     *   insert_pedido: int,
     *   insert_combinacion: int,
     *   insert_talle: int,
     *   insert_ordentrabajo: int,
     *   insert_oct: int,
     *   insert_tarea: int,
     *   ot_map: list<string>,
     *   detalle: list<string>,
     *   ocupante_movido: array{pedido_id: int, codigo_antes: string, codigo_despues: string, cliente: string}|null,
     *   ot_reutilizadas: list<int>
     * }
     */
    public function importar(
        string $codigoL8,
        ?string $codigoNuevo = null,
        bool $dryRun = true,
        bool $moverOcupante = false,
        ?string $codigoOcupanteDestino = null,
        bool $conservarOtL8 = false,
    ): array {
        FerliL8ReaderSupport::assertFerli();

        $fuente = $this->reader->resolverFuente();
        if ($fuente['fuente'] !== 'mysql_l8' || $fuente['conexion'] === null) {
            throw new RuntimeException('Se requiere conexión mysql_l8 directa.');
        }

        /** @var \Illuminate\Database\ConnectionInterface $l8 */
        $l8 = $fuente['conexion'];

        $codigoL8 = trim($codigoL8);
        if ($codigoL8 === '') {
            throw new RuntimeException('Indique el código del pedido en L8.');
        }

        $pedidoL8 = $l8->table('pedido')->where('codigo', $codigoL8)->first();
        if (! $pedidoL8) {
            throw new RuntimeException("No existe el pedido código {$codigoL8} en L8.");
        }

        $cliente = $l8->table('cliente')->where('id', $pedidoL8->cliente_id)->first();
        $clienteNombre = $cliente
            ? trim((string) ($cliente->codigo ?? '')).' — '.trim((string) ($cliente->nombre ?? ''))
            : 'cliente_id='.$pedidoL8->cliente_id;

        if (! DB::table('cliente')->where('id', $pedidoL8->cliente_id)->exists()) {
            throw new RuntimeException("El cliente L8 id={$pedidoL8->cliente_id} no existe en L12.");
        }

        $codigoDestino = $codigoNuevo !== null && trim($codigoNuevo) !== ''
            ? trim($codigoNuevo)
            : (string) (((int) DB::table('pedido')->selectRaw('MAX(CAST(codigo AS UNSIGNED)) m')->value('m')) + 1);

        $ocupante = DB::table('pedido as p')
            ->leftJoin('cliente as c', 'c.id', '=', 'p.cliente_id')
            ->where('p.codigo', $codigoDestino)
            ->first(['p.id', 'p.codigo', 'p.cliente_id', 'c.codigo as cli_cod', 'c.nombre as cli_nom', 'p.estadopedido']);

        $ocupanteMovido = null;
        $codigoOcupanteFinal = null;

        if ($ocupante) {
            if (! $moverOcupante) {
                throw new RuntimeException(
                    "El código destino {$codigoDestino} ya existe en L12"
                    .' (id='.$ocupante->id.', '.trim((string) ($ocupante->cli_cod ?? '')).' — '.trim((string) ($ocupante->cli_nom ?? '')).').'
                    .' Use --mover-ocupante para renumerarlo y liberar el número.'
                );
            }

            $codigoOcupanteFinal = $codigoOcupanteDestino !== null && trim($codigoOcupanteDestino) !== ''
                ? trim($codigoOcupanteDestino)
                : (string) (((int) DB::table('pedido')->selectRaw('MAX(CAST(codigo AS UNSIGNED)) m')->value('m')) + 1);

            if ($codigoOcupanteFinal === $codigoDestino) {
                throw new RuntimeException('El código destino del ocupante no puede ser el mismo que se libera.');
            }
            if (DB::table('pedido')->where('codigo', $codigoOcupanteFinal)->exists()) {
                throw new RuntimeException("El código destino del ocupante {$codigoOcupanteFinal} ya existe en L12.");
            }

            $ocupanteMovido = [
                'pedido_id' => (int) $ocupante->id,
                'codigo_antes' => (string) $ocupante->codigo,
                'codigo_despues' => $codigoOcupanteFinal,
                'cliente' => trim((string) ($ocupante->cli_cod ?? '')).' — '.trim((string) ($ocupante->cli_nom ?? '')),
            ];
        } elseif ($moverOcupante) {
            // Nada que mover; el destino está libre.
        }

        $combinaciones = $l8->table('pedido_combinacion')
            ->where('pedido_id', $pedidoL8->id)
            ->orderBy('id')
            ->get();
        if ($combinaciones->isEmpty()) {
            throw new RuntimeException("El pedido L8 {$codigoL8} no tiene líneas.");
        }

        $pcIds = $combinaciones->pluck('id')->map(static fn ($id) => (int) $id)->all();
        $talles = $l8->table('pedido_combinacion_talle')
            ->whereIn('pedido_combinacion_id', $pcIds)
            ->orderBy('id')
            ->get();

        $otCodigos = $combinaciones->pluck('ot_id')
            ->map(static fn ($v) => (int) $v)
            ->filter(static fn ($v) => $v > 0)
            ->unique()
            ->values()
            ->all();

        $ots = $otCodigos === []
            ? collect()
            : $l8->table('ordentrabajo')->whereIn('codigo', $otCodigos)->orderBy('id')->get();

        $otIdsL8 = $ots->pluck('id')->map(static fn ($id) => (int) $id)->all();
        $tareas = $otIdsL8 === []
            ? collect()
            : $l8->table('ordentrabajo_tarea')->whereIn('ordentrabajo_id', $otIdsL8)->orderBy('id')->get();
        $octs = $otIdsL8 === []
            ? collect()
            : $l8->table('ordentrabajo_combinacion_talle')->whereIn('ordentrabajo_id', $otIdsL8)->orderBy('id')->get();

        $otMapL8aL12 = [];
        $otMapDetalle = [];
        $otReutilizadas = [];
        $maxOtCodigo = (int) DB::table('ordentrabajo')->selectRaw('MAX(CAST(codigo AS UNSIGNED)) m')->value('m');
        $siguienteOt = $maxOtCodigo;

        foreach ($ots as $ot) {
            $codigoOt = (string) $ot->codigo;
            $otIdL8 = (int) $ot->id;
            $existente = DB::table('ordentrabajo')
                ->where(static function ($q) use ($codigoOt): void {
                    $q->where('codigo', $codigoOt)->orWhere('id', (int) $codigoOt);
                })
                ->first();

            if ($conservarOtL8 && $existente && $ocupante) {
                // Reusa la OT de producción (etiqueta) tras desvincularla del ocupante.
                $otL12Id = (int) $existente->id;
                $otMapL8aL12[$otIdL8] = $otL12Id;
                $otMapL8aL12[(int) $codigoOt] = $otL12Id;
                $otReutilizadas[] = $otL12Id;
                $otMapDetalle[] = sprintf('OT L8 %s → L12 %d (REUTILIZA, conserva etiqueta)', $codigoOt, $otL12Id);
                continue;
            }

            $siguienteOt++;
            while (
                DB::table('ordentrabajo')->where('codigo', (string) $siguienteOt)->exists()
                || DB::table('ordentrabajo')->where('id', $siguienteOt)->exists()
                || in_array($siguienteOt, $otMapL8aL12, true)
            ) {
                $siguienteOt++;
            }
            $otMapL8aL12[$otIdL8] = $siguienteOt;
            $otMapL8aL12[(int) $codigoOt] = $siguienteOt;
            $otMapDetalle[] = sprintf('OT L8 %s → L12 %d', $codigoOt, $siguienteOt);
        }

        $pcsOcupanteAQuitar = [];
        if ($ocupante && $otReutilizadas !== []) {
            $pcsOcupanteAQuitar = DB::table('pedido_combinacion')
                ->where('pedido_id', (int) $ocupante->id)
                ->whereIn('ot_id', $otReutilizadas)
                ->orderBy('id')
                ->get(['id', 'ot_id'])
                ->all();
        }

        $stats = [
            'dry_run' => $dryRun,
            'fuente' => $fuente['fuente'],
            'l8_id' => (int) $pedidoL8->id,
            'l8_codigo' => (string) $pedidoL8->codigo,
            'l12_codigo' => $codigoDestino,
            'l12_pedido_id' => null,
            'cliente' => $clienteNombre,
            'insert_pedido' => 1,
            'insert_combinacion' => $combinaciones->count(),
            'insert_talle' => $talles->count(),
            'insert_ordentrabajo' => $ots->count() - count($otReutilizadas),
            'insert_oct' => $octs->count(),
            'insert_tarea' => 0, // se calcula abajo
            'ot_map' => $otMapDetalle,
            'detalle' => [],
            'ocupante_movido' => $ocupanteMovido,
            'ot_reutilizadas' => $otReutilizadas,
        ];

        if ($ocupanteMovido) {
            $stats['detalle'][] = sprintf(
                'MOVER ocupante L12 id=%d codigo %s (%s, %s) → codigo %s',
                $ocupanteMovido['pedido_id'],
                $ocupanteMovido['codigo_antes'],
                $ocupanteMovido['cliente'],
                $ocupante->estadopedido ?? '?',
                $ocupanteMovido['codigo_despues']
            );
        }

        $stats['detalle'][] = sprintf(
            'L8 pedido id=%d codigo=%s (%s, %s) → L12 codigo=%s',
            $pedidoL8->id,
            $pedidoL8->codigo,
            $clienteNombre,
            $pedidoL8->estadopedido ?? '?',
            $codigoDestino
        );
        $stats['detalle'][] = sprintf(
            'Líneas L8: %d comb / %d talles / %d OT / %d tareas / %d OCT',
            $combinaciones->count(),
            $talles->count(),
            $ots->count(),
            $tareas->count(),
            $octs->count()
        );
        foreach ($otMapDetalle as $line) {
            $stats['detalle'][] = $line;
        }

        if ($pcsOcupanteAQuitar !== []) {
            $stats['detalle'][] = sprintf(
                'Del ocupante se desvinculan %d línea(s) con OT a reutilizar (pasan a Boston): pc %s',
                count($pcsOcupanteAQuitar),
                implode(', ', array_map(static fn ($r) => (string) $r->id, $pcsOcupanteAQuitar))
            );
        }

        $tareasNuevas = 0;
        foreach ($tareas as $tarea) {
            $otL12 = $otMapL8aL12[(int) $tarea->ordentrabajo_id] ?? null;
            if ($otL12 === null) {
                continue;
            }
            $ya = DB::table('ordentrabajo_tarea')
                ->where('ordentrabajo_id', $otL12)
                ->where('tarea_id', (int) $tarea->tarea_id)
                ->exists();
            if (! $ya) {
                $tareasNuevas++;
            }
        }
        $stats['insert_tarea'] = $tareasNuevas;
        $stats['detalle'][] = sprintf(
            'Insertarán: pedido +%d · comb +%d · talles +%d · OT nuevas +%d · tareas nuevas +%d · OCT +%d',
            $stats['insert_pedido'],
            $stats['insert_combinacion'],
            $stats['insert_talle'],
            $stats['insert_ordentrabajo'],
            $stats['insert_tarea'],
            $stats['insert_oct']
        );

        if ($dryRun) {
            return $stats;
        }

        $colsPedido = Schema::getColumnListing('pedido');
        $colsPc = Schema::getColumnListing('pedido_combinacion');
        $colsPct = Schema::getColumnListing('pedido_combinacion_talle');
        $colsOt = Schema::getColumnListing('ordentrabajo');
        $colsOtt = Schema::getColumnListing('ordentrabajo_tarea');
        $colsOct = Schema::getColumnListing('ordentrabajo_combinacion_talle');

        DB::beginTransaction();
        try {
            if ($ocupanteMovido) {
                DB::table('pedido')
                    ->where('id', $ocupanteMovido['pedido_id'])
                    ->update([
                        'codigo' => $ocupanteMovido['codigo_despues'],
                        'updated_at' => now(),
                    ]);
            }

            // Desvincular del ocupante las líneas cuya OT se reutiliza (Lilly/Boston).
            if ($pcsOcupanteAQuitar !== []) {
                $pcIdsQuitar = array_map(static fn ($r) => (int) $r->id, $pcsOcupanteAQuitar);
                $pctIds = DB::table('pedido_combinacion_talle')
                    ->whereIn('pedido_combinacion_id', $pcIdsQuitar)
                    ->pluck('id')
                    ->map(static fn ($id) => (int) $id)
                    ->all();

                if ($pctIds !== []) {
                    DB::table('ordentrabajo_combinacion_talle')
                        ->whereIn('pedido_combinacion_talle_id', $pctIds)
                        ->delete();
                }

                DB::table('ordentrabajo_tarea')
                    ->whereIn('pedido_combinacion_id', $pcIdsQuitar)
                    ->update(['pedido_combinacion_id' => null]);

                if ($pctIds !== []) {
                    DB::table('pedido_combinacion_talle')->whereIn('id', $pctIds)->delete();
                }
                DB::table('pedido_combinacion')->whereIn('id', $pcIdsQuitar)->delete();
            }

            $pedidoRow = FerliL8ImportRowSupport::normalize('pedido', (array) $pedidoL8, $colsPedido);
            unset($pedidoRow['id']);
            $pedidoRow['codigo'] = $codigoDestino;
            $pedidoL12Id = (int) DB::table('pedido')->insertGetId($pedidoRow);
            $stats['l12_pedido_id'] = $pedidoL12Id;

            // OTs nuevas (las reutilizadas ya existen).
            foreach ($ots as $ot) {
                $nuevo = $otMapL8aL12[(int) $ot->id];
                if (in_array($nuevo, $otReutilizadas, true)) {
                    continue;
                }
                $otRow = FerliL8ImportRowSupport::normalize('ordentrabajo', (array) $ot, $colsOt);
                $otRow['id'] = $nuevo;
                $otRow['codigo'] = (string) $nuevo;
                DB::table('ordentrabajo')->insert($otRow);
            }

            $mapaPc = [];
            foreach ($combinaciones as $pc) {
                $pcRow = FerliL8ImportRowSupport::normalize('pedido_combinacion', (array) $pc, $colsPc);
                $oldPcId = (int) $pcRow['id'];
                unset($pcRow['id']);
                $pcRow['pedido_id'] = $pedidoL12Id;
                $otL8 = (int) ($pcRow['ot_id'] ?? 0);
                if ($otL8 > 0) {
                    if (! isset($otMapL8aL12[$otL8])) {
                        throw new RuntimeException("Sin mapa OT para ot_id L8={$otL8}.");
                    }
                    $pcRow['ot_id'] = $otMapL8aL12[$otL8];
                }
                $mapaPc[$oldPcId] = (int) DB::table('pedido_combinacion')->insertGetId($pcRow);
            }

            $mapaPct = [];
            foreach ($talles as $talle) {
                $pctRow = FerliL8ImportRowSupport::normalize('pedido_combinacion_talle', (array) $talle, $colsPct);
                $oldPctId = (int) $pctRow['id'];
                $oldPcId = (int) $pctRow['pedido_combinacion_id'];
                if (! isset($mapaPc[$oldPcId])) {
                    throw new RuntimeException("Sin mapa PC para talle L8 pct={$oldPctId} pc={$oldPcId}.");
                }
                unset($pctRow['id']);
                $pctRow['pedido_combinacion_id'] = $mapaPc[$oldPcId];
                $mapaPct[$oldPctId] = (int) DB::table('pedido_combinacion_talle')->insertGetId($pctRow);
            }

            foreach ($tareas as $tarea) {
                $ottRow = FerliL8ImportRowSupport::normalize('ordentrabajo_tarea', (array) $tarea, $colsOtt);
                $otL8 = (int) $ottRow['ordentrabajo_id'];
                if (! isset($otMapL8aL12[$otL8])) {
                    throw new RuntimeException("Sin mapa OT para tarea ordentrabajo_id={$otL8}.");
                }
                $otL12 = $otMapL8aL12[$otL8];
                $pcL8 = (int) ($ottRow['pedido_combinacion_id'] ?? 0);
                $pcL12 = null;
                if ($pcL8 > 0) {
                    if (! isset($mapaPc[$pcL8])) {
                        throw new RuntimeException("Sin mapa PC para tarea pc={$pcL8}.");
                    }
                    $pcL12 = $mapaPc[$pcL8];
                }

                $existenteTarea = DB::table('ordentrabajo_tarea')
                    ->where('ordentrabajo_id', $otL12)
                    ->where('tarea_id', (int) $ottRow['tarea_id'])
                    ->orderBy('id')
                    ->first();

                if ($existenteTarea) {
                    $upd = ['updated_at' => now()];
                    if ($pcL12 !== null) {
                        $upd['pedido_combinacion_id'] = $pcL12;
                    }
                    DB::table('ordentrabajo_tarea')->where('id', $existenteTarea->id)->update($upd);
                    continue;
                }

                unset($ottRow['id']);
                $ottRow['ordentrabajo_id'] = $otL12;
                if ($pcL12 !== null) {
                    $ottRow['pedido_combinacion_id'] = $pcL12;
                }
                DB::table('ordentrabajo_tarea')->insert($ottRow);
            }

            foreach ($octs as $oct) {
                $octRow = FerliL8ImportRowSupport::normalize('ordentrabajo_combinacion_talle', (array) $oct, $colsOct);
                unset($octRow['id']);
                $otL8 = (int) $octRow['ordentrabajo_id'];
                if (! isset($otMapL8aL12[$otL8])) {
                    throw new RuntimeException("Sin mapa OT para OCT ordentrabajo_id={$otL8}.");
                }
                $octRow['ordentrabajo_id'] = $otMapL8aL12[$otL8];
                $pctL8 = (int) $octRow['pedido_combinacion_talle_id'];
                if (! isset($mapaPct[$pctL8])) {
                    throw new RuntimeException("Sin mapa PCT para OCT pct={$pctL8}.");
                }
                $octRow['pedido_combinacion_talle_id'] = $mapaPct[$pctL8];
                $octRow['ordentrabajo_stock_id'] = null;
                DB::table('ordentrabajo_combinacion_talle')->insert($octRow);
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('ferli.l8.importar_uno_renumerado.fallo', [
                'l8_codigo' => $codigoL8,
                'error' => $e->getMessage(),
            ]);
            throw new RuntimeException('Error importando pedido renumerado: '.$e->getMessage(), 0, $e);
        }

        $stats['detalle'][] = sprintf(
            'Creado L12 pedido id=%d codigo=%s.',
            $stats['l12_pedido_id'],
            $codigoDestino
        );
        try {
            Log::info('ferli.l8.importar_uno_renumerado.ok', $stats);
        } catch (\Throwable) {
            // Log opcional: no tumba el import ya commitado.
        }

        return $stats;
    }
}
