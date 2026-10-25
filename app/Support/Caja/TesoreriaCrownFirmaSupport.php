<?php

namespace App\Support\Caja;

use App\Models\Admin\Permiso;
use App\Models\Caja\CajeroCrown;
use App\Models\Caja\TesoreriaCrownComprobante;
use App\Models\Seguridad\Usuario;
use Illuminate\Support\Facades\Hash;

final class TesoreriaCrownFirmaSupport
{
    public static function ladoCajero(string $tipo): string
    {
        return $tipo === TesoreriaCrownComprobanteSupport::RENDICION ? 'entrega' : 'recibe';
    }

    public static function ladoTesoreria(string $tipo): string
    {
        return $tipo === TesoreriaCrownComprobanteSupport::RENDICION ? 'recibe' : 'entrega';
    }

    public static function etiqueta(string $tipo, string $lado): string
    {
        $esHabilitacion = $tipo === TesoreriaCrownComprobanteSupport::HABILITACION;
        if ($lado === 'entrega') {
            return $esHabilitacion ? 'Tesorería entrega' : 'Cajero rinde';
        }

        return $esHabilitacion ? 'Cajero recibe' : 'Tesorería recibe';
    }

    public static function publicarToken(TesoreriaCrownComprobante $comprobante): void
    {
        $comprobante->qr_token = bin2hex(random_bytes(32));
        $comprobante->estado = TesoreriaCrownComprobanteSupport::PENDIENTE;
        $comprobante->entrega_usuario_id = null;
        $comprobante->entrega_at = null;
        $comprobante->recibe_usuario_id = null;
        $comprobante->recibe_at = null;
        $comprobante->save();
    }

    public static function volverABorrador(TesoreriaCrownComprobante $comprobante): void
    {
        $comprobante->estado = TesoreriaCrownComprobanteSupport::BORRADOR;
        $comprobante->qr_token = null;
        $comprobante->entrega_usuario_id = null;
        $comprobante->entrega_at = null;
        $comprobante->recibe_usuario_id = null;
        $comprobante->recibe_at = null;
        $comprobante->save();
    }

    public static function usuarioPorClave(string $login, string $clave): ?Usuario
    {
        $login = trim($login);
        if ($login === '' || $clave === '') {
            return null;
        }
        $usuario = Usuario::query()->where('usuario', $login)->first();
        if ($usuario === null || $usuario->estaSuspendido()) {
            return null;
        }
        if (! Hash::check($clave, (string) $usuario->password)) {
            return null;
        }

        return $usuario;
    }

    public static function puedeFirmarTesoreria(Usuario $usuario): bool
    {
        $roles = $usuario->roles()->get(['rol.id', 'rol.nombre']);
        if ($roles->contains(fn ($rol) => (string) $rol->nombre === 'administrador')) {
            return true;
        }

        return Permiso::query()
            ->where('slug', 'editar-tesoreria-crown-movimiento')
            ->whereHas('roles', fn ($q) => $q->whereIn('rol.id', $roles->pluck('id')))
            ->exists();
    }

    public static function aplicar(TesoreriaCrownComprobante $comprobante, string $lado, Usuario $usuario): void
    {
        if ($lado === 'entrega' && $comprobante->entrega_at === null) {
            $comprobante->entrega_usuario_id = $usuario->id;
            $comprobante->entrega_at = now();
        }
        if ($lado === 'recibe' && $comprobante->recibe_at === null) {
            $comprobante->recibe_usuario_id = $usuario->id;
            $comprobante->recibe_at = now();
        }
        if ($comprobante->firmasCompletas()) {
            $comprobante->estado = TesoreriaCrownComprobanteSupport::CONFORME;
            $comprobante->qr_token = null;
        }
        $comprobante->save();
    }

    public static function textoFirma(?Usuario $usuario, mixed $cuando): string
    {
        if ($cuando === null) {
            return 'Sin firma';
        }
        $nombre = trim((string) ($usuario->nombre ?? ''));
        $login = trim((string) ($usuario->usuario ?? ''));
        $quien = $nombre !== '' ? $nombre.($login !== '' ? ' ('.$login.')' : '') : $login;

        return trim($quien.' · '.$cuando->format('d/m/Y H:i'));
    }

    /**
     * @return \Illuminate\Support\Collection<int, CajeroCrown>
     */
    public static function cajerosDelMismoLegajo(CajeroCrown $cajero)
    {
        $query = CajeroCrown::query()->where('sala_id', $cajero->sala_id);
        $legajo = (int) $cajero->legajo;
        if ($legajo > 0) {
            $query->where('legajo', $legajo);
        } else {
            $query->where('id', $cajero->id);
        }

        return $query->get();
    }
}
