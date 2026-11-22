<?php

namespace App\Console\Commands\Stock;

use App\Services\Stock\StkmovIntMailDistribucionService;
use Illuminate\Console\Command;

class DistribuirStkmovIntMailCommand extends Command
{
    protected $signature = 'stock:distribuir-stkmov-int-mail
                            {--fecha= : Fecha Y-m-d (default hoy)}
                            {--cc=* : Emails en copia (opcional)}
                            {--dry-run : Genera merge/PDF y lista destinatarios, sin enviar}
                            {--sin-mail : Alias de dry-run}';

    protected $description = 'El Bierzo: mail stkmov interno G1–G6 (Villafranca+Bierzo) — reemplazo de l-stkmovint -b';

    public function handle(StkmovIntMailDistribucionService $service): int
    {
        $fecha = trim((string) ($this->option('fecha') ?? ''));
        $dryRun = (bool) $this->option('dry-run') || (bool) $this->option('sin-mail');
        $cc = array_values(array_filter(array_map(
            static fn ($e) => strtolower(trim((string) $e)),
            (array) $this->option('cc')
        )));

        $this->line(sprintf(
            'Stkmov int mail%s%s',
            $dryRun ? ' | MODO SIMULACIÓN (sin mail)' : '',
            $cc !== [] ? ' | cc='.implode(',', $cc) : '',
        ));

        try {
            $informe = $service->ejecutar(
                $fecha !== '' ? $fecha : null,
                ! $dryRun,
                $dryRun,
                $cc,
            );
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        if (! empty($informe['omitido'])) {
            $this->warn((string) ($informe['motivo'] ?? 'Omitido.'));

            return self::SUCCESS;
        }

        $tot = $informe['totales'] ?? [];
        $this->table(
            ['Concepto', 'Valor'],
            [
                ['Fecha', (string) ($informe['fecha'] ?? '')],
                ['Filas merge', (string) ($tot['filas'] ?? 0)],
                ['Saldo total', number_format((float) ($tot['saldo'] ?? 0), 2, ',', '.')],
                ['Unidades', number_format((float) ($tot['unidades'] ?? 0), 0, ',', '.')],
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
