<?php

namespace App\Console\Commands\Stock;

use App\Repositories\Stock\Articulo_EstadoRepositoryInterface;
use App\Support\Stock\InactivarArticulosCompraOcCc85Support;
use Illuminate\Console\Command;

class InactivarArticulosCompraOcCc85Command extends Command
{
    protected $signature = 'stock:inactivar-articulos-compra-oc-cc85
                            {csv=/home/sergio/tmp/OC CC 85 POR ARTICULO.csv : Ruta al CSV de pedidos OC CC 85}
                            {--aplicar : Graba inactivación solo en anitaERP (sin sync Anita)}
                            {--export= : Exporta candidatos a inactivar a un CSV}
                            {--force : Aplicar sin confirmación interactiva}
                            {--usuario=1 : usuario_id para historial articulo_estado}
                            {--mostrar=25 : Filas de muestra en consola (0 = omitir)}';

    protected $description = 'Inactiva artículos COMPRA tipo ALIMENTO/BEBIDA activos que no figuran en el CSV OC CC 85 (excluye V/I/LAB/LIB).';

    public function handle(Articulo_EstadoRepositoryInterface $articuloEstadoRepository): int
    {
        $rutaCsv = (string) $this->argument('csv');
        $aplicar = (bool) $this->option('aplicar');
        $usuarioId = max(1, (int) $this->option('usuario'));
        $mostrar = max(0, (int) $this->option('mostrar'));

        if (! $aplicar) {
            $this->warn('Modo simulación: no se graban cambios. Use --aplicar para inactivar en anitaERP.');
        }

        try {
            $csvSkus = InactivarArticulosCompraOcCc85Support::leerSkusDesdeCsv($rutaCsv);
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $resultado = InactivarArticulosCompraOcCc85Support::resolverCandidatos($csvSkus);

        $this->info('CSV: '.$rutaCsv);
        $this->info('SKUs únicos en reporte: '.$resultado['csv_skus_total']);
        $this->info('Activos COMPRA ALIMENTO/BEBIDA (sin patrón V/I/LAB/LIB): '
            .($resultado['mantener']->count() + $resultado['inactivar']->count()));
        $this->info('En reporte → mantener activos: '.$resultado['mantener']->count());
        $this->info('Excluidos por patrón SKU (V/I/LAB/LIB): '.$resultado['excluidos_patron']->count());
        $this->line('A inactivar (no están en reporte): <fg=yellow>'.$resultado['inactivar']->count().'</>');

        $exportPath = $this->option('export');
        if (is_string($exportPath) && $exportPath !== '') {
            $this->exportarCsv($exportPath, $resultado['inactivar']);
            $this->info('Exportado listado de candidatos: '.$exportPath);
        }

        if ($mostrar > 0 && $resultado['inactivar']->isNotEmpty()) {
            $this->newLine();
            $this->comment('Muestra de artículos a inactivar (hasta '.$mostrar.'):');
            $this->table(
                ['ID', 'SKU', 'Descripción', 'Tipo'],
                $resultado['inactivar']->take($mostrar)->map(fn ($r) => [
                    $r->id,
                    $r->sku,
                    $r->descripcion,
                    $r->tipo_nombre,
                ])->all(),
            );
        }

        if ($resultado['inactivar']->isEmpty()) {
            $this->comment('No hay artículos para inactivar.');

            return self::SUCCESS;
        }

        if (! $aplicar) {
            return self::SUCCESS;
        }

        if (! (bool) $this->option('force') && ! $this->confirm(
            '¿Inactivar '.$resultado['inactivar']->count().' artículo(s) en anitaERP (sin Anita legacy)?',
            false,
        )) {
            $this->warn('Cancelado.');

            return self::FAILURE;
        }

        $ids = $resultado['inactivar']->pluck('id')->map(fn ($id) => (int) $id)->all();
        $ret = InactivarArticulosCompraOcCc85Support::inactivar(
            $ids,
            $articuloEstadoRepository,
            $usuarioId,
            false,
        );

        $this->info('Inactivados: '.$ret['inactivados']);
        foreach ($ret['errores'] as $msg) {
            $this->warn($msg);
        }

        return $ret['errores'] === [] ? self::SUCCESS : self::FAILURE;
    }

    private function exportarCsv(string $path, $filas): void
    {
        $dir = dirname($path);
        if ($dir !== '' && $dir !== '.' && ! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $handle = fopen($path, 'wb');
        if ($handle === false) {
            throw new \RuntimeException('No se pudo crear el archivo: '.$path);
        }

        fputcsv($handle, ['id', 'sku', 'descripcion', 'tipoarticulo'], ',', '"', '\\');
        foreach ($filas as $row) {
            fputcsv($handle, [
                $row->id,
                $row->sku,
                $row->descripcion,
                $row->tipo_nombre,
            ], ',', '"', '\\');
        }

        fclose($handle);
    }
}
