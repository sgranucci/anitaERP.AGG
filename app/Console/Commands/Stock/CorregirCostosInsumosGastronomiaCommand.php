<?php

namespace App\Console\Commands\Stock;

use App\Support\Stock\GastronomiaInsumoCostoCorreccionSupport;
use Illuminate\Console\Command;

class CorregirCostosInsumosGastronomiaCommand extends Command
{
    protected $signature = 'stock:corregir-costos-insumos-gastronomia
                            {--aplicar : Graba coeficientes, TRA, recuentos}
                            {--anita : Además empuja stkm_pre_compra3 desde las TRA corregidas}';

    protected $description = 'Corrige costo unitario de insumos I0038/I0042/I0044/I0053/I0133/I0149/I0221/I0404 (coef. compra + TRA + recuento).';

    public function handle(): int
    {
        $aplicar = (bool) $this->option('aplicar');
        $anita = (bool) $this->option('anita');

        if (! $aplicar) {
            $this->warn('Simulación: no se graba. Use --aplicar (y --anita para stkmae).');
        }

        try {
            $r = GastronomiaInsumoCostoCorreccionSupport::corregir($aplicar, $aplicar && $anita);
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info(sprintf(
            'Coeficientes: %d | TRA: %d | Mov. TRA: %d | Recuentos: %d | Anita stkmae: %d',
            $r['coeficientes'],
            $r['lineas_tra'],
            $r['movimientos_tra'],
            $r['recuentos'],
            $r['stkmae']
        ));

        foreach ($r['costos'] as $sku => $precio) {
            $this->line(sprintf('  %s última compra = %s', $sku, $precio === null ? '(sin dato)' : number_format((float) $precio, 2, ',', '.')));
        }

        return self::SUCCESS;
    }
}
