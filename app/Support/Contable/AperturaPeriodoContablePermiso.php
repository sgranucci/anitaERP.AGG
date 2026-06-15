<?php

namespace App\Support\Contable;

use App\Models\Seguridad\Usuario;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;

class AperturaPeriodoContablePermiso
{
    public const SLUG_HABILITAR = 'habilitar-apertura-periodo-contable';

    public const SLUG_APROBAR = 'aprobar-apertura-periodo-contable';

    public static function puedeGestionarSolicitudes(): bool
    {
        return can(self::SLUG_APROBAR, false) || can(self::SLUG_HABILITAR, false);
    }

    public static function urlHabilitacionFirmada(int $aperturaId): string
    {
        $dias = max(1, (int) config('contable_cierre.apertura_link_habilitacion_dias', 7));

        return URL::temporarySignedRoute(
            'habilitar_apertura_periodo_contable_desde_aviso',
            now()->addDays($dias),
            ['id' => $aperturaId]
        );
    }

    /**
     * Emails de usuarios con permiso habilitar-apertura-periodo-contable (vía rol).
     *
     * @return list<string>
     */
    public static function emailsEncargadosHabilitacion(): array
    {
        $permisoId = (int) (DB::table('permiso')->where('slug', self::SLUG_HABILITAR)->value('id') ?? 0);
        if ($permisoId <= 0) {
            return [];
        }

        $rolIds = DB::table('permiso_rol')
            ->where('permiso_id', $permisoId)
            ->pluck('rol_id')
            ->unique()
            ->all();

        if ($rolIds === []) {
            return [];
        }

        $usuarioIds = DB::table('usuario_rol')
            ->whereIn('rol_id', $rolIds)
            ->pluck('usuario_id')
            ->unique()
            ->all();

        if ($usuarioIds === []) {
            return [];
        }

        $emails = [];
        foreach (Usuario::query()->whereIn('id', $usuarioIds)->get(['email']) as $usuario) {
            $email = strtolower(trim((string) ($usuario->email ?? '')));
            if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $emails[] = $email;
            }
        }

        return array_values(array_unique($emails));
    }
}
