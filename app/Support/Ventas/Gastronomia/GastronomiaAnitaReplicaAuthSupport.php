<?php

namespace App\Support\Ventas\Gastronomia;

use App\Models\Seguridad\Usuario;
use App\Models\Ventas\Venta;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * Sesión mínima para replica Anita fuera del request HTTP (cola o terminating).
 */
final class GastronomiaAnitaReplicaAuthSupport
{
    /**
     * @param  array<string, mixed>|null  $anitaPendiente
     */
    public static function autenticarSiNecesario(
        int $ventaId,
        ?array $anitaPendiente = null,
        string $contexto = 'factura',
    ): void {
        if (Auth::check()) {
            return;
        }

        $usuarioId = (int) (Venta::query()->whereKey($ventaId)->value('usuario_id') ?? 0);
        if ($usuarioId <= 0 && is_array($anitaPendiente)) {
            $ventaPendiente = $anitaPendiente['venta'] ?? null;
            if (is_array($ventaPendiente)) {
                $usuarioId = (int) ($ventaPendiente['usuario_id'] ?? 0);
            }
        }
        if ($usuarioId <= 0) {
            $usuarioId = (int) (config('gastronomia.auditoria_anita_diaria.usuario_id', 0));
        }
        if ($usuarioId <= 0) {
            $usuarioId = (int) (Usuario::query()->orderBy('id')->value('id') ?? 0);
        }

        if ($usuarioId <= 0 || ! Auth::loginUsingId($usuarioId)) {
            Log::warning('gastronomia.anita.replica.sin_usuario', [
                'venta_id' => $ventaId,
                'contexto' => $contexto,
                'usuario_id_intentado' => $usuarioId,
            ]);

            return;
        }

        Log::debug('gastronomia.anita.replica.usuario_autenticado', [
            'venta_id' => $ventaId,
            'contexto' => $contexto,
            'usuario_id' => $usuarioId,
        ]);
    }
}
