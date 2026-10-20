<?php

namespace App\Console\Commands\Compras;

use App\Services\Compras\ComprobanteProveedorAuditorFarDiferenciaService;
use Illuminate\Console\Command;

class ComprobanteProveedorAuditorFarDiferenciaCommand extends Command
{
    protected $signature = 'comprobante-proveedor:auditar-far-diferencia
                            {--desde= : Desde Y-m-d (default: 1º del mes)}
                            {--hasta= : Hasta Y-m-d (default: hoy)}
                            {--sin-mail : No envía correo}';

    protected $description = 'Audita (solo ERP) FC vs COM con diferencia imputada a FAR en vez de cuentas OC';

    public function handle(ComprobanteProveedorAuditorFarDiferenciaService $service): int
    {
        if (! config('comprobante_proveedor_auditoria_erp.far_diferencia.habilitada', true)) {
            $this->warn('Auditoría deshabilitada (comprobante_proveedor_auditoria_erp.far_diferencia.habilitada).');

            return self::SUCCESS;
        }

        $desde = trim((string) ($this->option('desde') ?? ''));
        $hasta = trim((string) ($this->option('hasta') ?? ''));
        $enviarMail = ! (bool) $this->option('sin-mail');

        $this->line(sprintf(
            'FAR vs OC · %s → %s%s',
            $desde !== '' ? $desde : 'mes actual',
            $hasta !== '' ? $hasta : 'hoy',
            $enviarMail ? '' : ' · sin mail',
        ));

        try {
            $informe = $service->ejecutar(
                $desde !== '' ? $desde : null,
                $hasta !== '' ? $hasta : null,
                $enviarMail,
            );
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->table(
            ['Concepto', 'Cantidad'],
            [
                ['Auditadas', (string) ($informe['total'] ?? 0)],
                ['OK', (string) ($informe['ok'] ?? 0)],
                ['Desvíos', (string) count($informe['desvios'] ?? [])],
                ['Errores', (string) count($informe['errores'] ?? [])],
            ],
        );

        foreach (array_slice($informe['desvios'] ?? [], 0, 25) as $fila) {
            $this->warn(sprintf(
                '%s | FAR asiento %s vs esperado %s | %s',
                (string) ($fila['etiqueta'] ?? '?'),
                number_format((float) ($fila['far_asiento'] ?? 0), 2, ',', '.'),
                number_format((float) ($fila['far_esperado'] ?? 0), 2, ',', '.'),
                (string) ($fila['motivo'] ?? ''),
            ));
        }

        if (! empty($informe['mail_enviado'])) {
            $this->info('Correo enviado a '.$informe['mail_destino']);
        } elseif (! empty($informe['mail_error'])) {
            $this->error('Mail: '.$informe['mail_error']);
        }

        return ($informe['requiere_alerta'] ?? false) ? self::FAILURE : self::SUCCESS;
    }
}
