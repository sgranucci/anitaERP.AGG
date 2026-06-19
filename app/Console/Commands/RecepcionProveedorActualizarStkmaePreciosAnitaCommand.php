<?php

namespace App\Console\Commands;

use App\Models\Stock\Recepcion_Proveedor;
use App\Services\Stock\RecepcionProveedorStkmaePrecioAnitaBackfillService;
use Illuminate\Console\Command;

class RecepcionProveedorActualizarStkmaePreciosAnitaCommand extends Command
{
    protected $signature = 'recepcion-proveedor:actualizar-stkmae-precios-anita
                            {--desde= : Fecha ISO desde (inclusive)}
                            {--hasta= : Fecha ISO hasta (inclusive)}
                            {--id= : Solo una recepción por ID ERP}
                            {--limite= : Máximo de recepciones a procesar}
                            {--incluir-importadas : Incluye origen ANITA_IMPORT (histórico importado)}
                            {--reprocesar : Ignora stkmae_precio_anita_sync_at y vuelve a empujar precios}
                            {--dry-run : Solo contadores, sin llamar Anita}';

    protected $description = 'Backfill stkmae.stkm_pre_compra* en Anita para recepciones confirmadas cargadas en anitaERP';

    public function handle(RecepcionProveedorStkmaePrecioAnitaBackfillService $service): int
    {
        $opciones = [
            'desde' => $this->option('desde') ?: null,
            'hasta' => $this->option('hasta') ?: null,
            'id' => $this->option('id') ? (int) $this->option('id') : null,
            'limite' => $this->option('limite') ? (int) $this->option('limite') : null,
            'dry_run' => (bool) $this->option('dry-run'),
            'reprocesar' => (bool) $this->option('reprocesar'),
            'incluir_importadas' => (bool) $this->option('incluir-importadas'),
        ];

        if ($opciones['dry_run']) {
            $this->warn('Dry-run: no se escribirá en Anita ni se marcará sync_at.');
        }

        if (! $opciones['incluir_importadas']) {
            $this->info('Alcance: recepciones ERP (excluye ANITA_IMPORT; use --incluir-importadas para histórico Anita).');
        }

        $total = $service->contarCandidatas(
            $opciones,
            $opciones['reprocesar'],
            $opciones['incluir_importadas']
        );
        $this->info("Recepciones candidatas: {$total}");

        if ($total === 0) {
            return self::SUCCESS;
        }

        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $stats = $service->ejecutar($opciones, function (Recepcion_Proveedor $recepcion, int $articulos, ?\Throwable $error) use ($bar) {
            $bar->advance();
            if ($error !== null) {
                $this->newLine();
                $this->error(
                    'Recepción '.$recepcion->id.' (COM '.$recepcion->numerorecepcion.'): '.$error->getMessage()
                );
            }
        });

        $bar->finish();
        $this->newLine(2);

        $this->table(['Métrica', 'Cantidad'], [
            ['Candidatas', $stats['candidatas']],
            ['Procesadas', $stats['procesadas']],
            ['Artículos stkmae actualizados', $stats['articulos_stkmae']],
            ['Sin líneas aplicables', $stats['sin_lineas']],
            ['Errores', $stats['errores']],
        ]);

        return $stats['errores'] > 0 ? self::FAILURE : self::SUCCESS;
    }
}
