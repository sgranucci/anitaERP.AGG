<?php

namespace App\Services\Ventas\Ferli;

use App\Support\Ventas\Ferli\FerliL8ReaderSupport;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

/**
 * Importa a L12 los pedidos que existen en L8 y aún no están en L12 (cabecera + líneas + OT + tareas).
 */
class PedidoImportarFaltantesDesdeL8Service
{
    public function __construct(
        private readonly FerliL8ReaderSupport $reader,
    ) {
    }

    /**
     * @return array{
     *   fuente: string,
     *   candidatos: int,
     *   insert_pedido: int,
     *   insert_combinacion: int,
     *   insert_talle: int,
     *   insert_estado: int,
     *   insert_ordentrabajo: int,
     *   insert_oct: int,
     *   insert_tarea: int,
     *   insert_movimiento: int,
     *   errores: list<string>,
     *   detalle: list<string>
     * }
     */
    public function importar(?string $fechaDesde = null, int $limite = 100, bool $dryRun = false): array
    {
        FerliL8ReaderSupport::assertFerli();

        ini_set('max_execution_time', '600');
        ini_set('memory_limit', '512M');

        $payload = $this->reader->payloadPedidosFaltantes($fechaDesde, $limite);

        // Por si el bridge HTTP no filtró: quedarnos solo con pedidos inexistentes en L12.
        $codigosL12 = array_flip(DB::table('pedido')->pluck('codigo')->map(static fn ($c) => (string) $c)->all());
        $idsL12 = array_flip(DB::table('pedido')->pluck('id')->map(static fn ($c) => (int) $c)->all());
        $pedidosOk = [];
        $pedidoIdsOk = [];
        foreach ($payload['pedidos'] ?? [] as $p) {
            $id = (int) ($p['id'] ?? 0);
            $codigo = (string) ($p['codigo'] ?? '');
            if (isset($idsL12[$id]) || ($codigo !== '' && isset($codigosL12[$codigo]))) {
                continue;
            }
            $pedidosOk[] = $p;
            $pedidoIdsOk[$id] = true;
        }
        $payload['pedidos'] = $pedidosOk;
        if ($pedidoIdsOk !== []) {
            $payload['pedido_combinacion'] = array_values(array_filter(
                $payload['pedido_combinacion'] ?? [],
                static fn ($r) => isset($pedidoIdsOk[(int) ($r['pedido_id'] ?? 0)])
            ));
            $pcIdsOk = array_flip(array_map(static fn ($r) => (int) $r['id'], $payload['pedido_combinacion']));
            $payload['pedido_combinacion_talle'] = array_values(array_filter(
                $payload['pedido_combinacion_talle'] ?? [],
                static fn ($r) => isset($pcIdsOk[(int) ($r['pedido_combinacion_id'] ?? 0)])
            ));
            $payload['pedido_combinacion_estado'] = array_values(array_filter(
                $payload['pedido_combinacion_estado'] ?? [],
                static fn ($r) => isset($pcIdsOk[(int) ($r['pedido_combinacion_id'] ?? 0)])
            ));
            $otCodigosOk = array_flip(array_values(array_unique(array_filter(
                array_map(static fn ($r) => (int) ($r['ot_id'] ?? 0), $payload['pedido_combinacion']),
                static fn ($c) => $c > 0
            ))));
            $payload['ordentrabajo'] = array_values(array_filter(
                $payload['ordentrabajo'] ?? [],
                static fn ($r) => isset($otCodigosOk[(int) ($r['codigo'] ?? 0)])
            ));
            $otIdsOk = array_flip(array_map(static fn ($r) => (int) $r['id'], $payload['ordentrabajo']));
            $payload['ordentrabajo_combinacion_talle'] = array_values(array_filter(
                $payload['ordentrabajo_combinacion_talle'] ?? [],
                static fn ($r) => isset($otIdsOk[(int) ($r['ordentrabajo_id'] ?? 0)])
            ));
            $payload['ordentrabajo_tarea'] = array_values(array_filter(
                $payload['ordentrabajo_tarea'] ?? [],
                static fn ($r) => isset($otIdsOk[(int) ($r['ordentrabajo_id'] ?? 0)])
            ));
            $payload['movimientoordentrabajo'] = array_values(array_filter(
                $payload['movimientoordentrabajo'] ?? [],
                static fn ($r) => isset($otIdsOk[(int) ($r['ordentrabajo_id'] ?? 0)])
            ));
        } else {
            $payload['pedido_combinacion'] = [];
            $payload['pedido_combinacion_talle'] = [];
            $payload['pedido_combinacion_estado'] = [];
            $payload['ordentrabajo'] = [];
            $payload['ordentrabajo_combinacion_talle'] = [];
            $payload['ordentrabajo_tarea'] = [];
            $payload['movimientoordentrabajo'] = [];
        }

        $stats = [
            'fuente' => (string) ($payload['fuente'] ?? ''),
            'candidatos' => count($payload['pedidos'] ?? []),
            'insert_pedido' => 0,
            'insert_combinacion' => 0,
            'insert_talle' => 0,
            'insert_estado' => 0,
            'insert_ordentrabajo' => 0,
            'insert_oct' => 0,
            'insert_tarea' => 0,
            'insert_movimiento' => 0,
            'errores' => [],
            'detalle' => [],
        ];

        if ($stats['candidatos'] === 0) {
            $stats['detalle'][] = 'No hay pedidos en L8 que falten en L12'
                .($fechaDesde ? " (desde {$fechaDesde})" : '')
                .'.';

            return $stats;
        }

        if ($dryRun) {
            foreach (array_slice($payload['pedidos'], 0, 20) as $p) {
                $stats['detalle'][] = sprintf(
                    'Falta pedido id=%s codigo=%s fecha=%s',
                    $p['id'] ?? '?',
                    $p['codigo'] ?? '?',
                    $p['fecha'] ?? '?'
                );
            }

            return $stats;
        }

        DB::beginTransaction();
        try {
            DB::statement('SET FOREIGN_KEY_CHECKS=0');

            $stats['insert_pedido'] = $this->insertMissing('pedido', $payload['pedidos'] ?? []);
            $stats['insert_combinacion'] = $this->insertMissing('pedido_combinacion', $payload['pedido_combinacion'] ?? []);
            $stats['insert_talle'] = $this->insertMissing('pedido_combinacion_talle', $payload['pedido_combinacion_talle'] ?? []);
            if (Schema::hasTable('pedido_combinacion_estado')) {
                $stats['insert_estado'] = $this->insertMissing('pedido_combinacion_estado', $payload['pedido_combinacion_estado'] ?? []);
            }
            $stats['insert_ordentrabajo'] = $this->insertMissing('ordentrabajo', $payload['ordentrabajo'] ?? []);
            $stats['insert_oct'] = $this->insertMissing('ordentrabajo_combinacion_talle', $payload['ordentrabajo_combinacion_talle'] ?? []);
            $stats['insert_tarea'] = $this->insertMissing('ordentrabajo_tarea', $payload['ordentrabajo_tarea'] ?? []);
            $stats['insert_movimiento'] = $this->insertMissing('movimientoordentrabajo', $payload['movimientoordentrabajo'] ?? []);

            DB::statement('SET FOREIGN_KEY_CHECKS=1');
            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            try {
                DB::statement('SET FOREIGN_KEY_CHECKS=1');
            } catch (\Throwable) {
            }
            Log::error('ferli.l8.importar_pedidos.fallo', ['error' => $e->getMessage()]);
            throw new RuntimeException('Error importando pedidos desde L8: '.$e->getMessage(), 0, $e);
        }

        $stats['detalle'][] = sprintf(
            'Importados %d pedidos (+%d comb, +%d talles, +%d OT, +%d tareas) desde %s.',
            $stats['insert_pedido'],
            $stats['insert_combinacion'],
            $stats['insert_talle'],
            $stats['insert_ordentrabajo'],
            $stats['insert_tarea'],
            $stats['fuente']
        );

        Log::info('ferli.l8.importar_pedidos.ok', $stats);

        return $stats;
    }

    /**
     * Preview liviano para el modal del index.
     *
     * @return list<array{id: int, codigo: string, fecha: string|null, cliente_id: int|null}>
     */
    public function listarPreview(?string $fechaDesde = null, int $limite = 50): array
    {
        $payload = $this->reader->payloadPedidosFaltantes($fechaDesde, $limite);
        $out = [];
        foreach ($payload['pedidos'] ?? [] as $p) {
            $out[] = [
                'id' => (int) ($p['id'] ?? 0),
                'codigo' => (string) ($p['codigo'] ?? ''),
                'fecha' => isset($p['fecha']) ? (string) $p['fecha'] : null,
                'cliente_id' => isset($p['cliente_id']) ? (int) $p['cliente_id'] : null,
            ];
        }

        return $out;
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    private function insertMissing(string $table, array $rows): int
    {
        if ($rows === [] || ! Schema::hasTable($table)) {
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
            try {
                DB::table($table)->insert($clean);
                $n++;
            } catch (\Throwable $e) {
                Log::warning('ferli.l8.insert_fila', [
                    'table' => $table,
                    'id' => $id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $n;
    }
}
