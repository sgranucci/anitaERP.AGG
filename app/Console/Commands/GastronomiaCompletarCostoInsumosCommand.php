<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Stock\GastronomiaInsumoCostoDiferidoService;
use Illuminate\Console\Command;

/**
 * Completa costo de insumos grabados en 0 por emisión con costo diferido (ver config gastronomia.insumos_costo_diferido).
 */
class GastronomiaCompletarCostoInsumosCommand extends Command
{
    protected $signature = 'gastronomia:completar-costo-insumos
                            {--dias= : Ventana hacia atrás por fechajornada (default config, 2)}
                            {--max-articulos= : Tope de artículos distintos por pasada (default config, 300)}
                            {--dry-run : Solo contar, no grabar}';

    protected $description = 'Completa costo última compra en movimientos de insumo (venta/NC gastronomía) con costo 0 de los últimos días';

    public function handle(GastronomiaInsumoCostoDiferidoService $service): int
    {
        $dias = (int) ($this->option('dias') ?: config('gastronomia.insumos_costo_diferido.dias', 2));
        $max = $this->option('max-articulos') !== null ? (int) $this->option('max-articulos') : null;
        $dryRun = (bool) $this->option('dry-run');

        $r = $service->completar($dias, $dryRun, $max);

        $this->line(sprintf(
            '[%s] desde=%s pendientes=%d artículos=%d/%d actualizados=%d sin_costo=%d (movs %d) %d ms%s',
            now()->format('Y-m-d H:i:s'),
            $r['desde'],
            $r['pendientes'],
            $r['articulos_procesados'],
            $r['articulos'],
            $r['actualizados'],
            $r['sin_costo'],
            $r['sin_costo_movs'],
            $r['ms'],
            $dryRun ? ' (dry-run)' : '',
        ));

        return self::SUCCESS;
    }
}
