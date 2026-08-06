<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Completa historia de requisicion_estado del cierre histórico Anita
 * (la 161000 actualizó cabeceras pero falló el insert por usuario_id NOT NULL).
 */
return new class extends Migration
{
    private const ESTADO_DESTINO = 'CUMPLIDA';

    private const FECHA_TOPE = '2026-05-31';

    private const OBSERVACION = 'Cierre histórico Anita (migración 2026_08_06_161000): EN ARBOL sin movimientos de árbol, fecha<=2026-05-31';

    public function up(): void
    {
        $ids = DB::table('requisicion as r')
            ->where('r.estado', self::ESTADO_DESTINO)
            ->whereDate('r.fecha', '<=', self::FECHA_TOPE)
            ->where('r.updated_at', '>=', '2026-08-06 13:00:00')
            ->whereNotExists(function ($q) {
                $q->select(DB::raw(1))
                    ->from('arbolaprobacion_movimiento as m')
                    ->whereColumn('m.requisicion_id', 'r.id')
                    ->whereNull('m.deleted_at');
            })
            ->whereNotExists(function ($q) {
                $q->select(DB::raw(1))
                    ->from('requisicion_estado as re')
                    ->whereColumn('re.requisicion_id', 'r.id')
                    ->where('re.observacion', self::OBSERVACION);
            })
            ->orderBy('r.id')
            ->pluck('r.id')
            ->map(fn ($id) => (int) $id)
            ->all();

        if ($ids === []) {
            return;
        }

        $usuarioId = (int) (DB::table('usuario')->where('id', 1)->value('id')
            ?? DB::table('usuario')->orderBy('id')->value('id')
            ?? 0);
        if ($usuarioId <= 0) {
            throw new RuntimeException('No hay usuario para registrar historia de requisicion_estado.');
        }

        $ahora = now();
        $filas = [];
        foreach ($ids as $id) {
            $filas[] = [
                'requisicion_id' => $id,
                'fecha' => $ahora,
                'estado' => self::ESTADO_DESTINO,
                'usuario_id' => $usuarioId,
                'observacion' => self::OBSERVACION,
                'created_at' => $ahora,
                'updated_at' => $ahora,
            ];
        }

        foreach (array_chunk($filas, 100) as $chunk) {
            DB::table('requisicion_estado')->insert($chunk);
        }
    }

    public function down(): void
    {
        // La historia se revierte junto con 161000; no borrar acá para no duplicar lógica.
    }
};
