<?php

declare(strict_types=1);

namespace App\Support\Compras;

use App\Mail\Compras\ProveedorAltaAviso;
use App\Models\Compras\Proveedor;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Aviso de alta de proveedor (Anita a-promae.c envia_mail_alta).
 * Destinatarios: config('proveedor.emailavisoalta') vía PROVEEDOR_EMAIL_AVISO_ALTA.
 */
final class ProveedorAltaMailSupport
{
    public static function habilitado(): bool
    {
        return (bool) config('proveedor.enviamailalta', false)
            && self::destinatarios() !== [];
    }

    /**
     * @return list<string>
     */
    public static function destinatarios(): array
    {
        $raw = config('proveedor.emailavisoalta', []);
        if (! is_array($raw)) {
            return [];
        }

        $out = [];
        foreach ($raw as $email) {
            $email = strtolower(trim((string) $email));
            if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $out[$email] = $email;
            }
        }

        return array_values($out);
    }

    public static function enviarSiCorresponde(?Proveedor $proveedor): bool
    {
        if (! $proveedor || ! self::habilitado()) {
            return false;
        }

        $destinos = self::destinatarios();
        $usuario = Auth::user();
        $payload = [
            'codigo' => (string) ($proveedor->codigo ?? ''),
            'nombre' => (string) ($proveedor->nombre ?? ''),
            'cuit' => (string) ($proveedor->nroinscripcion ?? ''),
            'usuario' => (string) ($usuario->nombre ?? $usuario->usuario ?? Auth::id() ?? ''),
        ];

        try {
            Mail::to($destinos)->send(new ProveedorAltaAviso($payload));

            return true;
        } catch (Throwable $e) {
            Log::error('proveedor.alta_aviso.mail_fallo', [
                'proveedor_id' => (int) $proveedor->id,
                'destinos' => $destinos,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }
}
