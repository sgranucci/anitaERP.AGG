<?php

namespace App\Console\Commands\Stock;

use App\Support\Configuracion\EntornoEmpresaSupport;
use App\Support\Stock\MovimientoStockTipotransaccionRefillSupport;
use Illuminate\Console\Command;

/**
 * Ferli: rellena tipotransaccion_stock_map y tipo en movimientostock / articulo_movimiento.
 * Default dry-run; requiere --ejecutar para persistir.
 */
class RefillTipotransaccionStockFerliCommand extends Command
{
    protected $signature = 'stock:refill-tipotransaccion-stock-ferli
                            {--ejecutar : Persiste; sin esto solo dry-run}';

    protected $description = 'Ferli: arma mapa ventas→stock por abreviatura y rellena tipos en movimientos históricos';

    public function handle(): int
    {
        if (! EntornoEmpresaSupport::esFerli()) {
            $this->error('Este comando solo corre en entorno Calzados Ferli (EMPRESA actual: '.config('app.empresa').').');

            return self::FAILURE;
        }

        $ejecutar = (bool) $this->option('ejecutar');
        $this->info(($ejecutar ? 'EJECUTAR' : 'DRY-RUN').' refill tipotransaccion_stock (Ferli)');

        $plan = MovimientoStockTipotransaccionRefillSupport::planificar();

        $this->line('Mapa propuesto (por abreviatura):');
        $this->table(
            ['Abrev', 'tipotransaccion_id', 'tipotransaccion_stock_id', 'Nombre stock'],
            array_map(static fn (array $f) => [
                $f['abreviatura'],
                $f['tipotransaccion_id'],
                $f['tipotransaccion_stock_id'],
                $f['nombre_stock'],
            ], $plan['mapa_propuesto'])
        );
        $this->line('Mapa ya existente: '.$plan['mapa_ya_existente'].' | a insertar: '.$plan['mapa_a_insertar']);

        $resumenRows = [];
        foreach ($plan['resumen_por_abreviatura'] as $abrev => $dato) {
            $resumenRows[] = [$abrev, $dato['tipo_stock_id'], $dato['cabeceras'], $dato['lineas']];
        }
        $this->line('Cabeceras a actualizar por tipo:');
        $this->table(['Abrev', 'stock_id', 'Cabeceras', 'Líneas (de esas cab.)'], $resumenRows);

        $this->table(['Métrica', 'Cantidad'], [
            ['Cabeceras a actualizar', count($plan['cabeceras_a_actualizar'])],
            ['Cabeceras sin líneas (quedan en 0)', count($plan['cabeceras_sin_lineas'])],
            ['Cabeceras sin mapa', count($plan['cabeceras_sin_mapa'])],
            ['Líneas a actualizar (estimadas)', $plan['lineas_a_actualizar']],
        ]);

        if ($plan['cabeceras_sin_lineas'] !== []) {
            $muestra = array_slice($plan['cabeceras_sin_lineas'], 0, 30);
            $this->warn('MS sin líneas (muestra): '.implode(', ', $muestra));
        }
        if ($plan['cabeceras_sin_mapa'] !== []) {
            $this->warn('Cabeceras sin mapa (no se tocan): '.count($plan['cabeceras_sin_mapa']));
            foreach (array_slice($plan['cabeceras_sin_mapa'], 0, 10) as $fila) {
                $this->line("  MS #{$fila['movimientostock_id']} tipo_venta={$fila['tipo_venta_id']} lineas={$fila['lineas']}");
            }
        }

        if (! $ejecutar) {
            $this->comment('Sin cambios. Para persistir: php artisan stock:refill-tipotransaccion-stock-ferli --ejecutar');

            return self::SUCCESS;
        }

        $resultado = MovimientoStockTipotransaccionRefillSupport::ejecutar($plan);
        $this->info('Persistido:');
        $this->line('  Filas mapa insertadas: '.$resultado['mapa_insertados']);
        $this->line('  Cabeceras movimientostock: '.$resultado['cabeceras']);
        $this->line('  Líneas articulo_movimiento: '.$resultado['lineas']);

        return self::SUCCESS;
    }
}
