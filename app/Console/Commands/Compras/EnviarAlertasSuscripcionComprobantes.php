<?php

namespace App\Console\Commands\Compras;

use App\Services\Compras\SuscripcionComprobanteAvisoService;
use Illuminate\Console\Command;

class EnviarAlertasSuscripcionComprobantes extends Command
{
    protected $signature = 'compras:alertas-suscripcion-comprobantes
                            {--simular : Muestra cantidades sin enviar mails}';

    protected $description = 'Avisa facturas de portal de suscripción pendientes al dueño y escala a gerencia desde el umbral configurado (default: último día del mes del período).';

    public function handle(SuscripcionComprobanteAvisoService $service): int
    {
        $simular = (bool) $this->option('simular');
        $resultado = $service->procesar(null, $simular);

        if ($simular) {
            $this->info(sprintf(
                'Simulación: %d dueños con pendientes, %d ítems para escalar.',
                (int) ($resultado['owners'] ?? 0),
                (int) ($resultado['escala_cantidad'] ?? 0)
            ));

            return self::SUCCESS;
        }

        $this->line(sprintf(
            'Mails dueño: %d. Escalamiento: %d.',
            $resultado['enviados_dueno'],
            $resultado['enviados_escala']
        ));

        if (($resultado['omitido'] ?? null) !== null) {
            $this->info('Detalle: '.$resultado['omitido']);
        }

        return self::SUCCESS;
    }
}
