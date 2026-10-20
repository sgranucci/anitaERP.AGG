<?php

namespace App\Console\Commands\Compras;

use App\Services\Compras\ComprobanteProveedorAuditorAnticipoBsUsoCapexService;
use Illuminate\Console\Command;

class ComprobanteProveedorAuditorAnticipoBsUsoCapexCommand extends Command
{
    protected $signature = 'comprobante-proveedor:auditar-anticipo-bsuso-capex
                            {--desde= : Desde Y-m-d (default: 1º del mes)}
                            {--hasta= : Hasta Y-m-d (default: hoy)}
                            {--sin-mail : No envía correo}';

    protected $description = 'Audita (solo ERP) imputaciones a anticipo BS.USO sin Capex en la OC';

    public function handle(ComprobanteProveedorAuditorAnticipoBsUsoCapexService $service): int
    {
        if (! config('comprobante_proveedor_auditoria_erp.anticipo_bsuso_sin_capex.habilitada', true)) {
            $this->warn('Auditoría deshabilitada (comprobante_proveedor_auditoria_erp.anticipo_bsuso_sin_capex.habilitada).');

            return self::SUCCESS;
        }

        $desde = trim((string) ($this->option('desde') ?? ''));
        $hasta = trim((string) ($this->option('hasta') ?? ''));
        $enviarMail = ! (bool) $this->option('sin-mail');

        $this->line(sprintf(
            'Anticipo BS.USO sin Capex · %s → %s%s',
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
                ['Con puente BS.USO', (string) ($informe['total'] ?? 0)],
                ['OK (con Capex)', (string) ($informe['ok'] ?? 0)],
                ['Omitidos (apertura / Anita)', (string) ($informe['omitidos'] ?? 0)],
                ['Desvío OC sin Capex', (string) ($informe['desvios_oc_sin_capex'] ?? 0)],
                ['Desvío sin OC', (string) ($informe['desvios_sin_oc'] ?? 0)],
                ['Errores', (string) count($informe['errores'] ?? [])],
            ],
        );

        foreach (array_slice($informe['desvios'] ?? [], 0, 25) as $fila) {
            $tipo = (($fila['tipo'] ?? '') === 'sin_oc') ? 'sin OC' : 'OC sin Capex';
            $this->warn(sprintf(
                '[%s] %s | OC %s | $ %s | %s',
                $tipo,
                (string) ($fila['etiqueta'] ?? '?'),
                (string) (($fila['oc'] ?? '') !== '' ? $fila['oc'] : '—'),
                number_format((float) ($fila['monto_puente'] ?? 0), 2, ',', '.'),
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
