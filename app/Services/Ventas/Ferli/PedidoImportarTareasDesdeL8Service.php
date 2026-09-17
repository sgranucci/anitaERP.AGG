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

        $stats = $this->importarPorOtCodigos($otCodigos, $dryRun, actualizarTareasExistentes: true);

        if ($otCodigos === [] && $stats['detalle'] === []) {
            $stats['detalle'][] = 'El pedido no tiene órdenes de trabajo asociadas.';
        } elseif ($stats['detalle'] !== []) {
            // Prefijo con código de pedido en el resumen.
            $stats['detalle'][0] = sprintf(
                'Pedido %s: %s',
                (string) $pedido->codigo,
                $stats['detalle'][0]
            );
        }

        return $stats;
    }

    /**
     * Trae desde L8 las tareas del rango: inserta las que faltan en L12 (por id)
     * y actualiza fechas de finalización/inicio/estado de las ya existentes. No duplica.
     *
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
    public function importarFaltantesPorRangoFechas(string $fechaDesde, string $fechaHasta, bool $dryRun = false): array
    {
        FerliL8ReaderSupport::assertFerli();

        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $fechaDesde)
            || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $fechaHasta)) {
            throw new RuntimeException('Fechas inválidas. Use formato AAAA-MM-DD.');
        }
        if ($fechaDesde > $fechaHasta) {
            throw new RuntimeException('La fecha desde no puede ser posterior a la fecha hasta.');
        }

        // Todas las OT con tareas L8 en el rango (faltantes + existentes a refrescar fechas).
        $otCodigos = $this->reader->otCodigosConTareasEnRango($fechaDesde, $fechaHasta);
        if ($otCodigos === []) {
            $otCodigos = $this->otCodigosPedidosEnRango($fechaDesde, $fechaHasta);
        }
        // También OT ya en L12 con tareas del período (por si el bridge no listó alguna).
        $otCodigos = array_values(array_unique(array_merge(
            $otCodigos,
            $this->otCodigosConTareasL12EnRango($fechaDesde, $fechaHasta)
        )));

        $stats = $this->importarPorOtCodigos(
            $otCodigos,
            $dryRun,
            actualizarTareasExistentes: true,
            soloActualizarFechasTarea: true
        );
        $stats['detalle'][] = sprintf(
            'Rango %s a %s — altas de ids faltantes + actualización de fechas de finalización (sin duplicar).',
            $fechaDesde,
            $fechaHasta
        );

        return $stats;
    }

    /**
     * @param  list<int>  $otCodigos
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
    public function importarPorOtCodigos(
        array $otCodigos,
        bool $dryRun = false,
        bool $actualizarTareasExistentes = true,
        bool $soloActualizarFechasTarea = false
    ): array {
        FerliL8ReaderSupport::assertFerli();

        ini_set('max_execution_time', '600');
        ini_set('memory_limit', '512M');

        $otCodigos = array_values(array_unique(array_filter(array_map('intval', $otCodigos), static fn ($c) => $c > 0)));

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
            $stats['detalle'][] = 'No hay órdenes de trabajo para importar.';

            return $stats;
        }

        // Lotes chicos: el bridge/HTTP y la memoria no bancan miles de OT de una.
        $fuentes = [];
        foreach (array_chunk($otCodigos, 40) as $lote) {
            $payload = $this->reader->payloadTareasPorOtCodigos($lote);
            $fuentes[(string) ($payload['fuente'] ?? '')] = true;
            $tareasPayload = $payload['ordentrabajo_tarea'] ?? [];
            $tareasNuevas = $this->filtrarTareasFaltantesEnL12($tareasPayload);

            if ($dryRun) {
                $stats['insert_tarea'] += count($tareasNuevas);
                if ($actualizarTareasExistentes) {
                    $stats['update_tarea'] += count($tareasPayload) - count($tareasNuevas);
                } else {
                    $stats['omitidos'] += count($tareasPayload) - count($tareasNuevas);
                }
                $stats['insert_ordentrabajo'] += count($payload['ordentrabajo'] ?? []);
                $stats['insert_movimiento'] += count($payload['movimientoordentrabajo'] ?? []);
                continue;
            }

            DB::beginTransaction();
            try {
                $stats['insert_ordentrabajo'] += $this->insertMissing('ordentrabajo', $payload['ordentrabajo'] ?? []);
                [$insOct, $updOct] = $this->upsertRowsConUpdate('ordentrabajo_combinacion_talle', $payload['ordentrabajo_combinacion_talle'] ?? []);
                $stats['insert_oct'] += $insOct;
                $stats['update_oct'] += $updOct;

                if ($actualizarTareasExistentes && ! $soloActualizarFechasTarea) {
                    // Pedido puntual: sincroniza todos los campos (mismo id).
                    [$insT, $updT] = $this->upsertRowsConUpdate('ordentrabajo_tarea', $tareasPayload);
                    $stats['insert_tarea'] += $insT;
                    $stats['update_tarea'] += $updT;
                } else {
                    // Liquidación: altas sin duplicar + refresco de fechas de finalización.
                    $stats['insert_tarea'] += $this->insertMissing('ordentrabajo_tarea', $tareasNuevas);
                    if ($actualizarTareasExistentes) {
                        $stats['update_tarea'] += $this->actualizarFechasTareasExistentes($tareasPayload);
                    } else {
                        $stats['omitidos'] += count($tareasPayload) - count($tareasNuevas);
                    }
                }

                $stats['insert_movimiento'] += $this->insertMissing('movimientoordentrabajo', $payload['movimientoordentrabajo'] ?? []);
                DB::commit();
            } catch (\Throwable $e) {
                DB::rollBack();
                Log::error('ferli.l8.importar_tareas.fallo', [
                    'ots' => $lote,
                    'error' => $e->getMessage(),
                ]);
                throw $e;
            }
        }

        $stats['fuente'] = implode(',', array_keys(array_filter($fuentes)));

        if ($dryRun) {
            $stats['detalle'][] = sprintf(
                'Dry-run: %d OT candidatas — tareas nuevas %d, fechas a actualizar ~%d.',
                $stats['ots'],
                $stats['insert_tarea'],
                $stats['update_tarea']
            );
        } else {
            $stats['detalle'][] = sprintf(
                'OT %d — OT nuevas %d, tareas +%d, fechas actualizadas %d, movimientos +%d.',
                $stats['ots'],
                $stats['insert_ordentrabajo'],
                $stats['insert_tarea'],
                $stats['update_tarea'],
                $stats['insert_movimiento']
            );
            Log::info('ferli.l8.importar_tareas.ok', $stats);
        }

        return $stats;
    }

    /**
     * @return list<int>
     */
    private function otCodigosPedidosEnRango(string $fechaDesde, string $fechaHasta): array
    {
        return DB::table('pedido_combinacion as pc')
            ->join('pedido as p', 'p.id', '=', 'pc.pedido_id')
            ->whereBetween('p.fecha', [$fechaDesde, $fechaHasta])
            ->whereNotNull('pc.ot_id')
            ->where('pc.ot_id', '>', 0)
            ->distinct()
            ->orderBy('pc.ot_id')
            ->limit(2000)
            ->pluck('pc.ot_id')
            ->map(static fn ($c) => (int) $c)
            ->values()
            ->all();
    }

    /**
     * OT de L12 que ya tienen tareas en el rango (para refrescar hastafecha desde L8).
     *
     * @return list<int>
     */
    private function otCodigosConTareasL12EnRango(string $fechaDesde, string $fechaHasta): array
    {
        return DB::table('ordentrabajo_tarea as ott')
            ->join('ordentrabajo as ot', 'ot.id', '=', 'ott.ordentrabajo_id')
            ->where(function ($w) use ($fechaDesde, $fechaHasta) {
                $w->whereBetween('ott.hastafecha', [$fechaDesde, $fechaHasta])
                    ->orWhereBetween('ott.desdefecha', [$fechaDesde, $fechaHasta])
                    ->orWhere(function ($p) use ($fechaDesde, $fechaHasta) {
                        // Pendientes abiertas en el período: desdefecha en rango y sin cierre.
                        $p->whereBetween('ott.desdefecha', [$fechaDesde, $fechaHasta])
                            ->whereNull('ott.hastafecha');
                    });
            })
            ->distinct()
            ->orderBy('ot.codigo')
            ->limit(2000)
            ->pluck('ot.codigo')
            ->map(static fn ($c) => (int) $c)
            ->filter(static fn ($c) => $c > 0)
            ->values()
            ->all();
    }

    /**
     * Actualiza solo fechas/estado de tareas ya presentes (mismo id). No inserta.
     *
     * @param  list<array<string, mixed>>  $tareas
     */
    private function actualizarFechasTareasExistentes(array $tareas): int
    {
        if ($tareas === []) {
            return 0;
        }
        $cols = Schema::getColumnListing('ordentrabajo_tarea');
        $campos = array_values(array_intersect(['desdefecha', 'hastafecha', 'estado'], $cols));
        if ($campos === []) {
            return 0;
        }

        $upd = 0;
        foreach ($tareas as $row) {
            $clean = FerliL8ImportRowSupport::normalize('ordentrabajo_tarea', $row, $cols);
            $id = (int) ($clean['id'] ?? 0);
            if ($id <= 0) {
                continue;
            }
            $existente = DB::table('ordentrabajo_tarea')->where('id', $id)->first();
            if (! $existente) {
                continue;
            }

            $data = [];
            foreach ($campos as $campo) {
                if (! array_key_exists($campo, $clean)) {
                    continue;
                }
                $nuevo = $clean[$campo];
                $actual = $existente->{$campo} ?? null;
                $nuevoNorm = $this->normalizarFechaComparacion($campo, $nuevo);
                $actualNorm = $this->normalizarFechaComparacion($campo, $actual);
                if ($nuevoNorm !== $actualNorm) {
                    $data[$campo] = $nuevo;
                }
            }
            if ($data === []) {
                continue;
            }
            if (in_array('updated_at', $cols, true)) {
                $data['updated_at'] = now();
            }
            DB::table('ordentrabajo_tarea')->where('id', $id)->update($data);
            $upd++;
        }

        return $upd;
    }

    private function normalizarFechaComparacion(string $campo, mixed $valor): string
    {
        if ($valor === null || $valor === '' || $valor === '0000-00-00' || $valor === '0000-00-00 00:00:00') {
            return '';
        }
        if (in_array($campo, ['desdefecha', 'hastafecha'], true)) {
            return substr((string) $valor, 0, 10);
        }

        return (string) $valor;
    }

    /**
     * @param  list<array<string, mixed>>  $tareas
     * @return list<array<string, mixed>>
     */
    private function filtrarTareasFaltantesEnL12(array $tareas): array
    {
        if ($tareas === []) {
            return [];
        }
        $ids = [];
        foreach ($tareas as $row) {
            $id = (int) ($row['id'] ?? 0);
            if ($id > 0) {
                $ids[] = $id;
            }
        }
        if ($ids === []) {
            return [];
        }

        $existentes = [];
        foreach (array_chunk($ids, 500) as $chunk) {
            foreach (DB::table('ordentrabajo_tarea')->whereIn('id', $chunk)->pluck('id') as $id) {
                $existentes[(int) $id] = true;
            }
        }

        return array_values(array_filter(
            $tareas,
            static fn ($row) => ! isset($existentes[(int) ($row['id'] ?? 0)])
        ));
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
                continue;
            }

            // OCT: L8 y L12 pueden tener distinto id para el mismo OT+PCT.
            // Insertar por id nuevo duplica pares al facturar.
            if ($table === 'ordentrabajo_combinacion_talle') {
                $otId = (int) ($clean['ordentrabajo_id'] ?? 0);
                $pctId = (int) ($clean['pedido_combinacion_talle_id'] ?? 0);
                if ($otId > 0 && $pctId > 0) {
                    $existenteId = (int) (DB::table($table)
                        ->where('ordentrabajo_id', $otId)
                        ->where('pedido_combinacion_talle_id', $pctId)
                        ->orderBy('id')
                        ->value('id') ?? 0);
                    if ($existenteId > 0) {
                        $data = $clean;
                        unset($data['id'], $data['created_at']);
                        DB::table($table)->where('id', $existenteId)->update($data);
                        $upd++;
                        continue;
                    }
                }
            }

            DB::table($table)->insert($clean);
            $ins++;
        }

        return [$ins, $upd];
    }
}
