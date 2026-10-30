<?php

namespace App\Support\Caja;

use App\Models\Admin\Permiso;
use App\Models\Caja\CajeroCrown;
use App\Models\Caja\TesoreriaCrownComprobante;
use App\Models\Seguridad\Usuario;
use Illuminate\Support\Facades\DB;
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

    public static function pasarAPendiente(TesoreriaCrownComprobante $comprobante): void
    {
        $comprobante->qr_token = null;
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

    /**
     * El número es el código de cajero. Si ese código no existe y el legajo es único, vale el legajo.
     * Tiene que ser la persona asignada a la caja.
     */
    public static function cajeroPorNumero(CajeroCrown $asignado, string $numero): CajeroCrown|string
    {
        $numero = (int) preg_replace('/\D/', '', $numero);
        if ($numero <= 0) {
            return 'Ingresá el número de cajero.';
        }
        $base = CajeroCrown::query()
            ->where('sala_id', $asignado->sala_id)
            ->where('activo', true);
        $candidato = (clone $base)->where('codigo', $numero)->first();
        if ($candidato === null) {
            $porLegajo = (clone $base)->where('legajo', $numero)->orderBy('codigo')->get();
            if ($porLegajo->count() > 1) {
                return 'Hay varios cajeros con ese legajo. Ingresá el código de cajero.';
            }
            $candidato = $porLegajo->first();
        }
        if ($candidato === null || ! self::esLaPersonaAsignada($asignado, $candidato)) {
            return 'Esta firma le corresponde a '.$asignado->nombre.'.';
        }

        return $candidato;
    }

    public static function aceptaClaveCajero(CajeroCrown $cajero, string $ingresada): bool
    {
        $ingresada = trim($ingresada);
        if ($ingresada === '') {
            return false;
        }
        $guardada = (string) ($cajero->clave ?? '');
        if ($guardada !== '') {
            return Hash::check($ingresada, $guardada);
        }

        return hash_equals((string) $cajero->codigo, $ingresada);
    }

    private static function esLaPersonaAsignada(CajeroCrown $asignado, CajeroCrown $candidato): bool
    {
        if ((int) $asignado->id === (int) $candidato->id) {
            return true;
        }
        $legajo = (int) $asignado->legajo;

        return $legajo > 0 && $legajo === (int) $candidato->legajo;
    }

    public static function aplicarCajero(TesoreriaCrownComprobante $comprobante): void
    {
        $lado = self::ladoCajero($comprobante->tipo);
        if ($lado === 'entrega' && $comprobante->entrega_at === null) {
            $comprobante->entrega_at = now();
        }
        if ($lado === 'recibe' && $comprobante->recibe_at === null) {
            $comprobante->recibe_at = now();
        }
        self::cerrarSiCorresponde($comprobante);
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

    public static function puedeReabrirCaja(Usuario $usuario): bool
    {
        $roles = $usuario->roles()->get(['rol.id', 'rol.nombre']);
        if ($roles->contains(fn ($rol) => (string) $rol->nombre === 'administrador')) {
            return true;
        }

        return Permiso::query()
            ->where('slug', TesoreriaCrownCierreSupport::PERMISO_REABRIR)
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
        self::cerrarSiCorresponde($comprobante);
    }

    private static function cerrarSiCorresponde(TesoreriaCrownComprobante $comprobante): void
    {
        DB::transaction(function () use ($comprobante) {
            if ($comprobante->firmasCompletas()) {
                $comprobante->estado = TesoreriaCrownComprobanteSupport::CONFORME;
                $comprobante->qr_token = null;
            }
            $comprobante->save();
            if ($comprobante->estado === TesoreriaCrownComprobanteSupport::CONFORME) {
                TesoreriaCrownBovedaSupport::asentarCajero($comprobante);
            }
        });
    }

    public static function textoFirma(?Usuario $usuario, mixed $cuando, ?string $nombreSiNoHayUsuario = null): string
    {
        if ($cuando === null) {
            return 'Sin firma';
        }
        if ($usuario === null) {
            $quien = trim((string) $nombreSiNoHayUsuario);

            return trim(($quien !== '' ? $quien : 'Cajero').' · '.$cuando->format('d/m/Y H:i'));
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
