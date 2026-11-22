<?php

declare(strict_types=1);

namespace App\Console\Commands\Caja;

use App\Services\Caja\CierreCajaMailDiarioService;
use Illuminate\Console\Command;

class EnviarCierreCajaDiarioCommand extends Command
{
    protected $signature = 'caja:enviar-cierre-caja-diario
                            {--fecha= : Fecha Y-m-d (default: config hoy/ayer)}
                            {--sin-mail : Genera el informe sin enviar correo}';

    protected $description = 'Envía por mail el cierre de caja del día (Anita l-ciecaja -m)';

    public function handle(CierreCajaMailDiarioService $service): int
    {
        $fecha = trim((string) ($this->option('fecha') ?? ''));
        $enviarMail = ! (bool) $this->option('sin-mail');

        $this->line(sprintf(
            'Cierre de caja diario · fecha %s%s',
            $fecha !== '' ? $fecha : 'config ('.(string) config('caja.cierre_caja_mail.fecha_relativa', 'hoy').')',
            $enviarMail ? '' : ' · sin mail'
        ));

        $resultado = $service->ejecutar($fecha !== '' ? $fecha : null, $enviarMail);

        if ($resultado['omitido'] !== null) {
            $this->warn('Omitido: '.$resultado['omitido']);

            return self::SUCCESS;
        }

        if (! $resultado['ok']) {
            $this->error($resultado['error'] ?? 'Error desconocido');

            return self::FAILURE;
        }

        $this->info(sprintf(
            'OK · fecha %s · destinatarios %d',
            $resultado['fecha'],
            $resultado['enviados']
        ));

        return self::SUCCESS;
    }
}
