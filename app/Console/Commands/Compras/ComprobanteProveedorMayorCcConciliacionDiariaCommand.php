<?php

namespace App\Console\Commands\Compras;

use App\Services\Compras\ComprobanteProveedorMayorCcConciliacionDiariaService;
use Illuminate\Console\Command;

class ComprobanteProveedorMayorCcConciliacionDiariaCommand extends Command
{
    protected $signature = 'comprobante-proveedor:conciliar-mayor-cc
                            {--desde= : Desde Y-m-d inclusive (default: ventana config)}
                            {--hasta= : Hasta Y-m-d inclusive (default: hoy)}
                            {--sin-mail : No envía correo}';

    protected $description = 'Compara mayor Anita proveedores MN/ME (subdiario+ctamov) vs CC ERP (solo facturas) y notifica';

    public function handle(ComprobanteProveedorMayorCcConciliacionDiariaService $service): int
    {
        if (! config('comprobante_proveedor_anita.conciliacion_mayor_cc.habilitada', true)) {
            $this->warn('Conciliación deshabilitada (comprobante_proveedor_anita.conciliacion_mayor_cc.habilitada).');

            return self::SUCCESS;
        }

        $desde = trim((string) ($this->option('desde') ?? ''));
        $hasta = trim((string) ($this->option('hasta') ?? ''));
        $enviarMail = ! (bool) $this->option('sin-mail');

        $this->line(sprintf(
            'Mayor Anita AP vs CC facturas · %s → %s%s',
            $desde !== '' ? $desde : 'ventana',
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
            ['Concepto', 'MN', 'ME'],
            [
                [
                    'Mayor Anita (neto Haber)',
                    number_format((float) ($informe['mayor_mn'] ?? 0), 2, ',', '.'),
                    number_format((float) ($informe['mayor_me'] ?? 0), 2, ',', '.'),
                ],
                [
                    'CC facturas ERP ($)',
                    number_format((float) ($informe['cc_mn'] ?? 0), 2, ',', '.'),
                    number_format((float) ($informe['cc_me'] ?? 0), 2, ',', '.'),
                ],
                [
                    'Diferencia (mayor − CC)',
                    number_format((float) ($informe['diferencia_mn'] ?? 0), 2, ',', '.'),
                    number_format((float) ($informe['diferencia_me'] ?? 0), 2, ',', '.'),
                ],
                [
                    'Movimientos / facturas',
                    (string) (($informe['movimientos_mayor_mn'] ?? 0).' / '.($informe['facturas_cc_mn'] ?? 0)),
                    (string) (($informe['movimientos_mayor_me'] ?? 0).' / '.($informe['facturas_cc_me'] ?? 0)),
                ],
            ],
        );

        if ((float) ($informe['cc_me_moneda_origen'] ?? 0) != 0.0) {
            $this->line('CC ME en moneda origen: '.number_format((float) $informe['cc_me_moneda_origen'], 2, ',', '.'));
        }

        foreach ($informe['errores'] ?? [] as $error) {
            $this->error((string) $error);
        }

        if (! empty($informe['mail_enviado'])) {
            $this->info('Correo enviado a '.$informe['mail_destino']);
        } elseif (! empty($informe['mail_error'])) {
            $this->error('Fallo al enviar correo: '.$informe['mail_error']);
        }

        return ($informe['requiere_alerta'] ?? false) ? self::FAILURE : self::SUCCESS;
    }
}
