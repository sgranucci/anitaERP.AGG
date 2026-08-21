<?php

declare(strict_types=1);

namespace App\Services\Caja;

use App\Mail\Caja\RendicionGastronomiaAuditoriaAnita as RendicionGastronomiaAuditoriaAnitaMail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Envío por correo de la auditoría rendgastro (rendicion-gastronomia:auditoria-anita).
 */
final class RendicionGastronomiaAuditoriaAnitaNotificacionService
{
    /**
     * @param  array<string, mixed>  $informe
     */
    public function debeEnviarMail(array $informe): bool
    {
        $config = config('rendicion_gastronomia_anita.auditoria_diaria', []);

        if (filter_var($config['email_si_ok'] ?? true, FILTER_VALIDATE_BOOLEAN)) {
            return true;
        }

        return (bool) ($informe['requiere_alerta'] ?? false);
    }

    /**
     * @param  array<string, mixed>  $informe
     * @return array{enviado: bool, destino?: string, error?: string}
     */
    public function enviarCorreo(array $informe): array
    {
        $config = config('rendicion_gastronomia_anita.auditoria_diaria', []);
        $destino = trim((string) ($config['email'] ?? ''));
        if ($destino === '') {
            return ['enviado' => false, 'error' => 'Sin destino de correo configurado (RENDICION_GASTRONOMIA_AUDITORIA_EMAIL)'];
        }

        if (! $this->debeEnviarMail($informe)) {
            return ['enviado' => false, 'error' => 'Sin alertas y email_si_ok deshabilitado'];
        }

        try {
            Mail::to($destino)->send(new RendicionGastronomiaAuditoriaAnitaMail($informe));
            Log::info('rendicion_gastronomia.auditoria_anita.mail_ok', [
                'destino' => $destino,
                'fecha_jornada' => $informe['fecha_jornada'] ?? null,
                'requiere_alerta' => $informe['requiere_alerta'] ?? null,
                'clasificacion_alerta' => $informe['clasificacion_alerta'] ?? null,
                'empresas' => array_map(
                    static fn (array $e) => (int) ($e['empresa_id'] ?? 0),
                    $informe['empresas'] ?? [],
                ),
            ]);

            return ['enviado' => true, 'destino' => $destino];
        } catch (\Throwable $e) {
            Log::error('rendicion_gastronomia.auditoria_anita.mail_fallo', [
                'destino' => $destino,
                'fecha_jornada' => $informe['fecha_jornada'] ?? null,
                'msg' => $e->getMessage(),
            ]);

            return ['enviado' => false, 'destino' => $destino, 'error' => $e->getMessage()];
        }
    }
}
