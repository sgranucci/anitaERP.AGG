<?php

declare(strict_types=1);

namespace App\Support\Contable\ConciliacionBancaria;

use App\Models\Contable\ConciliacionBancariaExcepcion;
use App\Services\Contable\ConciliacionBancariaService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Schema;

final class ConciliacionBancariaExcepcionSupport
{
    /**
     * Sincroniza worklist desde aging del período (upsert por hash).
     *
     * @return array{creadas: int, actualizadas: int, total: int}
     */
    public static function sincronizarDesdeAging(
        ConciliacionBancariaService $service,
        int $empresaId,
        int $cuentacajaId,
        int $mes,
        int $anio,
    ): array {
        if (! Schema::hasTable('conciliacion_bancaria_excepcion')) {
            return ['creadas' => 0, 'actualizadas' => 0, 'total' => 0];
        }

        $aging = $service->agingPendientes($empresaId, $cuentacajaId, $mes, $anio);
        $creadas = 0;
        $actualizadas = 0;
        $vivos = [];

        foreach ($aging['filas'] ?? [] as $fila) {
            $hash = (string) ($fila['hash'] ?? '');
            $ladoRaw = strtolower((string) ($fila['lado'] ?? ''));
            $lado = match (true) {
                $ladoRaw === 'banco' || str_starts_with($ladoRaw, 'b') => 'B',
                $ladoRaw === 'contable' || str_starts_with($ladoRaw, 'c') => 'C',
                default => '',
            };
            if ($hash === '' || $lado === '') {
                continue;
            }
            $vivos[$lado.'|'.$hash] = true;

            $attrs = [
                'tipo' => (string) ($fila['tipo'] ?? 'pendiente'),
                'fecha' => self::normalizarFechaYmd($fila['fecha'] ?? null),
                'importe' => (float) ($fila['importe'] ?? 0),
                'dias' => (int) ($fila['dias'] ?? 0),
                'bucket' => (string) ($fila['bucket'] ?? ''),
                'detalle' => mb_substr((string) ($fila['detalle'] ?? $fila['descripcion'] ?? ''), 0, 255),
                'movimiento_json' => $fila,
            ];

            $exc = ConciliacionBancariaExcepcion::query()->firstOrNew([
                'cuentacaja_id' => $cuentacajaId,
                'lado' => $lado,
                'hash' => $hash,
                'mes' => $mes,
                'anio' => $anio,
            ]);

            if (! $exc->exists) {
                $exc->empresa_id = $empresaId;
                $exc->estado = ConciliacionBancariaExcepcion::ESTADO_ABIERTA;
                $exc->fill($attrs);
                $exc->save();
                $creadas++;
            } elseif (! in_array((string) $exc->estado, [
                ConciliacionBancariaExcepcion::ESTADO_RESUELTA,
                ConciliacionBancariaExcepcion::ESTADO_DESCARTADA,
            ], true)) {
                $exc->fill($attrs);
                $exc->save();
                $actualizadas++;
            }
        }

        // Marcar resueltas las que ya no están en aging (se conciliaron).
        ConciliacionBancariaExcepcion::query()
            ->where('empresa_id', $empresaId)
            ->where('cuentacaja_id', $cuentacajaId)
            ->where('mes', $mes)
            ->where('anio', $anio)
            ->whereIn('estado', [
                ConciliacionBancariaExcepcion::ESTADO_ABIERTA,
                ConciliacionBancariaExcepcion::ESTADO_EN_CURSO,
            ])
            ->get()
            ->each(function (ConciliacionBancariaExcepcion $exc) use ($vivos) {
                $key = $exc->lado.'|'.$exc->hash;
                if (! isset($vivos[$key])) {
                    $exc->estado = ConciliacionBancariaExcepcion::ESTADO_RESUELTA;
                    $exc->resolucion = 'conciliado';
                    $exc->resuelto_at = now();
                    $exc->save();
                }
            });

        $total = ConciliacionBancariaExcepcion::query()
            ->where('empresa_id', $empresaId)
            ->where('cuentacaja_id', $cuentacajaId)
            ->where('mes', $mes)
            ->where('anio', $anio)
            ->whereIn('estado', [
                ConciliacionBancariaExcepcion::ESTADO_ABIERTA,
                ConciliacionBancariaExcepcion::ESTADO_EN_CURSO,
            ])
            ->count();

        return compact('creadas', 'actualizadas', 'total');
    }

    /**
     * Aging muestra d/m/Y; el cast date del modelo exige Y-m-d.
     */
    public static function normalizarFechaYmd(mixed $fecha): ?string
    {
        if ($fecha === null || $fecha === '') {
            return null;
        }
        if ($fecha instanceof \DateTimeInterface) {
            return Carbon::instance($fecha)->toDateString();
        }

        $raw = trim((string) $fecha);
        if ($raw === '') {
            return null;
        }

        foreach (['Y-m-d', 'd/m/Y', 'd-m-Y', 'Ymd'] as $formato) {
            try {
                $dt = Carbon::createFromFormat($formato, $raw);

                return $dt->toDateString();
            } catch (\Throwable) {
                // probar siguiente
            }
        }

        try {
            return Carbon::parse($raw)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }
}
