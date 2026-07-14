<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Ventas\Gastronomia\GastronomiaInformeZTransmisionFaltanteService;
use Illuminate\Console\Command;

class GastronomiaVerificarInformeZTransmision extends Command
{
    protected $signature = 'gastronomia:verificar-informe-z-transmision
                            {jornada_id : ID de jornada_gastronomia}
                            {--sin-mail : No envía correo aunque haya diferencias}';

    protected $description = 'Relee Waitry vs Informe Z del cierre y documenta comandas no transmitidas a tiempo';

    public function handle(GastronomiaInformeZTransmisionFaltanteService $service): int
    {
        $jornadaId = (int) $this->argument('jornada_id');
        $enviarMail = ! (bool) $this->option('sin-mail');

        $resultado = $service->verificarYPersistir($jornadaId, $enviarMail);

        if (! ($resultado['ok'] ?? false)) {
            $this->error((string) ($resultado['error'] ?? 'Error desconocido'));

            return self::FAILURE;
        }

        $a = is_array($resultado['analisis'] ?? null) ? $resultado['analisis'] : [];
        $this->info(sprintf(
            'Jornada %d — diferencias: %s — faltante $ %s (%d comandas) — mail: %s',
            $jornadaId,
            ! empty($a['tiene_diferencias']) ? 'SÍ' : 'no',
            number_format((float) ($a['total_faltante'] ?? 0), 2, ',', '.'),
            (int) ($a['cantidad_comandas'] ?? 0),
            ! empty($a['mail_enviado']) ? 'enviado' : 'no',
        ));

        foreach ($a['comandas'] ?? [] as $c) {
            if (! is_array($c)) {
                continue;
            }
            $this->line(sprintf(
                '  • %s (#%d) $ %s — %s',
                $c['display_id'] ?? '—',
                (int) ($c['waitry_order_id'] ?? 0),
                number_format((float) ($c['monto'] ?? 0), 2, ',', '.'),
                $c['placed_at'] ?? '',
            ));
        }

        return self::SUCCESS;
    }
}
