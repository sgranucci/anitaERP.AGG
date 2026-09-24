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

        // 1) OT ya ligadas en L12.
        $otCodigos = DB::table('pedido_combinacion')
            ->where('pedido_id', $pedidoId)
            ->whereNotNull('ot_id')
            ->where('ot_id', '>', 0)
            ->pluck('ot_id')
            ->map(static fn ($c) => (int) $c)
            ->unique()
            ->values()
            ->all();

        // 2) OT que L8 tiene para este pedido (aunque L12 no las haya ligado,
        //    p.ej. reparación de líneas con ids nuevos / OT mal asignadas a otro pedido).
        $sync = $this->sincronizarOtIdsYMapaCombinacionesDesdeL8($pedidoId, (string) $pedido->codigo, $dryRun);
        $otCodigos = array_values(array_unique(array_merge($otCodigos, $sync['ot_codigos'])));

        // Primero limpia OCT que apuntan a PCT ajenos / duplicados (evita UK al remapear).
        $limpieza = ['remapeados' => 0, 'eliminados' => 0, 'detalle' => []];
        if (! $dryRun && $sync['mapa_pc'] !== []) {
            $limpieza = $this->limpiarOctHuerfanasDePedido($pedidoId, $otCodigos, $sync['mapa_pc']);
        }

        $stats = $this->importarPorOtCodigos(
            $otCodigos,
            $dryRun,
            actualizarTareasExistentes: true,
            soloActualizarFechasTarea: false,
            mapaPcL8aL12: $sync['mapa_pc'],
        );
        $stats['ot_sincronizados'] = $sync['ot_sincronizados'];
        $stats['ot_reclamados'] = $sync['ot_reclamados'];
        $stats['oct_remapeados'] = $limpieza['remapeados'];
        $stats['oct_eliminados'] = $limpieza['eliminados'];
        if ($limpieza['detalle'] !== []) {
            $stats['detalle'] = array_merge($limpieza['detalle'], $stats['detalle']);
        }

        if ($otCodigos === [] && $stats['detalle'] === []) {
            $stats['detalle'][] = 'El pedido no tiene órdenes de trabajo asociadas (ni en L12 ni en L8).';
        } elseif ($stats['detalle'] !== []) {
            $stats['detalle'][0] = sprintf(
                'Pedido %s: %s',
                (string) $pedido->codigo,
                $stats['detalle'][0]
            );
        }
        if ($sync['detalle'] !== []) {
            array_unshift($stats['detalle'], ...$sync['detalle']);
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
        bool $soloActualizarFechasTarea = false,
        array $mapaPcL8aL12 = []
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
            $tareasPayload = $this->remapearPedidoCombinacionId(
                $payload['ordentrabajo_tarea'] ?? [],
                $mapaPcL8aL12
            );
            $octPayload = $this->remapearOctPedidoCombinacionTalle(
                $payload['ordentrabajo_combinacion_talle'] ?? [],
                $mapaPcL8aL12
            );
            $tareasNuevas = $this->filtrarTareasFaltantesEnL12($tareasPayload);

            if ($dryRun) {
                // Cuenta también las que chocan por id pero faltan por (OT, tarea_id, pc).
                $faltanClave = $this->tareasQueFaltanPorClaveNatural($tareasPayload);
                $stats['insert_tarea'] += count($faltanClave);
                if ($actualizarTareasExistentes) {
                    $stats['update_tarea'] += count($tareasPayload) - count($faltanClave);
                } else {
                    $stats['omitidos'] += count($tareasPayload) - count($faltanClave);
                }
                $stats['insert_ordentrabajo'] += count($payload['ordentrabajo'] ?? []);
                $stats['insert_oct'] += count($octPayload);
                $stats['insert_movimiento'] += count($payload['movimientoordentrabajo'] ?? []);
                continue;
            }

            DB::beginTransaction();
            try {
                $stats['insert_ordentrabajo'] += $this->insertMissing('ordentrabajo', $payload['ordentrabajo'] ?? []);
                [$insOct, $updOct] = $this->upsertRowsConUpdate('ordentrabajo_combinacion_talle', $octPayload);
                $stats['insert_oct'] += $insOct;
                $stats['update_oct'] += $updOct;

                if ($actualizarTareasExistentes && ! $soloActualizarFechasTarea) {
                    // Pedido puntual: sincroniza por id o por (OT, tarea_id, pc).
                    // OT compartida (misma combinación en N pedidos) tiene Empaque/Terminada
                    // por cada pedido_combinacion_id; no colapsar a una sola fila por OT+tarea.
                    // Si el id L8 ya pertenece a otra OT en L12, inserta con id nuevo.
                    [$insT, $updT] = $this->upsertTareasConColision($tareasPayload);
                    $stats['insert_tarea'] += $insT;
                    $stats['update_tarea'] += $updT;
                } else {
                    // Liquidación: altas sin duplicar + refresco de fechas de finalización.
                    $stats['insert_tarea'] += $this->insertMissingTareasConColision(
                        $this->tareasQueFaltanPorClaveNatural($tareasPayload)
                    );
                    if ($actualizarTareasExistentes) {
                        $stats['update_tarea'] += $this->actualizarFechasTareasExistentes($tareasPayload);
                    } else {
                        $stats['omitidos'] += count($tareasPayload) - count($tareasNuevas);
                    }
                }

                $stats['insert_movimiento'] += $this->insertMissing(
                    'movimientoordentrabajo',
                    $payload['movimientoordentrabajo'] ?? []
                );
                DB::commit();
            } catch (\Throwable $e) {
                DB::rollBack();
                try {
                    Log::error('ferli.l8.importar_tareas.fallo', [
                        'ots' => $lote,
                        'error' => $e->getMessage(),
                    ]);
                } catch (\Throwable) {
                }
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
            try {
                Log::info('ferli.l8.importar_tareas.ok', $stats);
            } catch (\Throwable) {
                // storage/logs a veces no es escribible por CLI; no invalidar la importación.
            }
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
     * Liga en L12 las OT que L8 tiene para el pedido y arma mapa pc L8 → pc L12
     * (necesario cuando la reparación de líneas creó ids nuevos por colisión).
     *
     * @return array{
     *   ot_codigos: list<int>,
     *   mapa_pc: array<int, int>,
     *   ot_sincronizados: int,
     *   ot_reclamados: int,
     *   detalle: list<string>
     * }
     */
    private function sincronizarOtIdsYMapaCombinacionesDesdeL8(int $pedidoId, string $codigoPedido, bool $dryRun): array
    {
        $out = [
            'ot_codigos' => [],
            'mapa_pc' => [],
            'ot_sincronizados' => 0,
            'ot_reclamados' => 0,
            'detalle' => [],
        ];

        try {
            $fuente = $this->reader->resolverFuente();
        } catch (\Throwable) {
            return $out;
        }

        if ($fuente['fuente'] !== 'mysql_l8' || $fuente['conexion'] === null) {
            // Bridge HTTP: sin sync de ot_id; igual se pueden pedir OT por código si el bridge lo expone.
            return $out;
        }

        /** @var \Illuminate\Database\ConnectionInterface $l8 */
        $l8 = $fuente['conexion'];

        $pedidoL8 = $l8->table('pedido')->where('id', $pedidoId)->first()
            ?? $l8->table('pedido')->where('codigo', $codigoPedido)->first();
        if (! $pedidoL8) {
            return $out;
        }

        $pcsL8 = $l8->table('pedido_combinacion')
            ->where('pedido_id', (int) $pedidoL8->id)
            ->orderBy('id')
            ->get();
        $pcsL12 = DB::table('pedido_combinacion')
            ->where('pedido_id', $pedidoId)
            ->orderBy('id')
            ->get();

        if ($pcsL8->isEmpty() || $pcsL12->isEmpty()) {
            return $out;
        }

        // Índice L12 por (numeroitem|combinacion_id) y por (articulo_id|combinacion_id).
        $porClaveItem = [];
        $porClaveArt = [];
        foreach ($pcsL12 as $pc) {
            $porClaveItem[(int) $pc->numeroitem.'|'.(int) $pc->combinacion_id] = $pc;
            $claveArt = (int) $pc->articulo_id.'|'.(int) $pc->combinacion_id;
            if (! isset($porClaveArt[$claveArt])) {
                $porClaveArt[$claveArt] = [];
            }
            $porClaveArt[$claveArt][] = $pc;
        }

        $usadosL12 = [];
        foreach ($pcsL8 as $pc8) {
            $pc8Id = (int) $pc8->id;
            $otId = (int) ($pc8->ot_id ?? 0);
            if ($otId > 0) {
                $out['ot_codigos'][] = $otId;
            }

            $claveItem = (int) $pc8->numeroitem.'|'.(int) $pc8->combinacion_id;
            $claveArt = (int) $pc8->articulo_id.'|'.(int) $pc8->combinacion_id;
            $match = null;
            if (isset($porClaveItem[$claveItem]) && ! isset($usadosL12[(int) $porClaveItem[$claveItem]->id])) {
                $match = $porClaveItem[$claveItem];
            } elseif (isset($porClaveArt[$claveArt])) {
                foreach ($porClaveArt[$claveArt] as $cand) {
                    if (! isset($usadosL12[(int) $cand->id])) {
                        $match = $cand;
                        break;
                    }
                }
            }
            if ($match === null) {
                continue;
            }
            $usadosL12[(int) $match->id] = true;
            $out['mapa_pc'][$pc8Id] = (int) $match->id;

            if ($otId <= 0) {
                continue;
            }

            $otActual = (int) ($match->ot_id ?? 0);
            if ($otActual === $otId) {
                continue;
            }

            // ¿La OT está en otro pedido L12?
            $dueño = DB::table('pedido_combinacion')
                ->where('ot_id', $otId)
                ->where('pedido_id', '!=', $pedidoId)
                ->first(['id', 'pedido_id']);

            $reclamar = false;
            if ($dueño) {
                // Solo reclamar si en L8 ese dueño no tiene esa OT (asignación errónea en L12).
                $dueñoTieneEnL8 = $l8->table('pedido_combinacion')
                    ->where('pedido_id', (int) $dueño->pedido_id)
                    ->where('ot_id', $otId)
                    ->exists();
                if (! $dueñoTieneEnL8) {
                    $reclamar = true;
                } else {
                    $out['detalle'][] = sprintf(
                        'OT %d omitida: L8 la liga al pedido L12 %d (pc %d).',
                        $otId,
                        (int) $dueño->pedido_id,
                        (int) $dueño->id
                    );
                    continue;
                }
            }

            if ($dryRun) {
                $out['ot_sincronizados']++;
                if ($reclamar) {
                    $out['ot_reclamados']++;
                }
                $out['detalle'][] = sprintf(
                    'Dry-run: pc L12 %d ← ot_id %d (L8 pc %d)%s',
                    (int) $match->id,
                    $otId,
                    $pc8Id,
                    $reclamar ? ' [reclama de pedido '.(int) $dueño->pedido_id.']' : ''
                );
                continue;
            }

            if ($reclamar) {
                DB::table('pedido_combinacion')->where('id', (int) $dueño->id)->update([
                    'ot_id' => 0,
                    'updated_at' => now(),
                ]);
                $out['ot_reclamados']++;
            }

            DB::table('pedido_combinacion')->where('id', (int) $match->id)->update([
                'ot_id' => $otId,
                'updated_at' => now(),
            ]);
            $out['ot_sincronizados']++;
            $match->ot_id = $otId;
        }

        $out['ot_codigos'] = array_values(array_unique(array_filter($out['ot_codigos'])));

        return $out;
    }

    /**
     * Remapea pedido_combinacion_talle_id de OCT L8 → L12 usando el mapa de pc
     * (talle_id + pc L12). Sin mapa, deja el id L8 (casos sin colisión).
     *
     * @param  list<array<string, mixed>>  $octRows
     * @param  array<int, int>  $mapaPcL8aL12
     * @return list<array<string, mixed>>
     */
    private function remapearOctPedidoCombinacionTalle(array $octRows, array $mapaPcL8aL12): array
    {
        if ($octRows === [] || $mapaPcL8aL12 === []) {
            return $octRows;
        }

        $mapaPct = $this->mapaPctL8aL12($mapaPcL8aL12);
        if ($mapaPct === []) {
            return $octRows;
        }

        foreach ($octRows as &$row) {
            $pctL8 = (int) ($row['pedido_combinacion_talle_id'] ?? 0);
            if ($pctL8 > 0 && isset($mapaPct[$pctL8])) {
                $row['pedido_combinacion_talle_id'] = $mapaPct[$pctL8];
            }
        }
        unset($row);

        return $octRows;
    }

    /**
     * @param  array<int, int>  $mapaPcL8aL12
     * @return array<int, int> pct L8 id → pct L12 id
     */
    private function mapaPctL8aL12(array $mapaPcL8aL12): array
    {
        try {
            $fuente = $this->reader->resolverFuente();
        } catch (\Throwable) {
            return [];
        }
        if ($fuente['fuente'] !== 'mysql_l8' || $fuente['conexion'] === null) {
            return [];
        }
        /** @var \Illuminate\Database\ConnectionInterface $l8 */
        $l8 = $fuente['conexion'];

        $mapa = [];
        foreach ($mapaPcL8aL12 as $pc8 => $pc12) {
            $talles8 = $l8->table('pedido_combinacion_talle')
                ->where('pedido_combinacion_id', $pc8)
                ->get(['id', 'talle_id']);
            foreach ($talles8 as $t8) {
                $t12Id = (int) (DB::table('pedido_combinacion_talle')
                    ->where('pedido_combinacion_id', $pc12)
                    ->where('talle_id', (int) $t8->talle_id)
                    ->value('id') ?? 0);
                if ($t12Id > 0) {
                    $mapa[(int) $t8->id] = $t12Id;
                }
            }
        }

        return $mapa;
    }

    /**
     * Tras importar: remapea OCT que siguen apuntando a PCT ajenos al pedido
     * y elimina OCT huérfanas (id inexistente en L8 / PCT inexistente / duplicadas).
     *
     * @param  list<int>  $otCodigos
     * @param  array<int, int>  $mapaPcL8aL12
     * @return array{remapeados: int, eliminados: int, detalle: list<string>}
     */
    private function limpiarOctHuerfanasDePedido(int $pedidoId, array $otCodigos, array $mapaPcL8aL12): array
    {
        $out = ['remapeados' => 0, 'eliminados' => 0, 'detalle' => []];
        if ($otCodigos === [] || $mapaPcL8aL12 === []) {
            return $out;
        }

        $pcsPedido = array_values($mapaPcL8aL12);
        $mapaPct = $this->mapaPctL8aL12($mapaPcL8aL12);
        $vistos = [];

        try {
            $fuente = $this->reader->resolverFuente();
            $l8 = ($fuente['fuente'] === 'mysql_l8') ? $fuente['conexion'] : null;
        } catch (\Throwable) {
            $l8 = null;
        }

        foreach ($otCodigos as $ot) {
            $octs = DB::table('ordentrabajo_combinacion_talle')
                ->where('ordentrabajo_id', $ot)
                ->orderBy('id')
                ->get();
            foreach ($octs as $oct) {
                $pctId = (int) $oct->pedido_combinacion_talle_id;
                $pct = DB::table('pedido_combinacion_talle')->where('id', $pctId)->first();
                $pcId = $pct ? (int) $pct->pedido_combinacion_id : 0;
                $esDelPedido = $pcId > 0 && in_array($pcId, $pcsPedido, true);

                if ($esDelPedido) {
                    $clave = $ot.'|'.$pctId;
                    if (isset($vistos[$clave])) {
                        DB::table('ordentrabajo_combinacion_talle')->where('id', (int) $oct->id)->delete();
                        $out['eliminados']++;
                        continue;
                    }
                    $vistos[$clave] = true;
                    continue;
                }

                // Intentar remapear vía id L8 del mismo OCT o del pct.
                $pctL8Cand = $pctId;
                if ($l8) {
                    $oct8 = $l8->table('ordentrabajo_combinacion_talle')->where('id', (int) $oct->id)->first();
                    if ($oct8) {
                        $pctL8Cand = (int) $oct8->pedido_combinacion_talle_id;
                    }
                }
                $nuevo = $mapaPct[$pctL8Cand] ?? $mapaPct[$pctId] ?? 0;
                if ($nuevo <= 0 && $l8) {
                    $pct8 = $l8->table('pedido_combinacion_talle')->where('id', $pctL8Cand)->first();
                    if ($pct8 && isset($mapaPcL8aL12[(int) $pct8->pedido_combinacion_id])) {
                        $nuevo = (int) (DB::table('pedido_combinacion_talle')
                            ->where('pedido_combinacion_id', $mapaPcL8aL12[(int) $pct8->pedido_combinacion_id])
                            ->where('talle_id', (int) $pct8->talle_id)
                            ->value('id') ?? 0);
                    }
                }

                if ($nuevo > 0) {
                    $clave = $ot.'|'.$nuevo;
                    if (isset($vistos[$clave])) {
                        DB::table('ordentrabajo_combinacion_talle')->where('id', (int) $oct->id)->delete();
                        $out['eliminados']++;
                        continue;
                    }
                    $yaExistePar = (int) (DB::table('ordentrabajo_combinacion_talle')
                        ->where('ordentrabajo_id', $ot)
                        ->where('pedido_combinacion_talle_id', $nuevo)
                        ->where('id', '!=', (int) $oct->id)
                        ->value('id') ?? 0);
                    if ($yaExistePar > 0) {
                        // El par correcto ya está; esta fila es duplicado mal apuntado.
                        DB::table('ordentrabajo_combinacion_talle')->where('id', (int) $oct->id)->delete();
                        $vistos[$clave] = true;
                        $out['eliminados']++;
                        continue;
                    }
                    DB::table('ordentrabajo_combinacion_talle')->where('id', (int) $oct->id)->update([
                        'pedido_combinacion_talle_id' => $nuevo,
                        'updated_at' => now(),
                    ]);
                    $vistos[$clave] = true;
                    $out['remapeados']++;
                    continue;
                }

                // Sin mapa y PCT ajeno/inexistente: basura (p.ej. import previo con ids L8).
                DB::table('ordentrabajo_combinacion_talle')->where('id', (int) $oct->id)->delete();
                $out['eliminados']++;
            }
        }

        if ($out['remapeados'] > 0 || $out['eliminados'] > 0) {
            $out['detalle'][] = sprintf(
                'OCT: remapeados %d, eliminados huérfanos/duplicados %d (pedido %d).',
                $out['remapeados'],
                $out['eliminados'],
                $pedidoId
            );
        }

        return $out;
    }

    /**
     * @param  list<array<string, mixed>>  $tareas
     * @param  array<int, int>  $mapaPcL8aL12
     * @return list<array<string, mixed>>
     */
    private function remapearPedidoCombinacionId(array $tareas, array $mapaPcL8aL12): array
    {
        if ($mapaPcL8aL12 === []) {
            return $tareas;
        }
        foreach ($tareas as &$row) {
            $pc = (int) ($row['pedido_combinacion_id'] ?? 0);
            if ($pc > 0 && isset($mapaPcL8aL12[$pc])) {
                $row['pedido_combinacion_id'] = $mapaPcL8aL12[$pc];
            }
        }
        unset($row);

        return $tareas;
    }

    /**
     * Clave natural de tarea en OT compartida: OT + tarea + pedido_combinacion.
     * pc null/0 = tarea de cabecera OT (aplica a todos los pedidos ligados).
     */
    private function pedidoCombinacionKey(mixed $pc): int
    {
        return max(0, (int) ($pc ?? 0));
    }

    /**
     * @return object|null
     */
    private function buscarTareaPorClaveNatural(int $otId, int $tareaId, int $pcId)
    {
        $q = DB::table('ordentrabajo_tarea')
            ->where('ordentrabajo_id', $otId)
            ->where('tarea_id', $tareaId);
        if ($pcId > 0) {
            $q->where('pedido_combinacion_id', $pcId);
        } else {
            $q->where(function ($w) {
                $w->whereNull('pedido_combinacion_id')
                    ->orWhere('pedido_combinacion_id', 0);
            });
        }

        return $q->orderBy('id')->first();
    }

    /**
     * Tareas L8 que aún no existen en L12 para esa OT+tarea_id+pc
     * (aunque el id L8 esté ocupado o ya exista la misma tarea para otro pc de la OT).
     *
     * @param  list<array<string, mixed>>  $tareas
     * @return list<array<string, mixed>>
     */
    private function tareasQueFaltanPorClaveNatural(array $tareas): array
    {
        $faltan = [];
        foreach ($tareas as $row) {
            $otId = (int) ($row['ordentrabajo_id'] ?? 0);
            $tareaId = (int) ($row['tarea_id'] ?? 0);
            if ($otId <= 0 || $tareaId <= 0) {
                continue;
            }
            $pcId = $this->pedidoCombinacionKey($row['pedido_combinacion_id'] ?? 0);
            if (! $this->buscarTareaPorClaveNatural($otId, $tareaId, $pcId)) {
                $faltan[] = $row;
            }
        }

        return $faltan;
    }

    /**
     * @param  list<array<string, mixed>>  $tareas
     * @return array{0: int, 1: int}
     */
    private function upsertTareasConColision(array $tareas): array
    {
        if ($tareas === []) {
            return [0, 0];
        }
        $cols = Schema::getColumnListing('ordentrabajo_tarea');
        $ins = 0;
        $upd = 0;
        foreach ($tareas as $row) {
            $clean = FerliL8ImportRowSupport::normalize('ordentrabajo_tarea', $row, $cols);
            $id = (int) ($clean['id'] ?? 0);
            $otId = (int) ($clean['ordentrabajo_id'] ?? 0);
            $tareaId = (int) ($clean['tarea_id'] ?? 0);
            if ($otId <= 0 || $tareaId <= 0) {
                continue;
            }
            $pcId = $this->pedidoCombinacionKey($clean['pedido_combinacion_id'] ?? 0);

            // Misma OT + tarea + pc (OT compartida: un Empaque por pedido).
            $porClave = $this->buscarTareaPorClaveNatural($otId, $tareaId, $pcId);
            if ($porClave) {
                $data = $clean;
                unset($data['id'], $data['created_at']);
                // No pisar venta_id ya facturada en L12 si L8 viene sin ella.
                if (
                    empty($data['venta_id'])
                    && ! empty($porClave->venta_id)
                ) {
                    unset($data['venta_id']);
                }
                DB::table('ordentrabajo_tarea')->where('id', (int) $porClave->id)->update($data);
                $upd++;
                continue;
            }

            if ($id > 0) {
                $porId = DB::table('ordentrabajo_tarea')->where('id', $id)->first();
                if ($porId) {
                    $mismoOt = (int) ($porId->ordentrabajo_id ?? 0) === $otId;
                    $mismoPc = $this->pedidoCombinacionKey($porId->pedido_combinacion_id ?? 0) === $pcId;
                    $mismaTarea = (int) ($porId->tarea_id ?? 0) === $tareaId;
                    // Id L8 ya es exactamente esta fila → actualizar.
                    if ($mismoOt && $mismaTarea && $mismoPc) {
                        $data = $clean;
                        unset($data['id'], $data['created_at']);
                        if (
                            empty($data['venta_id'])
                            && ! empty($porId->venta_id)
                        ) {
                            unset($data['venta_id']);
                        }
                        DB::table('ordentrabajo_tarea')->where('id', $id)->update($data);
                        $upd++;
                        continue;
                    }
                    // Id ocupado por otra OT/tarea/pc → no pisar; insertar con id nuevo.
                    unset($clean['id']);
                }
            } else {
                unset($clean['id']);
            }

            if (! isset($clean['created_at']) && in_array('created_at', $cols, true)) {
                $clean['created_at'] = now();
            }
            if (in_array('updated_at', $cols, true)) {
                $clean['updated_at'] = now();
            }
            DB::table('ordentrabajo_tarea')->insert($clean);
            $ins++;
        }

        return [$ins, $upd];
    }

    /**
     * @param  list<array<string, mixed>>  $tareas
     */
    private function insertMissingTareasConColision(array $tareas): int
    {
        [$ins] = $this->upsertTareasConColision($tareas);

        return $ins;
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

                // OCT: al remapear PCT puede chocar UK (ot, pct) con otra fila.
                if ($table === 'ordentrabajo_combinacion_talle') {
                    $otId = (int) ($clean['ordentrabajo_id'] ?? 0);
                    $pctId = (int) ($clean['pedido_combinacion_talle_id'] ?? 0);
                    if ($otId > 0 && $pctId > 0) {
                        $otroId = (int) (DB::table($table)
                            ->where('ordentrabajo_id', $otId)
                            ->where('pedido_combinacion_talle_id', $pctId)
                            ->where('id', '!=', $id)
                            ->orderBy('id')
                            ->value('id') ?? 0);
                        if ($otroId > 0) {
                            DB::table($table)->where('id', $otroId)->update($data);
                            DB::table($table)->where('id', $id)->delete();
                            $upd++;
                            continue;
                        }
                    }
                }

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
