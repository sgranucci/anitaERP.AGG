<?php

namespace App\Console\Commands\Crm;

use App\Services\Crm\SuitecrmNotaAuditoriaSemanalService;
use Illuminate\Console\Command;

class SuitecrmNotaAuditoriaSemanalCommand extends Command
{
    protected $signature = 'suitecrm:auditoria-notas-semanal
                            {--desde= : Desde Y-m-d inclusive (default: ventana config)}
                            {--hasta= : Hasta Y-m-d inclusive (default: hoy)}
                            {--dry-run : Genera el PDF y lista destinatarios, sin enviar mail}
                            {--sin-mail : Alias de dry-run}';

    protected $description = 'Envía por mail el PDF de auditoría de notas CRM de los últimos 7 días (Interforming, viernes 08:00)';

    public function handle(SuitecrmNotaAuditoriaSemanalService $service): int
    {
        $desde = trim((string) ($this->option('desde') ?? ''));
        $hasta = trim((string) ($this->option('hasta') ?? ''));
        $dryRun = (bool) $this->option('dry-run') || (bool) $this->option('sin-mail');

        $this->line(sprintf(
            'Auditoría semanal notas CRM%s',
            $dryRun ? ' | MODO SIMULACIÓN (sin mail)' : '',
        ));

        try {
            $informe = $service->ejecutar(
                $desde !== '' ? $desde : null,
                $hasta !== '' ? $hasta : null,
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

        $this->table(
            ['Concepto', 'Valor'],
            [
                ['Desde', (string) ($informe['fecha_desde'] ?? '')],
                ['Hasta', (string) ($informe['fecha_hasta'] ?? '')],
                ['Notas', (string) ($informe['total'] ?? 0)],
                ['PDF', ! empty($informe['pdf_generado']) ? 'OK' : 'NO'],
                ['Destinos', implode(', ', $informe['destinos'] ?? []) ?: '—'],
            ],
        );

        if ($dryRun) {
            $this->warn('Dry-run: no se envió correo.');

            return self::SUCCESS;
        }

        if (! empty($informe['mail_enviado'])) {
            $this->info('Correo enviado a '.$informe['mail_destino']);

            return self::SUCCESS;
        }

        if (! empty($informe['mail_error'])) {
            $this->error('Fallo al enviar correo: '.$informe['mail_error']);

            return self::FAILURE;
        }

        $this->comment('Sin envío de correo.');

        return self::SUCCESS;
    }
}
