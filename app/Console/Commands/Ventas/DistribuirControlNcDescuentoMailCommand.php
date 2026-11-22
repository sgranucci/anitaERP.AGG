<?php

namespace App\Console\Commands\Ventas;

use App\Services\Ventas\ControlNcDescuentoDistribucionService;
use Illuminate\Console\Command;

class DistribuirControlNcDescuentoMailCommand extends Command
{
    protected $signature = 'ventas:distribuir-control-nc-descuento-mail
                            {--fecha= : Fecha Y-m-d (default hoy)}
                            {--cc=* : Emails en copia (opcional)}
                            {--dry-run : Genera merge/PDF y lista destinatarios, sin enviar}
                            {--sin-mail : Alias de dry-run}';

    protected $description = 'El Bierzo: mail control NC descuento (merge ERP+Anita) — reemplazo de p-controlnc';

    public function handle(ControlNcDescuentoDistribucionService $service): int
    {
        $fecha = trim((string) ($this->option('fecha') ?? ''));
        $dryRun = (bool) $this->option('dry-run') || (bool) $this->option('sin-mail');
        $cc = array_values(array_filter(array_map(
            static fn ($e) => strtolower(trim((string) $e)),
            (array) $this->option('cc')
        )));

        $this->line(sprintf(
            'Control NC descuento mail%s%s',
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
                ['Cuadran ERP=Anita', ! empty($informe['cuadran']) ? 'SÍ' : 'NO'],
                ['Diffs monto', (string) ($informe['diffs'] ?? 0)],
                ['NCP Anita (>%4)', (string) ($tot['anita'] ?? 0)],
                ['NCP ERP día', (string) ($tot['erp'] ?? 0)],
                ['Sección lista 1/8 >4%', (string) ($tot['lista'] ?? 0)],
                ['Sección >8%', (string) ($tot['general'] ?? 0)],
                ['Monto lista', number_format((float) ($tot['monto_lista'] ?? 0), 2, ',', '.')],
                ['Monto general', number_format((float) ($tot['monto_general'] ?? 0), 2, ',', '.')],
                ['Anita OK', ! empty($informe['anita_ok']) ? 'SÍ' : ('NO: '.($informe['anita_error'] ?? ''))],
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
