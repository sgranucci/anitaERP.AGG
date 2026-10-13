<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Avisa si el certificado HTTPS se renovó, está por vencer o si Certbot falló.
 */
class AvisarCertificadoHttpsCommand extends Command
{
    protected $signature = 'https:avisar-certificado
                            {--probar : Envía el estado actual aunque no corresponda un aviso}
                            {--dry-run : Muestra el estado y no envía}';

    protected $description = 'Avisa por mail la renovación o el vencimiento del certificado HTTPS';

    public function handle(): int
    {
        if (! (bool) config('https_certificado.aviso_habilitado', false) && ! $this->option('probar')) {
            $this->info('Aviso de certificado HTTPS deshabilitado.');

            return self::SUCCESS;
        }

        $dominio = (string) config('https_certificado.dominio', 'anitaerp.elbierzo.com.ar');
        $cert = $this->leerCertificado($dominio);
        if ($cert === null) {
            $this->error('No se pudo leer el certificado de '.$dominio);

            return self::FAILURE;
        }

        $dias = (int) floor(($cert['vence']->getTimestamp() - now()->getTimestamp()) / 86400);
        $renovacionFallo = $dias <= (int) config('https_certificado.dias_ventana_renovacion', 30)
            && $this->renovacionAutomaticaFallo();
        $estado = $this->leerEstado();
        $notAfter = $cert['vence']->toIso8601String();
        $renovado = isset($estado['not_after']) && $estado['not_after'] !== '' && $estado['not_after'] !== $notAfter
            && $cert['vence']->getTimestamp() > Carbon::parse($estado['not_after'])->getTimestamp();

        $this->line(sprintf(
            '%s vence %s (%d días). Renovación automática: %s',
            $dominio,
            $cert['vence']->timezone(config('app.timezone'))->format('d/m/Y H:i'),
            $dias,
            $renovacionFallo ? 'falló' : 'sin falla reciente en la ventana'
        ));

        $motivo = $this->motivo($dias, $renovado, $renovacionFallo, (bool) $this->option('probar'));
        if ($motivo === null) {
            $this->guardarEstado($estado, $notAfter, null);
            $this->info('Sin aviso para hoy.');

            return self::SUCCESS;
        }

        $hoy = now()->toDateString();
        if (! $this->option('probar') && ($estado['ultimo_aviso_fecha'] ?? '') === $hoy && ($estado['ultimo_motivo'] ?? '') === $motivo) {
            $this->info('El aviso de hoy ya se envió.');

            return self::SUCCESS;
        }

        $destinos = array_values(array_filter(array_map(
            'trim',
            explode(',', (string) config('https_certificado.email', ''))
        )));
        if ($destinos === []) {
            $this->warn('Sin destinatario en HTTPS_CERT_AVISO_EMAIL.');

            return self::FAILURE;
        }

        $cuerpo = $this->cuerpo($dominio, $cert, $dias, $motivo);
        $asunto = $this->asunto($motivo, $dias);

        if ($this->option('dry-run')) {
            $this->warn('Dry-run: no se envía. Asunto: '.$asunto);
            $this->line($cuerpo);

            return self::SUCCESS;
        }

        try {
            Mail::raw($cuerpo, function ($message) use ($destinos, $asunto) {
                $message->to($destinos)->subject($asunto);
            });
        } catch (\Throwable $e) {
            Log::error('https:avisar-certificado mail falló', ['error' => $e->getMessage()]);
            $this->error('No se pudo enviar el mail: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->guardarEstado($estado, $notAfter, $motivo);
        $this->info('Mail enviado a '.implode(', ', $destinos));

        return self::SUCCESS;
    }

    /**
     * @return array{vence: Carbon, subject: string}|null
     */
    private function leerCertificado(string $dominio): ?array
    {
        $comando = 'echo | openssl s_client -connect 127.0.0.1:443 -servername '
            .escapeshellarg($dominio)
            .' 2>/dev/null | openssl x509 -noout -enddate -subject';
        $salida = shell_exec($comando);
        if (! is_string($salida) || ! preg_match('/notAfter=(.+)/', $salida, $fecha)) {
            return null;
        }

        $subject = '';
        if (preg_match('/subject=(.+)/', $salida, $subj)) {
            $subject = trim($subj[1]);
        }

        try {
            $vence = Carbon::parse(trim($fecha[1]));
        } catch (\Throwable $e) {
            return null;
        }

        return ['vence' => $vence, 'subject' => $subject];
    }

    private function renovacionAutomaticaFallo(): bool
    {
        $result = trim((string) shell_exec('systemctl show certbot.service -p Result --value 2>/dev/null'));

        return $result === 'exit-code';
    }

    private function motivo(int $dias, bool $renovado, bool $renovacionFallo, bool $probar): ?string
    {
        if ($probar) {
            return 'prueba';
        }
        if ($renovado) {
            return 'renovado';
        }
        if ($dias < 0) {
            return 'vencido';
        }
        if ($renovacionFallo) {
            return 'renovacion_fallo';
        }
        $umbral = (int) config('https_certificado.dias_aviso', 21);
        if ($dias <= $umbral) {
            return 'por_vencer';
        }

        return null;
    }

    /**
     * @param  array{vence: Carbon, subject: string}  $cert
     */
    private function cuerpo(string $dominio, array $cert, int $dias, string $motivo): string
    {
        $cuando = $cert['vence']->timezone(config('app.timezone'))->format('d/m/Y H:i');
        $estado = match ($motivo) {
            'renovado' => 'El certificado se renovó.',
            'vencido' => 'El certificado está vencido. El navegador vuelve a bloquear el ingreso por HTTPS.',
            'renovacion_fallo' => 'La renovación automática de Certbot falló. Si el puerto 80 público no está abierto hacia este servidor, va a vencer sin renovarse.',
            'prueba' => 'Prueba del aviso. El certificado vigente es el de abajo.',
            default => 'El certificado entra en la ventana de aviso.',
        };

        return implode("\n", [
            $estado,
            '',
            'Dominio: '.$dominio,
            'Vence: '.$cuando.' (hora Argentina)',
            'Días restantes: '.$dias,
            '',
            'Let\'s Encrypt solo valida el puerto 80 público de '.$dominio.'.',
            'El puerto 12280 no sirve para renovar: hay que dejar el 80 reenviado a 192.168.59.122.',
            'Cuando el 80 responde, en el servidor:',
            'sudo certbot renew --cert-name '.$dominio.' --no-random-sleep-on-renew && sudo systemctl reload apache2',
        ]);
    }

    private function asunto(string $motivo, int $dias): string
    {
        return match ($motivo) {
            'renovado' => 'AnitaERP: se renovó el certificado HTTPS',
            'vencido' => 'AnitaERP: el certificado HTTPS está vencido',
            'renovacion_fallo' => 'AnitaERP: falló la renovación del certificado HTTPS',
            'prueba' => 'AnitaERP: aviso de certificado HTTPS (prueba)',
            default => 'AnitaERP: el certificado HTTPS vence en '.$dias.' días',
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function leerEstado(): array
    {
        $path = $this->rutaEstado();
        if (! is_file($path)) {
            return [];
        }
        $json = json_decode((string) file_get_contents($path), true);

        return is_array($json) ? $json : [];
    }

    /**
     * @param  array<string, mixed>  $estado
     */
    private function guardarEstado(array $estado, string $notAfter, ?string $motivo): void
    {
        if ($this->option('dry-run')) {
            return;
        }
        $estado['not_after'] = $notAfter;
        if ($motivo !== null) {
            $estado['ultimo_aviso_fecha'] = now()->toDateString();
            $estado['ultimo_motivo'] = $motivo;
        }
        $path = $this->rutaEstado();
        file_put_contents($path, json_encode($estado, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)."\n");
        @chmod($path, 0664);
        if (function_exists('posix_getgrnam')) {
            $grupo = posix_getgrnam('www-data');
            if (is_array($grupo) && isset($grupo['gid'])) {
                @chgrp($path, (int) $grupo['gid']);
            }
        }
    }

    private function rutaEstado(): string
    {
        return storage_path('app/https-certificado-aviso.json');
    }
}
