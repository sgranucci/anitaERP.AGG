<?php

namespace App\Support\Caja;

use App\Models\Admin\Permiso;
use App\Models\Caja\CajeroCrown;
use App\Models\Caja\TesoreriaCrownComprobante;
use App\Models\Seguridad\Usuario;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;

final class TesoreriaCrownFirmaSupport
{
    public const PIN_MIN = 4;

    public const PIN_MAX = 8;

    private const INTENTOS_PIN = 5;

    private const INTENTOS_USUARIO = 5;

    public const MENSAJE_FIRMANTE = 'Usuario o clave incorrectos, o ese usuario no puede hacer esta firma.';

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
        $guardada = (string) ($cajero->clave ?? '');
        if ($ingresada === '' || $guardada === '') {
            return false;
        }

        return Hash::check($ingresada, $guardada);
    }

    /**
     * Sin PIN, o con el PIN inicial de la migración (código o legajo): no sirve para firmar.
     */
    public static function pinEsInicial(CajeroCrown $cajero): bool
    {
        $guardada = (string) ($cajero->clave ?? '');
        if ($guardada === '') {
            return true;
        }
        foreach (self::pinesProhibidos($cajero) as $prohibido) {
            if (Hash::check($prohibido, $guardada)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Valida el PIN del cajero para una firma. Si el PIN es el inicial (o el cajero no tiene),
     * exige elegir uno nuevo en la misma firma y lo graba para todos los cajeros del legajo.
     *
     * @return array{ok: bool, error: ?string, pedir_nuevo: bool}
     */
    public static function autenticarCajero(CajeroCrown $cajero, string $pin, ?string $nuevo = null, ?string $repetir = null): array
    {
        $llave = 'tc-pin-cajero:'.$cajero->id.'|'.request()->ip();
        if (RateLimiter::tooManyAttempts($llave, self::INTENTOS_PIN)) {
            return self::resultadoPin(false, 'Demasiados intentos con este cajero. Esperá '
                .max(1, (int) ceil(RateLimiter::availableIn($llave) / 60)).' minuto(s).');
        }
        $sinPin = trim((string) ($cajero->clave ?? '')) === '';
        if (! $sinPin && ! self::aceptaClaveCajero($cajero, $pin)) {
            RateLimiter::hit($llave, 300);

            return self::resultadoPin(false, 'PIN incorrecto.');
        }
        $nuevo = trim((string) $nuevo);
        if (! self::pinEsInicial($cajero) && $nuevo === '') {
            RateLimiter::clear($llave);

            return self::resultadoPin(true);
        }
        if ($nuevo === '') {
            return self::resultadoPin(false, $sinPin
                ? 'El cajero no tiene PIN: tiene que elegir uno nuevo para firmar.'
                : 'Es el PIN inicial (código o legajo): el cajero tiene que elegir uno nuevo para firmar.', true);
        }
        if ($error = self::errorPinNuevo($cajero, $nuevo, trim((string) $repetir))) {
            return self::resultadoPin(false, $error, true);
        }
        foreach (self::cajerosDelMismoLegajo($cajero) as $fila) {
            $fila->clave = $nuevo;
            $fila->save();
        }
        $cajero->refresh();
        RateLimiter::clear($llave);

        return self::resultadoPin(true);
    }

    /**
     * Blanquea el PIN de todos los cajeros del legajo: en la próxima firma elige uno nuevo.
     */
    public static function blanquearPin(CajeroCrown $cajero): void
    {
        foreach (self::cajerosDelMismoLegajo($cajero) as $fila) {
            $fila->forceFill(['clave' => null])->save();
        }
    }

    public static function errorPinNuevo(CajeroCrown $cajero, string $nuevo, string $repetir): ?string
    {
        if (preg_match('/^\d{'.self::PIN_MIN.','.self::PIN_MAX.'}$/', $nuevo) !== 1) {
            return 'El PIN nuevo va de '.self::PIN_MIN.' a '.self::PIN_MAX.' números.';
        }
        if ($nuevo !== $repetir) {
            return 'El PIN nuevo y su repetición no coinciden.';
        }
        if (in_array($nuevo, self::pinesProhibidos($cajero), true)) {
            return 'El PIN no puede ser el código ni el legajo del cajero.';
        }
        if (count(array_unique(str_split($nuevo))) === 1
            || str_contains('0123456789', $nuevo)
            || str_contains('9876543210', $nuevo)) {
            return 'El PIN no puede ser una serie (1111, 1234, 4321…).';
        }

        return null;
    }

    /** @return list<string> */
    private static function pinesProhibidos(CajeroCrown $cajero): array
    {
        $out = [];
        foreach ([$cajero->codigo, $cajero->legajo] as $valor) {
            $texto = trim((string) $valor);
            if ($texto !== '' && $texto !== '0') {
                $out[] = $texto;
            }
        }

        return array_values(array_unique($out));
    }

    /** @return array{ok: bool, error: ?string, pedir_nuevo: bool} */
    private static function resultadoPin(bool $ok, ?string $error = null, bool $pedirNuevo = false): array
    {
        return ['ok' => $ok, 'error' => $error, 'pedir_nuevo' => $pedirNuevo];
    }

    /**
     * Usuario y clave del ERP para una firma, con límite de intentos por usuario.
     * Un solo mensaje para clave errónea o falta de permiso: no revela cuál de las dos falló.
     *
     * @param  callable(Usuario): bool|null  $autoriza
     */
    public static function firmante(string $login, string $clave, ?callable $autoriza = null): Usuario|string
    {
        $login = trim($login);
        $llave = 'tc-firma-usuario:'.mb_strtolower($login);
        $llaves = [
            $llave.'|'.request()->ip() => self::INTENTOS_USUARIO,
            $llave => self::INTENTOS_USUARIO * 3,
        ];
        foreach ($llaves as $llave => $maximo) {
            if (RateLimiter::tooManyAttempts($llave, $maximo)) {
                return 'Demasiados intentos con ese usuario. Esperá '
                    .max(1, (int) ceil(RateLimiter::availableIn($llave) / 60)).' minuto(s).';
            }
        }
        $usuario = self::verificarClave($login, $clave);
        if ($usuario === null || ($autoriza !== null && ! $autoriza($usuario))) {
            foreach (array_keys($llaves) as $llave) {
                RateLimiter::hit($llave, 900);
            }

            return self::MENSAJE_FIRMANTE;
        }
        foreach (array_keys($llaves) as $llave) {
            RateLimiter::clear($llave);
        }

        return $usuario;
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
        DB::transaction(function () use ($comprobante) {
            if (! self::releerFirmasBloqueando($comprobante)) {
                return;
            }
            $lado = self::ladoCajero($comprobante->tipo);
            if ($lado === 'entrega' && $comprobante->entrega_at === null) {
                $comprobante->entrega_at = now();
            }
            if ($lado === 'recibe' && $comprobante->recibe_at === null) {
                $comprobante->recibe_at = now();
            }
            self::cerrarSiCorresponde($comprobante);
        });
    }

    /**
     * Dos firmas al mismo tiempo: cada una tiene que ver la otra antes de decidir si queda conforme.
     */
    private static function releerFirmasBloqueando(TesoreriaCrownComprobante $comprobante): bool
    {
        $fila = DB::table('tesoreria_crown_comprobante')
            ->where('id', $comprobante->id)
            ->lockForUpdate()
            ->first(['estado', 'entrega_usuario_id', 'entrega_at', 'recibe_usuario_id', 'recibe_at']);
        if ($fila === null) {
            return false;
        }
        $comprobante->forceFill((array) $fila)->syncOriginalAttributes(array_keys((array) $fila));

        return $comprobante->estado === TesoreriaCrownComprobanteSupport::PENDIENTE;
    }

    /**
     * Usuario y clave del sistema, con el mismo límite de intentos que firmante().
     */
    public static function usuarioPorClave(string $login, string $clave): ?Usuario
    {
        $usuario = self::firmante($login, $clave);

        return $usuario instanceof Usuario ? $usuario : null;
    }

    private static function verificarClave(string $login, string $clave): ?Usuario
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
        return self::tienePermiso($usuario, 'editar-tesoreria-crown-movimiento');
    }

    public static function puedeReabrirCaja(Usuario $usuario): bool
    {
        return self::tienePermiso($usuario, TesoreriaCrownCierreSupport::PERMISO_REABRIR);
    }

    public static function puedeAjustarCaja(Usuario $usuario): bool
    {
        return self::tienePermiso($usuario, TesoreriaCrownCierreSupport::PERMISO_AJUSTAR);
    }

    public static function puedeAuditarCaja(Usuario $usuario): bool
    {
        return self::tienePermiso($usuario, TesoreriaCrownCierreSupport::PERMISO_AUDITAR);
    }

    public static function tienePermiso(Usuario $usuario, string $slug): bool
    {
        $roles = $usuario->roles()->get(['rol.id', 'rol.nombre']);
        if ($roles->contains(fn ($rol) => (string) $rol->nombre === 'administrador')) {
            return true;
        }

        return Permiso::query()
            ->where('slug', $slug)
            ->whereHas('roles', fn ($q) => $q->whereIn('rol.id', $roles->pluck('id')))
            ->exists();
    }

    public static function aplicar(TesoreriaCrownComprobante $comprobante, string $lado, Usuario $usuario): void
    {
        DB::transaction(function () use ($comprobante, $lado, $usuario) {
            if (! self::releerFirmasBloqueando($comprobante)) {
                return;
            }
            if ($lado === 'entrega' && $comprobante->entrega_at === null) {
                $comprobante->entrega_usuario_id = $usuario->id;
                $comprobante->entrega_at = now();
            }
            if ($lado === 'recibe' && $comprobante->recibe_at === null) {
                $comprobante->recibe_usuario_id = $usuario->id;
                $comprobante->recibe_at = now();
            }
            self::cerrarSiCorresponde($comprobante);
        });
    }

    private static function cerrarSiCorresponde(TesoreriaCrownComprobante $comprobante): void
    {
        DB::transaction(function () use ($comprobante) {
            if ($comprobante->firmasCompletas()) {
                $comprobante->estado = TesoreriaCrownComprobanteSupport::CONFORME;
                $comprobante->qr_token = null;
                TesoreriaCrownComprobanteSupport::sellarCotizacionVigente($comprobante);
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
