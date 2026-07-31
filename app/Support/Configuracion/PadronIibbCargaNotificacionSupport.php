<?php

declare(strict_types=1);

namespace App\Support\Configuracion;

use App\Mail\Configuracion\PadronIibbCargaResultadoMail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

final class PadronIibbCargaNotificacionSupport
{
    /**
     * @param  array<string, mixed>  $stats
     */
    public static function notificar(
        bool $ok,
        string $origen,
        string $mensaje,
        string $archivo = '',
        array $stats = [],
        ?string $error = null
    ): void {
        $destinos = self::destinatarios();
        if ($destinos === []) {
            return;
        }

        $payload = [
            'ok' => $ok,
            'origen' => $origen,
            'archivo' => $archivo,
            'mensaje' => $mensaje,
            'stats' => $stats,
            'error' => $error,
        ];

        try {
            Mail::to($destinos)->send(new PadronIibbCargaResultadoMail($payload));
            Log::info('padron_iibb:mail_enviado', [
                'ok' => $ok,
                'origen' => $origen,
                'destinos' => $destinos,
            ]);
        } catch (Throwable $e) {
            Log::error('padron_iibb:mail_error', [
                'origen' => $origen,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /** @return list<string> */
    public static function destinatarios(): array
    {
        $raw = (string) config('padrones_iibb.notificar_email', '');
        if (trim($raw) === '') {
            return [];
        }

        $out = [];
        foreach (preg_split('/[,;]+/', $raw) ?: [] as $email) {
            $email = trim($email);
            if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $out[] = $email;
            }
        }

        return array_values(array_unique($out));
    }
}
