<?php

namespace App\Console\Commands;

use App\Mail\Queue\ColaPicoAlerta;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\Process\Process;

class VerificarColaPico extends Command
{
    protected $signature = 'queue:verificar-pico
                            {--force : Ejecutar aunque esté fuera del horario pico configurado}
                            {--sin-mail : No envía correo aunque haya alertas}';

    protected $description = 'Verifica worker/cola Laravel en hora pico; alerta por mail si hay problemas';

    public function handle(): int
    {
        if (! config('queue.verificacion_pico.habilitada', true)) {
            $this->comment('Verificación cola pico deshabilitada (queue.verificacion_pico.habilitada).');

            return self::SUCCESS;
        }

        if (! $this->option('force') && ! $this->enHorarioPico()) {
            return self::SUCCESS;
        }

        $script = base_path('deploy/queue/verificar-pico.sh');
        if (! is_file($script)) {
            $this->error('No existe deploy/queue/verificar-pico.sh');

            return self::FAILURE;
        }

        $process = new Process([$script, '--json'], base_path(), null, null, 120);
        $process->run();

        $output = trim($process->getOutput());
        $informe = json_decode($output, true);
        if (! is_array($informe)) {
            $this->error('Salida inválida del script de verificación.');
            Log::warning('queue:verificar-pico — JSON inválido', ['output' => $output]);

            return self::FAILURE;
        }

        $exitCode = (int) ($informe['exit_code'] ?? $process->getExitCode());
        $this->registrarLog($informe, $exitCode);

        if ($exitCode === 0) {
            $this->info('Cola OK — '.$informe['timestamp']);

            if (config('queue.verificacion_pico.email_si_ok', false) && ! $this->option('sin-mail')) {
                $this->enviarCorreo($informe);
            }

            return self::SUCCESS;
        }

        $this->warn('Alerta cola — status '.($informe['status'] ?? '?').' (exit '.$exitCode.')');

        if (! $this->option('sin-mail')) {
            $this->enviarCorreo($informe, $exitCode);
        }

        return $exitCode === 1 ? self::FAILURE : self::INVALID;
    }

    private function enHorarioPico(): bool
    {
        $hora = (int) now()->format('G');
        $desde = (int) config('queue.verificacion_pico.hora_desde', 12);
        $hasta = (int) config('queue.verificacion_pico.hora_hasta', 1);

        if ($desde <= $hasta) {
            return $hora >= $desde && $hora <= $hasta;
        }

        // Ventana que cruza medianoche (ej. 18 → 1)
        return $hora >= $desde || $hora <= $hasta;
    }

    /**
     * @param  array<string, mixed>  $informe
     */
    private function registrarLog(array $informe, int $exitCode): void
    {
        $issuesWarn = $informe['issues_warn'] ?? [];
        $issuesCrit = $informe['issues_critical'] ?? [];
        $issuesExtra = '';
        if ($issuesCrit !== []) {
            $issuesExtra = ' critical='.implode(' | ', array_map('strval', $issuesCrit));
        } elseif ($issuesWarn !== []) {
            $issuesExtra = ' warn='.implode(' | ', array_map('strval', $issuesWarn));
        }

        $linea = sprintf(
            '[%s] status=%s exit=%d workers=%d pending=%d failed_24h=%d%s',
            $informe['timestamp'] ?? now()->toDateTimeString(),
            $informe['status'] ?? '?',
            $exitCode,
            (int) ($informe['worker_count'] ?? 0),
            (int) ($informe['jobs']['pending'] ?? 0),
            (int) ($informe['failed_jobs_24h'] ?? 0),
            $issuesExtra,
        );

        $logPath = storage_path('logs/queue-verificar-pico.log');
        @file_put_contents($logPath, $linea.PHP_EOL, FILE_APPEND | LOCK_EX);

        if ($exitCode !== 0) {
            Log::warning('queue:verificar-pico — '.$linea, [
                'issues_critical' => $informe['issues_critical'] ?? [],
                'issues_warn' => $informe['issues_warn'] ?? [],
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $informe
     */
    private function enviarCorreo(array $informe, int $exitCode = 0): void
    {
        $email = trim((string) config('queue.verificacion_pico.email', ''));
        if ($email === '') {
            $this->warn('Sin destinatario (QUEUE_VERIFICACION_PICO_EMAIL).');

            return;
        }

        $throttleMin = max(0, (int) config('queue.verificacion_pico.email_throttle_minutos', 15));
        $cacheKey = 'queue_verificacion_pico_mail:'.($informe['status'] ?? 'unknown');

        if ($throttleMin > 0 && $exitCode !== 0 && Cache::has($cacheKey)) {
            $this->comment('Correo omitido (throttle '.$throttleMin.' min, status '.($informe['status'] ?? '?').').');

            return;
        }

        try {
            Mail::to($email)->send(new ColaPicoAlerta($informe));
            $this->info('Correo enviado a '.$email);

            if ($throttleMin > 0 && $exitCode !== 0) {
                Cache::put($cacheKey, true, now()->addMinutes($throttleMin));
            }
        } catch (\Throwable $e) {
            $this->error('Fallo al enviar correo: '.$e->getMessage());
            Log::error('queue:verificar-pico — mail fallo', ['error' => $e->getMessage()]);
        }
    }
}
