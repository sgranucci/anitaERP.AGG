<?php

namespace App\Support\Stock;

use App\Models\Seguridad\Usuario;
use App\Models\Stock\Deposito_Administrador;
use App\Models\Stock\Depmae;
use App\Models\Stock\Transferencia_Mercaderia;
use App\Support\Seguridad\UsuarioOperativoSupport;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Resuelve el usuario destino de aprobación de transferencias.
 *
 * Prioridad:
 * 1. Usuario elegido explícitamente en el formulario (si es válido).
 * 2. Administrador principal del depósito destino con aprueba_transferencia.
 * 3. Cualquier administrador del depósito con aprueba_transferencia.
 * 4. null → el handler de avisos usará destinatarios del módulo externo.
 */
final class TransferenciaMercaderiaDestinatarioSupport
{
    public static function resolverUsuarioDestino(int $depositoDestinoId, ?int $usuarioDestinoId = null): ?Usuario
    {
        if ($usuarioDestinoId > 0) {
            $usuario = UsuarioOperativoSupport::find($usuarioDestinoId);
            if (self::usuarioValidoDestinatarioExplicito($usuario)) {
                return $usuario;
            }
        }

        $admins = self::administradoresAprobacion($depositoDestinoId);
        if ($admins->isEmpty()) {
            return null;
        }

        $principal = $admins->firstWhere('principal', true);
        if ($principal !== null) {
            return $principal->usuarios;
        }

        return $admins->first()?->usuarios;
    }

    /** @return Collection<int, Deposito_Administrador> */
    public static function administradoresAprobacion(int $depositoDestinoId): Collection
    {
        if ($depositoDestinoId <= 0) {
            return collect();
        }

        return Deposito_Administrador::query()
            ->where('deposito_id', $depositoDestinoId)
            ->where('aprueba_transferencia', true)
            ->where('recibe_avisos', true)
            ->whereHas('usuarios', fn ($q) => $q->soloActivos())
            ->with('usuarios:id,nombre,email,usuario,suspendido')
            ->orderByDesc('principal')
            ->orderBy('id')
            ->get()
            ->filter(fn (Deposito_Administrador $row) => UsuarioOperativoSupport::esOperativo($row->usuarios) && ! empty($row->usuarios?->email));
    }

    /** @return list<array{id: int, nombre: string, email: string, principal: bool}> */
    public static function opcionesSelector(int $depositoDestinoId): array
    {
        if ($depositoDestinoId <= 0) {
            return [];
        }

        $opciones = [];
        $vistos = [];

        foreach (self::administradoresAprobacion($depositoDestinoId) as $admin) {
            $u = $admin->usuarios;
            if ($u === null || ! UsuarioOperativoSupport::esOperativo($u)) {
                continue;
            }
            $uid = (int) $u->id;
            if (isset($vistos[$uid])) {
                continue;
            }
            $vistos[$uid] = true;
            $opciones[] = [
                'id' => $uid,
                'nombre' => (string) $u->nombre,
                'email' => (string) $u->email,
                'principal' => (bool) $admin->principal,
            ];
        }

        $usuarioIds = DB::table('usuario_deposito')
            ->where('deposito_id', $depositoDestinoId)
            ->pluck('usuario_id');

        if ($usuarioIds->isNotEmpty()) {
            foreach (UsuarioOperativoSupport::query()
                ->whereIn('id', $usuarioIds)
                ->whereNotNull('email')
                ->where('email', '!=', '')
                ->orderBy('nombre')
                ->get(['id', 'nombre', 'email']) as $usuario) {
                $uid = (int) $usuario->id;
                if (isset($vistos[$uid]) || ! self::usuarioPuedeRecibirAprobacion($depositoDestinoId, $usuario)) {
                    continue;
                }
                $vistos[$uid] = true;
                $opciones[] = [
                    'id' => $uid,
                    'nombre' => (string) $usuario->nombre,
                    'email' => (string) $usuario->email,
                    'principal' => false,
                ];
            }
        }

        usort($opciones, static function (array $a, array $b): int {
            if ($a['principal'] !== $b['principal']) {
                return $a['principal'] ? -1 : 1;
            }

            return strcasecmp($a['nombre'], $b['nombre']);
        });

        return $opciones;
    }

    /** Usuario elegido en el formulario: activo + email; no exige depósito ni administrador. */
    public static function usuarioValidoDestinatarioExplicito(?Usuario $usuario): bool
    {
        return $usuario !== null
            && UsuarioOperativoSupport::esOperativo($usuario)
            && trim((string) $usuario->email) !== '';
    }

    public static function usuarioPuedeRecibirAprobacion(int $depositoDestinoId, Usuario $usuario): bool
    {
        if ($depositoDestinoId <= 0 || ! self::usuarioValidoDestinatarioExplicito($usuario)) {
            return false;
        }

        $esAdmin = Deposito_Administrador::query()
            ->where('deposito_id', $depositoDestinoId)
            ->where('usuario_id', $usuario->id)
            ->where('aprueba_transferencia', true)
            ->exists();

        if ($esAdmin) {
            return true;
        }

        return self::usuarioAutorizadoEnDeposito($depositoDestinoId, (int) $usuario->id);
    }

    public static function usuarioAutorizadoEnDeposito(int $depositoId, int $usuarioId): bool
    {
        if ($depositoId <= 0 || $usuarioId <= 0) {
            return false;
        }

        $asignado = DB::table('usuario_deposito')
            ->where('deposito_id', $depositoId)
            ->where('usuario_id', $usuarioId)
            ->exists();

        if ($asignado) {
            return true;
        }

        return DB::table('usuario_deposito')->where('usuario_id', $usuarioId)->doesntExist();
    }

    public static function resolverUsuarioDestinoBienUso(?int $usuarioDestinoId = null): ?Usuario
    {
        if ($usuarioDestinoId <= 0) {
            return null;
        }

        $usuario = UsuarioOperativoSupport::find($usuarioDestinoId);
        if ($usuario === null || trim((string) $usuario->email) === '') {
            return null;
        }

        return $usuario;
    }

    /** @return list<array{id: int, nombre: string, email: string, principal: bool}> */
    public static function opcionesSelectorBienUso(): array
    {
        $permisoId = DB::table('permiso')->where('slug', 'aprobar-transferencia-mercaderia')->value('id');
        if (! $permisoId) {
            return [];
        }

        $rolIds = DB::table('permiso_rol')->where('permiso_id', $permisoId)->pluck('rol_id');
        if ($rolIds->isEmpty()) {
            return [];
        }

        $usuarioIds = UsuarioOperativoSupport::filtrarIdsActivos(
            DB::table('usuario_rol')->whereIn('rol_id', $rolIds)->distinct()->pluck('usuario_id')->all()
        );

        if ($usuarioIds === []) {
            return [];
        }

        return UsuarioOperativoSupport::query()
            ->whereIn('id', $usuarioIds)
            ->whereNotNull('email')
            ->where('email', '!=', '')
            ->orderBy('nombre')
            ->get(['id', 'nombre', 'email'])
            ->map(fn (Usuario $u) => [
                'id' => (int) $u->id,
                'nombre' => (string) $u->nombre,
                'email' => (string) $u->email,
                'principal' => false,
            ])
            ->values()
            ->all();
    }

    public static function usuarioPuedeAprobarBienUso(Transferencia_Mercaderia $transferencia, Usuario $usuario): bool
    {
        if ((int) ($transferencia->usuario_destino_id ?? 0) === (int) $usuario->id) {
            return true;
        }

        if (function_exists('can') && can('aprobar-transferencia-mercaderia', false)) {
            return true;
        }

        return false;
    }
}
