<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Support\Queue\CorreoFallidoReintentoSupport;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Reencola correos que ya agotaron los reintentos del worker por un corte SMTP.
 * No reintenta otros jobs: varios tienen su propio ciclo o tries=1 a propósito.
 */
class ReintentarCorreosFallidosCommand extends Command
{
    protected $signature = 'queue:reintentar-correos-fallidos
                            {--dry-run : Lista los correos que reencolaría}
                            {--forzar : Reencola aunque el SMTP no responda el sondeo}';

    protected $description = 'Reencola correos en failed_jobs que fallaron por SMTP transitorio';

    public function handle(): int
    {
        if (! (bool) config('queue.reintento_correos.habilitado', true) && ! $this->option('dry-run') && ! $this->option('forzar')) {
            $this->info('Reintento de correos fallidos deshabilitado.');

            return self::SUCCESS;
        }

        $max = max(1, (int) config('queue.reintento_correos.max_reintentos', 24));
        $lote = max(1, (int) config('queue.reintento_correos.lote', 30));
        $filas = DB::table('failed_jobs')->orderBy('id')->limit($lote * 5)->get(['id', 'uuid', 'payload', 'exception']);

        $reencolar = [];
        $topes = 0;
        foreach ($filas as $fila) {
            $decision = CorreoFallidoReintentoSupport::decision((string) $fila->payload, (string) $fila->exception, $max);
            if ($decision === CorreoFallidoReintentoSupport::REENCOLAR) {
                $reencolar[] = $fila;
            } elseif ($decision === CorreoFallidoReintentoSupport::TOPE) {
                $topes++;
            }
            if (count($reencolar) >= $lote) {
                break;
            }
        }

        if ($reencolar === []) {
            $this->info($topes > 0
                ? "Sin correos para reencolar. {$topes} llegaron al tope de reintentos."
                : 'Sin correos fallidos por SMTP transitorio.');

            return self::SUCCESS;
        }

        if ($this->option('dry-run')) {
            foreach ($reencolar as $fila) {
                $this->line($fila->uuid);
            }
            $this->info(count($reencolar).' correo(s) se reencolarían.');

            return self::SUCCESS;
        }

        if (! $this->option('forzar') && ! $this->smtpResponde()) {
            $this->warn('El SMTP no responde. Los correos siguen en failed_jobs para la próxima corrida.');
            Log::warning('queue.reintento_correos.smtp_caido', ['pendientes' => count($reencolar)]);

            return self::SUCCESS;
        }

        $ok = 0;
        foreach ($reencolar as $fila) {
            $actualizado = CorreoFallidoReintentoSupport::payloadConReintento((string) $fila->payload);
            if ($actualizado === null) {
                $this->warn('Payload ilegible: '.$fila->uuid);

                continue;
            }

            DB::table('failed_jobs')->where('uuid', $fila->uuid)->update(['payload' => $actualizado['json']]);
            Artisan::call('queue:retry', ['id' => [$fila->uuid]]);
            $ok++;
            $this->line($fila->uuid.' reencolado ('.$actualizado['reintentos'].'/'.$max.')');
        }

        Log::info('queue.reintento_correos.reencolados', ['cantidad' => $ok, 'tope' => $topes]);
        $this->info("Reencolados: {$ok}.");

        return self::SUCCESS;
    }

    private function smtpResponde(): bool
    {
        $host = (string) config('mail.mailers.smtp.host', '');
        $port = (int) config('mail.mailers.smtp.port', 587);
        if ($host === '' || $port < 1) {
            return false;
        }

        $ip = gethostbyname($host);
        if ($ip === $host && filter_var($host, FILTER_VALIDATE_IP) === false) {
            return false;
        }

        $errno = 0;
        $error = '';
        $socket = @fsockopen($host, $port, $errno, $error, 8);
        if ($socket === false) {
            return false;
        }

        fclose($socket);

        return true;
    }
}
