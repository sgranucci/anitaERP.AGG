<?php

namespace App\Support\Logistica;

use App\Models\Logistica\LogisticaUidHistorial;
use App\Models\Logistica\SolicitudLogistica;
use Illuminate\Support\Collection;

final class LogisticaUidSupport
{
    public static function registrar(SolicitudLogistica $solicitud, int $usuarioId): void
    {
        $uid = trim((string) $solicitud->uid_bien);
        if ($uid === '') {
            return;
        }
        $destino = $solicitud->ubicacionDestino->nombre ?? $solicitud->detalle;

        LogisticaUidHistorial::query()->create([
            'uid' => mb_substr($uid, 0, 40),
            'solicitud_logistica_id' => $solicitud->id,
            'fecha' => $solicitud->fecha ?? now()->toDateString(),
            'destino' => $destino !== null && $destino !== '' ? mb_substr((string) $destino, 0, 180) : null,
            'usuario_id' => $usuarioId,
        ]);
    }

    /**
     * @return Collection<int, LogisticaUidHistorial>
     */
    public static function historial(string $uid): Collection
    {
        $uid = trim($uid);
        if ($uid === '') {
            return collect();
        }

        return LogisticaUidHistorial::query()
            ->with(['solicitud:id,numero,fecha,estado,tipo_solicitud_id,trabajo_tipo_id', 'solicitud.tipo:id,codigo', 'solicitud.trabajoTipo:id,codigo', 'usuario:id,nombre'])
            ->where('uid', $uid)
            ->orderByDesc('fecha')
            ->orderByDesc('id')
            ->get();
    }
}
