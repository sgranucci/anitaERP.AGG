<?php

declare(strict_types=1);

namespace App\Console\Commands\Ventas;

use App\Support\Ventas\PedidoCombinacionTalleDuplicadosSupport;
use Illuminate\Console\Command;

final class LimpiarPedidoCombinacionTalleDuplicadosCommand extends Command
{
    protected $signature = 'ventas:limpiar-talles-pedido-duplicados
                            {--pedido_combinacion_id= : Solo este ítem de pedido}
                            {--dry-run : Solo analiza (default si no hay --ejecutar)}
                            {--ejecutar : Borra duplicados conservando el id más alto}';

    protected $description = 'Elimina filas duplicadas en pedido_combinacion_talle (mismo ítem + talle)';

    public function handle(): int
    {
        $ejecutar = (bool) $this->option('ejecutar');
        $dryRunFlag = (bool) $this->option('dry-run');
        if ($ejecutar && $dryRunFlag) {
            $this->error('No combine --ejecutar con --dry-run.');

            return self::FAILURE;
        }

        $dryRun = ! $ejecutar;
        $pcOpt = $this->option('pedido_combinacion_id');
        $pcId = ($pcOpt !== null && $pcOpt !== '') ? (int) $pcOpt : null;

        $this->line($dryRun ? 'DRY-RUN (no escribe)' : 'EJECUTAR (borra duplicados)');

        if ($dryRun) {
            $analisis = PedidoCombinacionTalleDuplicadosSupport::analizar($pcId);
            $this->table(['Concepto', 'Valor'], [
                ['Grupos (ítem+talle) duplicados', (string) $analisis['grupos']],
                ['Filas a borrar', (string) $analisis['filas_a_borrar']],
            ]);

            $muestra = array_slice($analisis['detalle'], 0, 15);
            if ($muestra !== []) {
                $this->table(
                    ['pedido_combinacion_id', 'talle_id', 'conservar_id', 'borrar_ids'],
                    array_map(static function (array $row): array {
                        return [
                            (string) $row['pedido_combinacion_id'],
                            (string) $row['talle_id'],
                            (string) $row['conservar_id'],
                            implode(',', $row['borrar_ids']),
                        ];
                    }, $muestra)
                );
                if (count($analisis['detalle']) > 15) {
                    $this->comment('… y '.(count($analisis['detalle']) - 15).' grupos más.');
                }
            }

            $this->comment('Para aplicar: php artisan ventas:limpiar-talles-pedido-duplicados --ejecutar');

            return self::SUCCESS;
        }

        $resultado = PedidoCombinacionTalleDuplicadosSupport::ejecutar($pcId);
        $this->table(['Concepto', 'Valor'], [
            ['Grupos procesados', (string) $resultado['grupos']],
            ['Filas borradas', (string) $resultado['filas_borradas']],
            ['OCT reasignados', (string) $resultado['oct_reasignados']],
            ['AMT reasignados', (string) $resultado['amt_reasignados']],
        ]);

        $queda = PedidoCombinacionTalleDuplicadosSupport::analizar($pcId);
        $this->line('Duplicados restantes: '.$queda['grupos'].' grupos / '.$queda['filas_a_borrar'].' filas.');

        return self::SUCCESS;
    }
}
