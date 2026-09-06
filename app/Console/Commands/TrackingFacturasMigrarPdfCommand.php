<?php

namespace App\Console\Commands;

use App\Services\Compras\Tracking\TrackingPdfMigradorService;
use Illuminate\Console\Command;

class TrackingFacturasMigrarPdfCommand extends Command
{
    protected $signature = 'tracking-facturas:migrar-pdf
                            {--lote=100 : Comprobantes por lote}
                            {--limite= : Corta después de N comprobantes}
                            {--ejecutar : Copia de verdad (sin esta opción sólo simula)}';

    protected $description = 'Copia los PDF escaneados del Anita al repositorio propio del ERP (no borra el origen)';

    public function handle(TrackingPdfMigradorService $service): int
    {
        $simular = ! (bool) $this->option('ejecutar');
        $lote = max(1, (int) $this->option('lote'));
        $limite = $this->option('limite') !== null ? max(1, (int) $this->option('limite')) : null;

        $this->line(sprintf(
            'Migración de PDF Anita → Facturas_scan/comprobantes | lote %d%s | %s',
            $lote,
            $limite !== null ? ' | límite '.$limite : '',
            $simular ? 'SIMULACIÓN' : 'EJECUTAR',
        ));
        $this->comment('No se borra nada del Anita: la copia es reintentable.');

        $barra = null;
        $stats = $service->migrar($lote, $limite, $simular, function (int $hechos, int $total) use (&$barra) {
            if ($barra === null) {
                $barra = $this->output->createProgressBar(max($total, 1));
                $barra->start();
            }
            $barra->setProgress(min($hechos, max($total, 1)));
        });

        $barra?->finish();
        $this->newLine(2);

        $this->table(
            ['Candidatos', $simular ? 'A copiar' : 'Copiados', 'Ya estaban', 'Sin origen', 'Errores', 'Peso'],
            [[
                $stats['candidatos'],
                $stats['copiados'],
                $stats['ya_estaban'],
                $stats['sin_origen'],
                $stats['errores'],
                $this->formatearBytes($stats['bytes']),
            ]]
        );

        foreach ($stats['detalle_errores'] as $error) {
            $this->warn('  '.$error);
        }

        if ($simular && $stats['copiados'] > 0) {
            $this->info('Simulación: volvé a correr con --ejecutar para copiar.');
        }

        return $stats['errores'] > 0 && $stats['copiados'] === 0 ? self::FAILURE : self::SUCCESS;
    }

    private function formatearBytes(int $bytes): string
    {
        if ($bytes >= 1073741824) {
            return number_format($bytes / 1073741824, 2, ',', '.').' GB';
        }

        return number_format($bytes / 1048576, 1, ',', '.').' MB';
    }
}
