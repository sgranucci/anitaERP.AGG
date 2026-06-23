<?php

namespace App\Console\Commands;

use App\ApiAnita;
use App\Services\Stock\RecepcionProveedorAsientoAuditoriaDiariaService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class RecepcionProveedorAuditoriaAsientosComCommand extends Command
{
    protected $signature = 'recepcion-proveedor:auditoria-asientos-com
                            {--fecha= : Fecha calendario Y-m-d (default: ayer)}
                            {--empresa= : Filtrar por empresa_id}
                            {--sin-mail : No envía correo aunque haya discrepancias}';

    protected $description = 'Audita asientos contables ERP ↔ ctamov Anita de recepciones COM confirmadas';

    public function handle(RecepcionProveedorAsientoAuditoriaDiariaService $service): int
    {
        if (! config('recepcion_proveedor.auditoria_asientos_com_diaria.habilitada', true)) {
            $this->warn('Auditoría deshabilitada (recepcion_proveedor.auditoria_asientos_com_diaria.habilitada).');

            return self::SUCCESS;
        }

        $fechaOpt = trim((string) ($this->option('fecha') ?? ''));
        $fecha = $fechaOpt !== '' ? $fechaOpt : Carbon::yesterday()->toDateString();
        $empresaOverride = $this->option('empresa') !== null ? (int) $this->option('empresa') : null;
        $enviarMail = ! (bool) $this->option('sin-mail');

        $this->line('Bridge: '.ApiAnita::urlBridge());
        $this->line(sprintf(
            'Recepciones COM confirmadas con fecha %s%s%s',
            $fecha,
            $empresaOverride ? ' · empresa '.$empresaOverride : '',
            $enviarMail ? '' : ' · sin mail',
        ));

        try {
            $informe = $service->ejecutar($fecha, $enviarMail, $empresaOverride);
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->table(
            ['Concepto', 'Cantidad'],
            [
                ['COM auditadas', (string) ($informe['total_com'] ?? 0)],
                ['OK', (string) ($informe['ok'] ?? 0)],
                ['Omitidas (sin contabilidad)', (string) ($informe['omitidas'] ?? 0)],
                ['Con discrepancia', (string) count($informe['discrepancias'] ?? [])],
                ['Errores de lectura', (string) count($informe['errores_lectura'] ?? [])],
            ],
        );

        foreach ($informe['discrepancias'] ?? [] as $fila) {
            $this->newLine();
            $this->warn(sprintf(
                'COM %d (id %d, empresa %d, asiento %s)',
                (int) ($fila['com'] ?? 0),
                (int) ($fila['recepcion_id'] ?? 0),
                (int) ($fila['empresa_id'] ?? 0),
                (string) ($fila['numero_asiento'] ?? '—'),
            ));
            foreach ($fila['problemas'] ?? [] as $problema) {
                $this->line('  · '.$problema);
            }
        }

        if (! empty($informe['mail_enviado'])) {
            $this->info('Correo enviado a '.$informe['mail_destino']);
        } elseif (! empty($informe['mail_error'])) {
            $this->error('Fallo al enviar correo: '.$informe['mail_error']);
        }

        return ($informe['requiere_alerta'] ?? false) ? self::FAILURE : self::SUCCESS;
    }
}
