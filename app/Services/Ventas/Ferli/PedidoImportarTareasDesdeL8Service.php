<?php

namespace App\Services\Ventas\Ferli;

use App\Support\Ventas\Ferli\FerliL8ReaderSupport;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

/**
 * Importa desde L8 las tareas (y movimientos) faltantes de las OT de un pedido en L12,
 * para poder facturar mientras producción sigue en el sistema legacy.
 */
class PedidoImportarTareasDesdeL8Service
{
    public function __construct(
        private readonly FerliL8ReaderSupport $reader,
    ) {
    }

    /**
     * @return array{
     *   fuente: string,
     *   ots: int,
     *   insert_ordentrabajo: int,
     *   insert_tarea: int,
     *   update_tarea: int,
     *   insert_movimiento: int,
     *   insert_oct: int,
     *   update_oct: int,
     *   omitidos: int,
     *   detalle: list<string>
     * }
     */
    public function importarPorPedidoId(int $pedidoId, bool $dryRun = false): array
    {
        FerliL8ReaderSupport::assertFerli();

        ini_set('max_execution_time', '300');
        ini_set('memory_limit', '512M');

        $pedido = DB::table('pedido')->where('id', $pedidoId)->first();
        if (! $pedido) {
            throw new RuntimeException("Pedido {$pedidoId} no existe en L12.");
        }

        $otCodigos = DB::table('pedido_combinacion')
            ->where('pedido_id', $pedidoId)
            ->whereNotNull('ot_id')
            ->where('ot_id', '>', 0)
            ->pluck('ot_id')
            ->map(static fn ($c) => (int) $c)
            ->unique()
            ->values()
            ->all();

        $stats = [
            'fuente' => '',
            'ots' => count($otCodigos),
            'insert_ordentrabajo' => 0,
            'insert_tarea' => 0,
            'update_tarea' => 0,
            'insert_movimiento' => 0,
            'insert_oct' => 0,
            'update_oct' => 0,
            'omitidos' => 0,
            'detalle' => [],
        ];

        if ($otCodigos === []) {
            $stats['detalle'][] = 'El pedido no tiene órdenes de trabajo asociadas.';

            return $stats;
        }

        $payload = $this->reader->payloadTareasPorOtCodigos($otCodigos);
        $stats['fuente'] = (string) ($payload['fuente'] ?? '');

        if ($dryRun) {
            $stats['detalle'][] = sprintf(
                'Dry-run: L8 trae %d OT, %d tareas, %d movimientos.',
                count($payload['ordentrabajo'] ?? []),
                count($payload['ordentrabajo_tarea'] ?? []),
                count($payload['movimientoordentrabajo'] ?? [])
            );

            return $stats;
        }

        DB::beginTransaction();
        try {
            $stats['insert_ordentrabajo'] = $this->upsertRows('ordentrabajo', $payload['ordentrabajo'] ?? [], false);
            [$insOct, $updOct] = $this->upsertRowsConUpdate('ordentrabajo_combinacion_talle', $payload['ordentrabajo_combinacion_talle'] ?? []);
            $stats['insert_oct'] = $insOct;
            $stats['update_oct'] = $updOct;
            [$insT, $updT] = $this->upsertRowsConUpdate('ordentrabajo_tarea', $payload['ordentrabajo_tarea'] ?? []);
            $stats['insert_tarea'] = $insT;
            $stats['update_tarea'] = $updT;
            $stats['insert_movimiento'] = $this->insertMissing('movimientoordentrabajo', $payload['movimientoordentrabajo'] ?? []);
            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('ferli.l8.importar_tareas.fallo', [
                'pedido_id' => $pedidoId,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }

        $stats['detalle'][] = sprintf(
            'Pedido %s: OT %d — OT nuevas %d, tareas +%d/~%d, movimientos +%d.',
            (string) $pedido->codigo,
            $stats['ots'],
            $stats['insert_ordentrabajo'],
            $stats['insert_tarea'],
            $stats['update_tarea'],
            $stats['insert_movimiento']
        );

        Log::info('ferli.l8.importar_tareas.ok', $stats);

        return $stats;
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    private function insertMissing(string $table, array $rows): int
    {
        if ($rows === []) {
            return 0;
        }
        $cols = Schema::getColumnListing($table);
        $n = 0;
        foreach ($rows as $row) {
            $clean = FerliL8ImportRowSupport::normalize($table, $row, $cols);
            $id = (int) ($clean['id'] ?? 0);
            if ($id <= 0) {
                continue;
            }
            if (DB::table($table)->where('id', $id)->exists()) {
                continue;
            }
            if ($table === 'movimientoordentrabajo') {
                $ottId = (int) ($clean['ordentrabajo_tarea_id'] ?? 0);
                if ($ottId > 0 && ! DB::table('ordentrabajo_tarea')->where('id', $ottId)->exists()) {
                    continue;
                }
            }
            DB::table($table)->insert($clean);
            $n++;
        }

        return $n;
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    private function upsertRows(string $table, array $rows, bool $update): int
    {
        if ($update) {
            [$ins] = $this->upsertRowsConUpdate($table, $rows);

            return $ins;
        }

        return $this->insertMissing($table, $rows);
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return array{0: int, 1: int}
     */
    private function upsertRowsConUpdate(string $table, array $rows): array
    {
        if ($rows === []) {
            return [0, 0];
        }
        $cols = Schema::getColumnListing($table);
        $ins = 0;
        $upd = 0;
        foreach ($rows as $row) {
            $clean = FerliL8ImportRowSupport::normalize($table, $row, $cols);
            $id = (int) ($clean['id'] ?? 0);
            if ($id <= 0) {
                continue;
            }
            if (DB::table($table)->where('id', $id)->exists()) {
                $data = $clean;
                unset($data['id'], $data['created_at']);
                DB::table($table)->where('id', $id)->update($data);
                $upd++;
            } else {
                DB::table($table)->insert($clean);
                $ins++;
            }
        }

        return [$ins, $upd];
    }
}
