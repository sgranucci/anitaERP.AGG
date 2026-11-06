<?php

declare(strict_types=1);

namespace App\Console\Commands\Compras;

use App\Services\Compras\ProveedorFacturaAnticipadaRecepcionAuditorService;
use Illuminate\Console\Command;

class AuditarFacturaAnticipadaRecepcionCommand extends Command
{
    protected $signature = 'compras:auditar-factura-anticipada-recepcion
                            {--empresa= : Solo esta empresa_id}
                            {--sin-mail : No envía correo}';

    protected $description = 'Auditor factura anticipada vs recepción en cuenta de anticipos';

    public function handle(ProveedorFacturaAnticipadaRecepcionAuditorService $service): int
    {
        if (! config('compras.factura_anticipada_recepcion_auditor.habilitada', true)) {
            $this->warn('Auditor deshabilitado (compras.factura_anticipada_recepcion_auditor.habilitada).');

            return self::SUCCESS;
        }

        $empresaOpt = trim((string) ($this->option('empresa') ?? ''));
        $empresaId = $empresaOpt !== '' ? (int) $empresaOpt : null;
        $enviarMail = ! (bool) $this->option('sin-mail');

        $this->line(sprintf(
            'Auditor factura anticipada vs recepción · empresas %s%s',
            $empresaId !== null && $empresaId > 0
                ? (string) $empresaId
                : implode(',', (array) config('compras.factura_anticipada_recepcion_auditor.empresas', [1, 2, 3])),
            $enviarMail ? '' : ' · sin mail',
        ));

        try {
            $salida = $service->ejecutar($enviarMail, $empresaId);
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        foreach ($salida['empresas'] ?? [] as $emp) {
            $this->table(
                ['Empresa', 'Órdenes', 'Desvíos', 'Pendientes', 'Exceso recepción', 'Mail'],
                [[
                    (string) ($emp['empresa_nombre'] ?? $emp['empresa_id'] ?? ''),
                    (string) ($emp['ordenes_total'] ?? 0),
                    (string) ($emp['desvios_total'] ?? 0),
                    (string) ($emp['pendientes_total'] ?? 0),
                    number_format((float) ($emp['exceso_recepcion'] ?? 0), 2, ',', '.'),
                    ! empty($emp['mail_enviado'])
                        ? ('OK '.$emp['mail_destino'])
                        : (string) ($emp['mail_error'] ?? 'omitido'),
                ]],
            );

            foreach (array_slice($emp['desvios'] ?? [], 0, 15) as $fila) {
                $this->warn(sprintf(
                    'OC %s | factura %s | recepción %s | exceso %s',
                    (string) ($fila['numeroordencompra'] ?? $fila['ordencompra_id'] ?? '?'),
                    number_format((float) ($fila['factura'] ?? 0), 2, ',', '.'),
                    number_format((float) ($fila['recepcion'] ?? 0), 2, ',', '.'),
                    number_format((float) ($fila['exceso_recepcion'] ?? 0), 2, ',', '.'),
                ));
            }
        }

        return ! empty($salida['requiere_alerta']) ? self::FAILURE : self::SUCCESS;
    }
}
