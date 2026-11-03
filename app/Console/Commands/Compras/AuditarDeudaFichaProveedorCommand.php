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

    protected $description = 'Auditor deuda vs ficha y delta conjunto de proveedores + anticipos';

    public function handle(ProveedorCuentacorrienteDeudaFichaAuditorService $service): int
    {
        if (! config('compras.deuda_ficha_auditor.habilitada', true)) {
            $this->warn('Auditor deshabilitado (compras.deuda_ficha_auditor.habilitada).');

            return self::SUCCESS;
        }

        $fecha = trim((string) ($this->option('fecha') ?? ''));
        $empresaId = (int) ($this->option('empresa') ?: 0);
        $enviarMail = ! (bool) $this->option('sin-mail');
        $permitidas = array_values(array_map(
            static fn ($id) => (int) $id,
            (array) config('compras.deuda_ficha_auditor.empresas', [1, 2, 3])
        ));

        if ($empresaId > 0 && ! in_array($empresaId, $permitidas, true)) {
            $this->warn(sprintf(
                'La empresa %d queda fuera del auditor (COMPRAS_DEUDA_FICHA_AUDITOR_EMPRESAS=%s).',
                $empresaId,
                $permitidas === [] ? '(ninguna)' : implode(',', $permitidas)
            ));

            return self::SUCCESS;
        }

        $this->line(sprintf(
            'Auditor deuda vs ficha y delta conjunto · corte %s · expresión %s · empresas %s%s',
            $fecha !== '' ? $fecha : 'hoy',
            (string) config('compras.deuda_ficha_auditor.expresion', 'origen'),
            $permitidas === [] ? '(ninguna)' : implode(',', $permitidas),
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
            $delta = $empresa['delta_mayor'] ?? [];
            $variacion = $delta['variacion_conjunto'] ?? null;
            $estadoDelta = $variacion === null
                ? 'base'
                : (abs((float) $variacion) >= (float) ($delta['tolerancia'] ?? 1)
                    ? number_format((float) $variacion, 2, ',', '.')
                    : 'estable');
            $filas[] = [
                $empresa['empresa_id'],
                $empresa['empresa_nombre'],
                ! empty($empresa['requiere_alerta']) ? 'ALERTA' : 'OK',
                (string) ($empresa['grupos_total'] ?? 0),
                $estadoDelta,
                ! empty($empresa['mail_enviado'])
                    ? ('sí → '.$empresa['mail_destino'])
                    : (! empty($empresa['mail_error']) ? 'error: '.$empresa['mail_error'] : 'no'),
            ];
        }
        $this->table(['Empresa', 'Nombre', 'Estado', 'Dif. ficha', 'Delta conjunto', 'Mail'], $filas);

        $this->info(sprintf(
            'Mails enviados: %d · omitidos: %d',
            (int) $informe['mails_enviados'],
            (int) $informe['mails_omitidos']
        ));

        return ! empty($informe['requiere_alerta']) ? self::FAILURE : self::SUCCESS;
    }
}
