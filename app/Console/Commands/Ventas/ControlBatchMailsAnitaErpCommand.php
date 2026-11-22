<?php

namespace App\Console\Commands\Ventas;

use App\Services\Ventas\BatchMailsAnitaErpControlService;
use Illuminate\Console\Command;

class ControlBatchMailsAnitaErpCommand extends Command
{
    protected $signature = 'ventas:control-batch-mails-anita-erp
                            {--fecha= : Fecha Y-m-d (default hoy)}
                            {--dry-run : Corre el control sin enviar mail}
                            {--sin-mail : Alias de dry-run}';

    protected $description = 'El Bierzo: robot control Anita↔ERP de batch mails (kilos / NC / stkmov). Solo alerta interna.';

    public function handle(BatchMailsAnitaErpControlService $service): int
    {
        $fecha = trim((string) ($this->option('fecha') ?? ''));
        $dryRun = (bool) $this->option('dry-run') || (bool) $this->option('sin-mail');

        $this->line('Control batch mails Anita↔ERP'.($dryRun ? ' | MODO SIMULACIÓN' : ''));

        try {
            $informe = $service->ejecutar(
                $fecha !== '' ? $fecha : null,
                ! $dryRun,
                $dryRun,
            );
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        if (! empty($informe['omitido'])) {
            $this->warn((string) ($informe['motivo'] ?? 'Omitido.'));

            return self::SUCCESS;
        }

        $rows = [];
        foreach ($informe['bloques'] ?? [] as $b) {
            $rows[] = [
                (string) ($b['nombre'] ?? ''),
                ! empty($b['problema']) ? 'DIFF/ERROR' : 'OK',
                (string) ($b['diffs'] ?? 0),
                ! empty($b['anita_ok']) ? 'SÍ' : ('NO: '.($b['anita_error'] ?? '')),
            ];
        }
        $this->table(['Bloque', 'Estado', 'Diffs', 'Anita'], $rows);
        $this->line('Destinos control: '.(implode(', ', $informe['destinos'] ?? []) ?: '—'));

        if ($dryRun) {
            $this->warn('Dry-run: no se envió correo.');

            return self::SUCCESS;
        }

        if (! empty($informe['mail_enviado'])) {
            $this->info('Alerta enviada a '.$informe['mail_destino']);

            return self::SUCCESS;
        }

        if (! empty($informe['mail_error'])) {
            $this->error('Fallo al enviar: '.$informe['mail_error']);

            return self::FAILURE;
        }

        if (empty($informe['hay_problemas'])) {
            $this->info('Sin diferencias: no se envía mail.');
        }

        return self::SUCCESS;
    }
}
