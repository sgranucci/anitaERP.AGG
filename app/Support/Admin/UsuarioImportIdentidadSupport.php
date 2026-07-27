<?php

namespace App\Support\Admin;

use App\Models\Seguridad\Usuario;
use Illuminate\Support\Str;

/**
 * Generación de login y email para carga masiva de usuarios.
 * Regla: inicial del primer nombre + apellido (último token), sin tildes ni espacios.
 * Ej.: "Juan Pérez" → jperez / jperez@dominio
 */
final class UsuarioImportIdentidadSupport
{
    public static function dominioEmailDefault(): string
    {
        return self::normalizarDominio((string) config('usuario_import.dominio_email', '@grupoagg.com'));
    }

    public static function normalizarDominio(string $dominio): string
    {
        $dominio = trim($dominio);
        if ($dominio === '') {
            return self::dominioEmailDefault();
        }

        if (! str_starts_with($dominio, '@')) {
            $dominio = '@'.$dominio;
        }

        return mb_strtolower($dominio);
    }

    /**
     * Login base: inicial del primer nombre + apellido (último token).
     */
    public static function loginDesdeNombre(string $nombre): string
    {
        $nombre = trim(preg_replace('/\s+/', ' ', $nombre) ?? $nombre);
        if ($nombre === '') {
            return '';
        }

        $partes = preg_split('/\s+/', $nombre) ?: [];
        $partes = array_values(array_filter($partes, static fn ($p) => trim((string) $p) !== ''));

        if ($partes === []) {
            return '';
        }

        $ascii = static function (string $texto): string {
            $texto = Str::ascii($texto);
            $texto = preg_replace('/[^A-Za-z0-9]/', '', $texto) ?? '';

            return mb_strtolower($texto);
        };

        if (count($partes) === 1) {
            return $ascii($partes[0]);
        }

        $inicial = $ascii(mb_substr($partes[0], 0, 1));
        $apellido = $ascii($partes[count($partes) - 1]);

        return $inicial.$apellido;
    }

    public static function emailDesdeLogin(string $login, string $dominio): string
    {
        $login = mb_strtolower(trim($login));
        $dominio = self::normalizarDominio($dominio);

        if ($login === '' || $dominio === '@') {
            return '';
        }

        return $login.$dominio;
    }

    /**
     * Asegura un login libre respecto a BD y a los ya reservados en esta importación.
     *
     * @param  array<string, true>  $reservados  claves en minúsculas
     */
    public static function asegurarLoginUnico(string $loginBase, array &$reservados): string
    {
        $base = mb_strtolower(trim($loginBase));
        if ($base === '') {
            return '';
        }

        $candidato = $base;
        $n = 1;
        while (
            isset($reservados[$candidato])
            || Usuario::query()->where('usuario', $candidato)->exists()
        ) {
            $n++;
            $sufijo = (string) $n;
            $maxBase = max(1, 50 - mb_strlen($sufijo));
            $candidato = mb_substr($base, 0, $maxBase).$sufijo;
            if ($n > 9999) {
                return '';
            }
        }

        $reservados[$candidato] = true;

        return $candidato;
    }

    /**
     * @param  array<string, true>  $reservados  emails en minúsculas
     */
    public static function asegurarEmailUnico(string $emailBase, array &$reservados): string
    {
        $base = mb_strtolower(trim($emailBase));
        if ($base === '' || ! str_contains($base, '@')) {
            return '';
        }

        [$local, $dominio] = explode('@', $base, 2);
        $local = trim($local);
        $dominio = trim($dominio);
        if ($local === '' || $dominio === '') {
            return '';
        }

        $candidato = $local.'@'.$dominio;
        $n = 1;
        while (
            isset($reservados[$candidato])
            || Usuario::query()->where('email', $candidato)->exists()
        ) {
            $n++;
            $sufijo = (string) $n;
            $maxLocal = max(1, 64 - mb_strlen($sufijo));
            $candidato = mb_substr($local, 0, $maxLocal).$sufijo.'@'.$dominio;
            if ($n > 9999) {
                return '';
            }
        }

        $reservados[$candidato] = true;

        return $candidato;
    }

    /**
     * Resuelve login y email de una fila: usa Excel si viene; si no, genera desde el nombre.
     *
     * @param  array<string, true>  $loginsReservados
     * @param  array<string, true>  $emailsReservados
     * @return array{login: string, email: string, login_generado: bool, email_generado: bool, error: ?string}
     */
    public static function resolverIdentidadFila(
        string $nombre,
        string $loginExcel,
        string $emailExcel,
        string $dominio,
        bool $generarLoginSiFalta,
        bool $generarEmailSiFalta,
        array &$loginsReservados,
        array &$emailsReservados
    ): array {
        $nombre = trim($nombre);
        $loginExcel = trim($loginExcel);
        $emailExcel = UsuarioImportColumnasSupport::normalizarEmail($emailExcel);
        $dominio = self::normalizarDominio($dominio);

        $loginGenerado = false;
        $emailGenerado = false;

        if ($nombre === '') {
            return [
                'login' => '',
                'email' => '',
                'login_generado' => false,
                'email_generado' => false,
                'error' => 'Nombre vacío',
            ];
        }

        $login = $loginExcel;
        if ($login === '') {
            if (! $generarLoginSiFalta) {
                return [
                    'login' => '',
                    'email' => '',
                    'login_generado' => false,
                    'email_generado' => false,
                    'error' => 'Login vacío y generación automática desactivada',
                ];
            }
            $loginBase = self::loginDesdeNombre($nombre);
            if ($loginBase === '') {
                return [
                    'login' => '',
                    'email' => '',
                    'login_generado' => false,
                    'email_generado' => false,
                    'error' => 'No se pudo generar login desde el nombre',
                ];
            }
            $login = self::asegurarLoginUnico($loginBase, $loginsReservados);
            $loginGenerado = true;
            if ($login === '') {
                return [
                    'login' => '',
                    'email' => '',
                    'login_generado' => true,
                    'email_generado' => false,
                    'error' => 'No hay login libre derivado del nombre',
                ];
            }
        } else {
            $loginKey = mb_strtolower($login);
            if (isset($loginsReservados[$loginKey]) || Usuario::query()->where('usuario', $login)->exists()) {
                return [
                    'login' => $login,
                    'email' => $emailExcel,
                    'login_generado' => false,
                    'email_generado' => false,
                    'error' => isset($loginsReservados[$loginKey])
                        ? 'Login duplicado en el archivo'
                        : 'Ya existe un usuario con ese login',
                ];
            }
            $loginsReservados[$loginKey] = true;
        }

        $email = $emailExcel;
        if ($email === '') {
            if (! $generarEmailSiFalta) {
                return [
                    'login' => $login,
                    'email' => '',
                    'login_generado' => $loginGenerado,
                    'email_generado' => false,
                    'error' => 'Email vacío y generación automática desactivada',
                ];
            }
            $emailBase = self::emailDesdeLogin($login, $dominio);
            if ($emailBase === '') {
                return [
                    'login' => $login,
                    'email' => '',
                    'login_generado' => $loginGenerado,
                    'email_generado' => false,
                    'error' => 'No se pudo generar email (revise el dominio)',
                ];
            }
            $email = self::asegurarEmailUnico($emailBase, $emailsReservados);
            $emailGenerado = true;
            if ($email === '') {
                return [
                    'login' => $login,
                    'email' => '',
                    'login_generado' => $loginGenerado,
                    'email_generado' => true,
                    'error' => 'No hay email libre derivado del login',
                ];
            }
        } else {
            if (isset($emailsReservados[$email]) || Usuario::query()->where('email', $email)->exists()) {
                return [
                    'login' => $login,
                    'email' => $email,
                    'login_generado' => $loginGenerado,
                    'email_generado' => false,
                    'error' => isset($emailsReservados[$email])
                        ? 'Email duplicado en el archivo'
                        : 'Ya existe un usuario con ese email',
                ];
            }
            $emailsReservados[$email] = true;
        }

        return [
            'login' => $login,
            'email' => $email,
            'login_generado' => $loginGenerado,
            'email_generado' => $emailGenerado,
            'error' => null,
        ];
    }
}
