<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Cierra requisiciones históricas importadas de Anita que quedaron en
 * EN ARBOL APROBACION sin movimientos reales del circuito ERP.
 *
 * Criterio:
 * - estado = EN ARBOL APROBACION
 * - fecha documento <= 2026-05-31 (tope mayo; el import Anita llega hasta 2026-04-30)
 * - sin filas en arbolaprobacion_movimiento
 *
 * No toca RQ con firmantes pendientes (desde 2026-06-29).
 */
return new class extends Migration
{
    private const ESTADO_ORIGEN = 'EN ARBOL APROBACION';

    private const ESTADO_DESTINO = 'CUMPLIDA';

    private const FECHA_TOPE = '2026-05-31';

    private const OBSERVACION = 'Cierre histórico Anita (migración 2026_08_06_161000): EN ARBOL sin movimientos de árbol, fecha<=2026-05-31';

    public function up(): void
    {
        $ids = DB::table('requisicion as r')
            ->where('r.estado', self::ESTADO_ORIGEN)
            ->whereDate('r.fecha', '<=', self::FECHA_TOPE)
            ->whereNotExists(function ($q) {
                $q->select(DB::raw(1))
                    ->from('arbolaprobacion_movimiento as m')
                    ->whereColumn('m.requisicion_id', 'r.id')
                    ->whereNull('m.deleted_at');
            })
            ->orderBy('r.id')
            ->pluck('r.id')
            ->map(fn ($id) => (int) $id)
            ->all();

        if ($ids === []) {
            return;
        }

        $ahora = now();
        $usuarioId = $this->usuarioSistemaId();

        DB::table('requisicion')
            ->whereIn('id', $ids)
            ->update([
                'estado' => self::ESTADO_DESTINO,
                'updated_at' => $ahora,
            ]);

        $filasHistoria = [];
        foreach ($ids as $id) {
            $filasHistoria[] = [
                'requisicion_id' => $id,
                'fecha' => $ahora,
                'estado' => self::ESTADO_DESTINO,
                'usuario_id' => $usuarioId,
                'observacion' => self::OBSERVACION,
                'created_at' => $ahora,
                'updated_at' => $ahora,
            ];
        }

        foreach (array_chunk($filasHistoria, 100) as $chunk) {
            DB::table('requisicion_estado')->insert($chunk);
        }
    }

    public function down(): void
    {
        $ids = DB::table('requisicion_estado')
            ->where('observacion', self::OBSERVACION)
            ->where('estado', self::ESTADO_DESTINO)
            ->pluck('requisicion_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        if ($ids === []) {
            return;
        }

        DB::table('requisicion')
            ->whereIn('id', $ids)
            ->where('estado', self::ESTADO_DESTINO)
            ->update([
                'estado' => self::ESTADO_ORIGEN,
                'updated_at' => now(),
            ]);

        DB::table('requisicion_estado')
            ->where('observacion', self::OBSERVACION)
            ->delete();
    }

    private function usuarioSistemaId(): int
    {
        $id = (int) (DB::table('usuario')->where('id', 1)->value('id') ?? 0);
        if ($id > 0) {
            return $id;
        }

        $id = (int) (DB::table('usuario')->orderBy('id')->value('id') ?? 0);
        if ($id <= 0) {
            throw new RuntimeException('No hay usuario para registrar historia de requisicion_estado.');
        }

        return $id;
    }
};
