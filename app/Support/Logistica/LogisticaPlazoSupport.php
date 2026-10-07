<?php

namespace App\Support\Logistica;

use App\Models\Logistica\LogisticaSla;
use App\Models\Logistica\SolicitudLogistica;
use Illuminate\Support\Carbon;

final class LogisticaPlazoSupport
{
    /** @var array<string, array{preparacion: int, entrega: int}> */
    private const DEFECTO = [
        'Urgente' => ['preparacion' => 4, 'entrega' => 8],
        'Alta' => ['preparacion' => 8, 'entrega' => 24],
        'Media' => ['preparacion' => 24, 'entrega' => 48],
        'Normal' => ['preparacion' => 24, 'entrega' => 72],
        'Baja' => ['preparacion' => 72, 'entrega' => 120],
    ];

    public static function aplicar(SolicitudLogistica $solicitud): void
    {
        $horas = self::horas((string) $solicitud->prioridad);
        if ($solicitud->fecha_tentativa !== null) {
            $solicitud->fecha_compromiso = Carbon::parse($solicitud->fecha_tentativa)->endOfDay();
        } else {
            $solicitud->fecha_compromiso = now()->addHours($horas['entrega']);
        }
        $solicitud->save();
    }

    /**
     * @return array{preparacion: int, entrega: int}
     */
    public static function horas(string $prioridad): array
    {
        $fila = LogisticaSla::query()->where('prioridad', $prioridad)->first();
        if ($fila !== null) {
            return [
                'preparacion' => max(1, (int) $fila->horas_preparacion),
                'entrega' => max(1, (int) $fila->horas_entrega),
            ];
        }

        return self::DEFECTO[$prioridad] ?? self::DEFECTO['Normal'];
    }

    public static function vencida(object $solicitud): bool
    {
        $estado = (string) ($solicitud->estado ?? '');
        if (in_array($estado, ['entregada', 'cerrada', 'rechazada'], true)) {
            return false;
        }
        $compromiso = $solicitud->fecha_compromiso ?? null;
        if ($compromiso === null || $compromiso === '') {
            return false;
        }

        return Carbon::parse($compromiso)->lt(now());
    }
}
