<?php

namespace App\Console\Commands\Compras;

use App\Services\Compras\ProveedorCuentacorrienteConciliacionDiariaService;
use Illuminate\Console\Command;

class ConciliarCuentacorrienteProveedorCommand extends Command
{
    protected $signature = 'compras:conciliar-cc-proveedor
                            {--desde= : Desde Y-m-d para auditar aplicaciones (default: ventana config)}
                            {--sin-mail : No envía correo}';

    protected $description = 'Concilia ficha CC proveedor ↔ deuda ↔ mayor AP y alerta descalces multi-moneda';

    public function handle(ProveedorCuentacorrienteConciliacionDiariaService $service): int
    {
        if (! config('compras.conciliacion_cc_proveedor.habilitada', true)) {
            $this->warn('Conciliación deshabilitada (compras.conciliacion_cc_proveedor.habilitada).');

            return self::SUCCESS;
        }

        $desde = trim((string) ($this->option('desde') ?? ''));
        $enviarMail = ! (bool) $this->option('sin-mail');

        $this->line(sprintf(
            'Conciliación CC proveedores · aplicaciones desde %s%s',
            $desde !== '' ? $desde : 'ventana config',
            $enviarMail ? '' : ' · sin mail'
        ));

        try {
            $informe = $service->ejecutar($desde !== '' ? $desde : null, $enviarMail);
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $rows = [];
        foreach ($informe['resumen'] ?? [] as $k => $v) {
            $rows[] = [$k, (string) $v];
        }
        $this->table(['Control', 'Alertas'], $rows);

        foreach ($informe['alertas'] ?? [] as $alerta) {
            $this->warn(($alerta['tipo'] ?? 'alerta').': '.($alerta['detalle'] ?? ''));
        }

        if (! empty($informe['mail_enviado'])) {
            $this->info('Correo enviado a '.$informe['mail_destino']);
        } elseif (! empty($informe['mail_error'])) {
            $this->error('Fallo al enviar correo: '.$informe['mail_error']);
        }

        return ($informe['requiere_alerta'] ?? false) ? self::FAILURE : self::SUCCESS;
    }
}
