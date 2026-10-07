<?php

namespace App\Support\Logistica;

use App\Mail\Configuracion\ModuloAvisoMail;
use App\Models\Logistica\SolicitudLogistica;
use App\Models\Seguridad\Usuario;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

final class LogisticaAvisoSupport
{
    public static function alta(SolicitudLogistica $solicitud): void
    {
        $solicitud->loadMissing(['usuario:id,nombre,email', 'trabajoTipo:id,nombre,email,responsable']);
        $numero = $solicitud->numeroVisible();
        $link = route('ver_logistica_solicitud', $solicitud->id);
        self::enviar(
            (string) ($solicitud->usuario->email ?? ''),
            'Solicitud '.$numero,
            'Quedó registrada la solicitud '.$numero.' en estado '.$solicitud->etiquetaEstado().'.',
            $link
        );
        $emailResponsable = (string) ($solicitud->trabajoTipo->email ?? '');
        if ($solicitud->trabajo_tipo_id !== null && $emailResponsable !== '') {
            self::enviar(
                $emailResponsable,
                'Trabajo asignado '.$numero,
                'Se te asignó la solicitud '.$numero.' ('.($solicitud->trabajoTipo->nombre ?? 'trabajo').').',
                $link
            );
        }
    }

    public static function cambio(SolicitudLogistica $solicitud): void
    {
        $solicitud->loadMissing(['usuario:id,nombre,email']);
        $numero = $solicitud->numeroVisible();
        self::enviar(
            (string) ($solicitud->usuario->email ?? ''),
            'Solicitud '.$numero.' · '.$solicitud->etiquetaEstado(),
            'La solicitud '.$numero.' pasó a '.$solicitud->etiquetaEstado().'.',
            route('ver_logistica_solicitud', $solicitud->id)
        );
    }

    private static function enviar(string $email, string $asunto, string $cuerpo, string $link): void
    {
        $email = trim($email);
        if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return;
        }
        if (Usuario::query()->where('email', $email)->where('suspendido', true)->exists()
            && ! Usuario::query()->where('email', $email)->where('suspendido', false)->exists()) {
            return;
        }

        try {
            Mail::to($email)->send(new ModuloAvisoMail($asunto, $cuerpo, $asunto, $link));
        } catch (\Throwable $e) {
            Log::warning('Logística no pudo enviar el aviso a '.$email.': '.$e->getMessage());
        }
    }
}
