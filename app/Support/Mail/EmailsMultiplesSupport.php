<?php

namespace App\Support\Mail;

/**
 * Emails de contacto de maestros (proveedor, cliente, etc.): uno o varios
 * separados por coma, punto y coma o espacio. No aplica a login/usuario.
 */
class EmailsMultiplesSupport
{
    public const PLACEHOLDER = 'uno o varios, separados por ; o ,';

    public const TITLE = 'Puede ingresar varios correos separados por punto y coma (;) o coma (,)';

    /**
     * @return list<string>
     */
    public static function parse(string $raw): array
    {
        $partes = preg_split('/[\s,;]+/', trim($raw)) ?: [];
        $emails = [];
        foreach ($partes as $p) {
            $p = strtolower(trim($p));
            if ($p !== '' && filter_var($p, FILTER_VALIDATE_EMAIL)) {
                $emails[] = $p;
            }
        }

        return array_values(array_unique($emails));
    }

    /**
     * Tokens no vacíos que no pasan validación de email.
     *
     * @return list<string>
     */
    public static function invalidos(string $raw): array
    {
        $partes = preg_split('/[\s,;]+/', trim($raw)) ?: [];
        $invalidos = [];
        foreach ($partes as $p) {
            $p = trim($p);
            if ($p !== '' && ! filter_var($p, FILTER_VALIDATE_EMAIL)) {
                $invalidos[] = $p;
            }
        }

        return array_values(array_unique($invalidos));
    }

    public static function esValido(?string $raw): bool
    {
        if ($raw === null || trim($raw) === '') {
            return true;
        }

        return self::invalidos($raw) === [];
    }
}
