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
     *   detalle: list<string>
     * }
     */
    public function importar(string $codigoL8, ?string $codigoNuevo = null, bool $dryRun = true): array
    {
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

        if (DB::table('pedido')->where('codigo', $codigoDestino)->exists()) {
            throw new RuntimeException("El código destino {$codigoDestino} ya existe en L12.");
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

        $maxOtCodigo = (int) DB::table('ordentrabajo')->selectRaw('MAX(CAST(codigo AS UNSIGNED)) m')->value('m');
        $otMapL8aL12 = [];
        $otMapDetalle = [];
        $siguienteOt = $maxOtCodigo;
        foreach ($ots as $ot) {
            $siguienteOt++;
            while (
                DB::table('ordentrabajo')->where('codigo', (string) $siguienteOt)->exists()
                || DB::table('ordentrabajo')->where('id', $siguienteOt)->exists()
            ) {
                $siguienteOt++;
            }
            $otMapL8aL12[(int) $ot->codigo] = $siguienteOt;
            $otMapL8aL12[(int) $ot->id] = $siguienteOt;
            $otMapDetalle[] = sprintf('OT L8 %s → L12 %d', $ot->codigo, $siguienteOt);
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
            'insert_ordentrabajo' => $ots->count(),
            'insert_oct' => $octs->count(),
            'insert_tarea' => $tareas->count(),
            'ot_map' => $otMapDetalle,
            'detalle' => [],
        ];

        $stats['detalle'][] = sprintf(
            'L8 pedido id=%d codigo=%s (%s, %s) → L12 codigo=%s',
            $pedidoL8->id,
            $pedidoL8->codigo,
            $clienteNombre,
            $pedidoL8->estadopedido ?? '?',
            $codigoDestino
        );
        $stats['detalle'][] = sprintf(
            'Líneas: %d comb / %d talles / %d OT / %d tareas / %d OCT',
            $combinaciones->count(),
            $talles->count(),
            $ots->count(),
            $tareas->count(),
            $octs->count()
        );
        foreach ($otMapDetalle as $line) {
            $stats['detalle'][] = $line;
        }
        $stats['detalle'][] = sprintf(
            'L12 pedido codigo=%s (PINAR u otro) NO se modifica.',
            $pedidoL8->codigo
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
            $pedidoRow = FerliL8ImportRowSupport::normalize('pedido', (array) $pedidoL8, $colsPedido);
            unset($pedidoRow['id']);
            $pedidoRow['codigo'] = $codigoDestino;
            $pedidoL12Id = (int) DB::table('pedido')->insertGetId($pedidoRow);
            $stats['l12_pedido_id'] = $pedidoL12Id;

            // OTs primero (ot_id en combinación apunta al código).
            foreach ($ots as $ot) {
                $nuevo = $otMapL8aL12[(int) $ot->id];
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
                unset($ottRow['id']);
                $otL8 = (int) $ottRow['ordentrabajo_id'];
                if (! isset($otMapL8aL12[$otL8])) {
                    throw new RuntimeException("Sin mapa OT para tarea ordentrabajo_id={$otL8}.");
                }
                $ottRow['ordentrabajo_id'] = $otMapL8aL12[$otL8];
                $pcL8 = (int) ($ottRow['pedido_combinacion_id'] ?? 0);
                if ($pcL8 > 0) {
                    if (! isset($mapaPc[$pcL8])) {
                        throw new RuntimeException("Sin mapa PC para tarea pc={$pcL8}.");
                    }
                    $ottRow['pedido_combinacion_id'] = $mapaPc[$pcL8];
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
                // Tabla ordentrabajo_stock no existe en L12.
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
        Log::info('ferli.l8.importar_uno_renumerado.ok', $stats);

        return $stats;
    }
}
