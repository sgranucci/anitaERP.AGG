<?php

namespace App\Console\Commands\Compras;

use App\Services\Compras\ComprobanteProveedorAuditorIvaTipoService;
use Illuminate\Console\Command;

class ComprobanteProveedorAuditorIvaTipoCommand extends Command
{
    protected $signature = 'comprobante-proveedor:auditar-iva-tipo
                            {--desde= : Desde Y-m-d (default: 1º del mes)}
                            {--hasta= : Hasta Y-m-d (default: hoy)}
                            {--sin-mail : No envía correo}';

    protected $description = 'Audita (solo ERP) conceptos IVA ajenos al tipo de comprobante';

    public function handle(ComprobanteProveedorAuditorIvaTipoService $service): int
    {
        if (! config('comprobante_proveedor_auditoria_erp.iva_fuera_tipo.habilitada', true)) {
            $this->warn('Auditoría deshabilitada (comprobante_proveedor_auditoria_erp.iva_fuera_tipo.habilitada).');

            return self::SUCCESS;
        }

        $desde = trim((string) ($this->option('desde') ?? ''));
        $hasta = trim((string) ($this->option('hasta') ?? ''));
        $enviarMail = ! (bool) $this->option('sin-mail');

        $this->line(sprintf(
            'IVA fuera de tipo · %s → %s%s',
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
                ['En alcance', (string) ($informe['total'] ?? 0)],
                ['OK', (string) ($informe['ok'] ?? 0)],
                ['Omitidos', (string) ($informe['omitidos'] ?? 0)],
                ['Desvíos', (string) count($informe['desvios'] ?? [])],
                ['Errores', (string) count($informe['errores'] ?? [])],
            ],
        );

        foreach (array_slice($informe['desvios'] ?? [], 0, 25) as $fila) {
            $this->warn(sprintf(
                '%s [%s] · %s',
                (string) ($fila['etiqueta'] ?? '?'),
                (string) ($fila['abreviatura'] ?? ''),
                implode('; ', $fila['conceptos_fuera'] ?? []),
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
