<?php

namespace App\Console\Commands;

use App\Services\Compras\Tracking\TrackingIndiceSyncService;
use Illuminate\Console\Command;

class TrackingFacturasSincronizarIndiceCommand extends Command
{
    protected $signature = 'tracking-facturas:sincronizar-indice
                            {--lote=200 : Comprobantes por lote (cada lote es una consulta al puente)}
                            {--id= : Sincroniza un solo comprobante}
                            {--limite= : Corta después de N comprobantes (para probar sin recorrer todo)}
                            {--solo-faltantes : Sólo los comprobantes que aún no están en el índice}
                            {--solo-pagos : Actualiza estado de pago y orden de pago sin volver a resolver el PDF}';

    protected $description = 'Resuelve PDF, fecha de carga real y estado de pago del tracking de facturas';

    public function handle(TrackingIndiceSyncService $service): int
    {
        $lote = max(1, (int) $this->option('lote'));
        $id = $this->option('id') !== null ? (int) $this->option('id') : null;
        $limite = $this->option('limite') !== null ? max(1, (int) $this->option('limite')) : null;
        $soloFaltantes = (bool) $this->option('solo-faltantes');
        $soloPagos = (bool) $this->option('solo-pagos');

        $this->line(sprintf(
            'Sincronizando índice de tracking | lote %d | %s%s%s%s',
            $lote,
            $id !== null ? 'comprobante '.$id : 'todos los comprobantes',
            $limite !== null ? ' | límite '.$limite : '',
            $soloFaltantes ? ' | sólo faltantes' : '',
            $soloPagos ? ' | sólo pagos' : '',
        ));

        if ($soloPagos) {
            $this->comment('Sin resolver PDF: sólo cuenta corriente del ERP, promov y aplmovp.');
        } else {
            $this->comment('Fuentes: adjuntos y precargas del ERP, Facturas_scan y base_admin.scanfactura + promov.');
        }

        $barra = null;
        $stats = $service->sincronizar($lote, $id, $soloFaltantes, function (int $hechos, int $total) use (&$barra) {
            if ($barra === null) {
                $barra = $this->output->createProgressBar(max($total, 1));
                $barra->start();
            }
            $barra->setProgress(min($hechos, max($total, 1)));
        }, $limite, $soloPagos);

        $barra?->finish();
        $this->newLine(2);

        if ($stats['procesados'] === 0) {
            $this->warn('No había comprobantes para sincronizar.');

            return self::SUCCESS;
        }

        // En el pase de pagos no se resolvió ningún PDF: mostrar «0 con PDF»
        // haría pensar que se perdió la cobertura que ya estaba en el índice.
        if ($soloPagos) {
            $this->table(
                ['Procesados', 'Con orden de pago', 'Con deuda'],
                [[$stats['procesados'], $stats['con_op'], $stats['con_deuda']]]
            );

            return self::SUCCESS;
        }

        $this->table(
            ['Procesados', 'Con PDF', 'Sin PDF', 'Con orden de pago', 'Con deuda'],
            [[
                $stats['procesados'],
                sprintf('%d (%.1f%%)', $stats['con_pdf'], 100 * $stats['con_pdf'] / $stats['procesados']),
                $stats['sin_pdf'],
                $stats['con_op'],
                $stats['con_deuda'],
            ]]
        );

        return self::SUCCESS;
    }
}
