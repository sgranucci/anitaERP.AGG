<?php

namespace App\Console\Commands\Compras;

use App\Services\Compras\ProveedorCuentacorrienteDeudaFichaAuditorService;
use Illuminate\Console\Command;

class AuditarDeudaFichaProveedorCommand extends Command
{
    protected $signature = 'compras:auditar-deuda-ficha-proveedor
                            {--fecha= : Corte Y-m-d (default: hoy)}
                            {--empresa= : Solo esta empresa_id}
                            {--sin-mail : No envía correo}';

    protected $description = 'Auditor deuda vs ficha CC por empresa: mail con diferencias (o verde si cuadra)';

    public function handle(ProveedorCuentacorrienteDeudaFichaAuditorService $service): int
    {
        if (! config('compras.deuda_ficha_auditor.habilitada', true)) {
            $this->warn('Auditor deshabilitado (compras.deuda_ficha_auditor.habilitada).');

            return self::SUCCESS;
        }

        $fecha = trim((string) ($this->option('fecha') ?? ''));
        $empresaId = (int) ($this->option('empresa') ?: 0);
        $enviarMail = ! (bool) $this->option('sin-mail');

        $this->line(sprintf(
            'Auditor deuda vs ficha · corte %s · expresión %s%s',
            $fecha !== '' ? $fecha : 'hoy',
            (string) config('compras.deuda_ficha_auditor.expresion', 'origen'),
            $enviarMail ? '' : ' · sin mail'
        ));

        try {
            $informe = $service->ejecutar(
                $fecha !== '' ? $fecha : null,
                $enviarMail,
                $empresaId > 0 ? $empresaId : null,
            );
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $filas = [];
        foreach ($informe['empresas'] as $empresa) {
            $filas[] = [
                $empresa['empresa_id'],
                $empresa['empresa_nombre'],
                ! empty($empresa['requiere_alerta']) ? 'ALERTA' : 'OK',
                (string) ($empresa['grupos_total'] ?? 0),
                ! empty($empresa['mail_enviado'])
                    ? ('sí → '.$empresa['mail_destino'])
                    : (! empty($empresa['mail_error']) ? 'error: '.$empresa['mail_error'] : 'no'),
            ];
        }
        $this->table(['Empresa', 'Nombre', 'Estado', 'Diferencias', 'Mail'], $filas);

        $this->info(sprintf(
            'Mails enviados: %d · omitidos: %d',
            (int) $informe['mails_enviados'],
            (int) $informe['mails_omitidos']
        ));

        return ! empty($informe['requiere_alerta']) ? self::FAILURE : self::SUCCESS;
    }
}
