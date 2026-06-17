<?php

namespace App\Support\Stock;

use App\Models\Seguridad\Usuario;
use App\Models\Stock\Deposito_Administrador;
use App\Models\Stock\Depmae;
use Illuminate\Support\Collection;

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
            $usuario = Usuario::query()->whereKey($usuarioDestinoId)->first();
            if ($usuario !== null && self::usuarioPuedeRecibirAprobacion($depositoDestinoId, $usuario)) {
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
            ->with('usuarios:id,nombre,email,usuario')
            ->orderByDesc('principal')
            ->orderBy('id')
            ->get()
            ->filter(fn (Deposito_Administrador $row) => ! empty($row->usuarios?->email));
    }

    /** @return list<array{id: int, nombre: string, email: string, principal: bool}> */
    public static function opcionesSelector(int $depositoDestinoId): array
    {
        $opciones = [];
        foreach (self::administradoresAprobacion($depositoDestinoId) as $admin) {
            $u = $admin->usuarios;
            if ($u === null) {
                continue;
            }
            $opciones[] = [
                'id' => (int) $u->id,
                'nombre' => (string) $u->nombre,
                'email' => (string) $u->email,
                'principal' => (bool) $admin->principal,
            ];
        }

        return $opciones;
    }

    public static function usuarioPuedeRecibirAprobacion(int $depositoDestinoId, Usuario $usuario): bool
    {
        if ($depositoDestinoId <= 0 || trim((string) $usuario->email) === '') {
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

        return Depmae::autorizadoParaUsuarioYEmpresa(
            $depositoDestinoId,
            (int) (Depmae::query()->whereKey($depositoDestinoId)->value('empresa_id') ?? 0)
        ) && UsuarioDepositoAutorizado::depositoAutorizado($depositoDestinoId);
    }
}
