<?php

declare(strict_types=1);

namespace App\Console\Commands\Ventas;

use App\Support\Ventas\OrdentrabajoCombinacionTalleDuplicadosSupport;
use Illuminate\Console\Command;

final class LimpiarOrdentrabajoCombinacionTalleDuplicadosCommand extends Command
{
    protected $signature = 'ventas:limpiar-oct-duplicados
                            {--ordentrabajo_id= : Solo esta OT}
                            {--dry-run : Solo analiza (default si no hay --ejecutar)}
                            {--ejecutar : Borra duplicados conservando el id más bajo}';

    protected $description = 'Elimina filas duplicadas en ordentrabajo_combinacion_talle (mismo OT + PCT)';

    public function handle(): int
    {
        $ejecutar = (bool) $this->option('ejecutar');
        $dryRunFlag = (bool) $this->option('dry-run');
        if ($ejecutar && $dryRunFlag) {
            $this->error('No combine --ejecutar con --dry-run.');

            return self::FAILURE;
        }

        $dryRun = ! $ejecutar;
        $otOpt = $this->option('ordentrabajo_id');
        $otId = ($otOpt !== null && $otOpt !== '') ? (int) $otOpt : null;

        $this->line($dryRun ? 'DRY-RUN (no escribe)' : 'EJECUTAR (borra duplicados)');

        if ($dryRun) {
            $analisis = OrdentrabajoCombinacionTalleDuplicadosSupport::analizar($otId);
            $this->table(['Concepto', 'Valor'], [
                ['Grupos (OT+PCT) duplicados', (string) $analisis['grupos']],
                ['Filas a borrar', (string) $analisis['filas_a_borrar']],
            ]);

            $muestra = array_slice($analisis['detalle'], 0, 15);
            if ($muestra !== []) {
                $this->table(
                    ['ordentrabajo_id', 'pedido_combinacion_talle_id', 'conservar_id', 'borrar_ids'],
                    array_map(static function (array $row): array {
                        return [
                            (string) $row['ordentrabajo_id'],
                            (string) $row['pedido_combinacion_talle_id'],
                            (string) $row['conservar_id'],
                            implode(',', $row['borrar_ids']),
                        ];
                    }, $muestra)
                );
                if (count($analisis['detalle']) > 15) {
                    $this->comment('… y '.(count($analisis['detalle']) - 15).' grupos más.');
                }
            }

            $this->comment('Para aplicar: php artisan ventas:limpiar-oct-duplicados --ejecutar');

            return self::SUCCESS;
        }

        $resultado = OrdentrabajoCombinacionTalleDuplicadosSupport::ejecutar($otId);
        $this->table(['Concepto', 'Valor'], [
            ['Grupos procesados', (string) $resultado['grupos']],
            ['Filas borradas', (string) $resultado['filas_borradas']],
        ]);

        $queda = OrdentrabajoCombinacionTalleDuplicadosSupport::analizar($otId);
        $this->line('Duplicados restantes: '.$queda['grupos'].' grupos / '.$queda['filas_a_borrar'].' filas.');

        return self::SUCCESS;
    }
}
