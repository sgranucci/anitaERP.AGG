<?php

declare(strict_types=1);

namespace App\Console\Commands\Contable;

use App\Services\Contable\AsientoSubdiarioDuplicadoTransferenciaAuditorService;
use App\Support\Configuracion\EntornoEmpresaSupport;
use Illuminate\Console\Command;

/**
 * Crown: alerta diaria si el import Anita dejó asientos subdiario que duplican
 * facturas ya asentadas por transferencia / venta ERP.
 */
class AuditarAsientosSubdiarioDuplicadosTransferenciaCommand extends Command
{
    protected $signature = 'contable:auditar-asientos-subdiario-duplicados-transferencia
                            {--fecha= : Corte Y-m-d (default: hoy)}
                            {--sin-mail : No envía correo}
                            {--forzar : Corre aunque EMPRESA no sea CROWN}';

    protected $description = 'Crown: detecta asientos Anita subdiario duplicados vs transferencia/venta y avisa por mail';

    public function handle(AsientoSubdiarioDuplicadoTransferenciaAuditorService $service): int
    {
        if (! (bool) config('contable.asiento_subdiario_duplicado_auditor.habilitada', true)) {
            $this->warn('Auditor deshabilitado (contable.asiento_subdiario_duplicado_auditor.habilitada).');

            return self::SUCCESS;
        }

        $forzar = (bool) $this->option('forzar');
        if (! $forzar && ! EntornoEmpresaSupport::esCrown()) {
            $this->warn('Solo corre en Crown (o use --forzar).');

            return self::SUCCESS;
        }

        $fecha = trim((string) ($this->option('fecha') ?? ''));
        $enviarMail = ! (bool) $this->option('sin-mail');

        $this->line(sprintf(
            'Auditor asientos subdiario duplicados · corte %s · ventana %d días%s',
            $fecha !== '' ? $fecha : 'hoy',
            (int) config('contable.asiento_subdiario_duplicado_auditor.ventana_dias', 60),
            $enviarMail ? '' : ' · sin mail'
        ));

        try {
            $informe = $service->ejecutar(
                $fecha !== '' ? $fecha : null,
                $enviarMail,
            );
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->table(
            ['Concepto', 'Valor'],
            [
                ['Empresa', (string) ($informe['empresa_nombre'] ?? '')],
                ['Ventana', ($informe['desde'] ?? '').' → '.($informe['hasta'] ?? '')],
                ['Estado', ! empty($informe['requiere_alerta']) ? 'ALERTA' : 'OK'],
                ['Duplicados', (string) ($informe['cantidad'] ?? 0)],
                ['Importe', number_format((float) ($informe['importe_total'] ?? 0), 2, ',', '.')],
                ['Mail', ! empty($informe['mail_enviado'])
                    ? ('sí → '.$informe['mail_destino'])
                    : (string) ($informe['mail_error'] ?? 'no')],
            ],
        );

        if (! empty($informe['requiere_alerta'])) {
            $this->warn('Hay duplicados. Limpieza: '.$informe['comando_limpieza']);
        } else {
            $this->info('Sin duplicados en la ventana.');
        }

        return ! empty($informe['requiere_alerta']) ? self::FAILURE : self::SUCCESS;
    }
}
