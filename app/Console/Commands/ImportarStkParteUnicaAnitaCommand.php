<?php

namespace App\Console\Commands;

use App\Models\Stock\Articulo;
use App\Models\Stock\Articulo_ParteUnica;
use App\Support\Stock\StkParteUnicaAnitaBridgeSupport;
use Illuminate\Console\Command;

class ImportarStkParteUnicaAnitaCommand extends Command
{
    protected $signature = 'stock:importar-stk-parte-unica-anita
                            {--sku= : Importar solo un SKU}
                            {--lote=2000 : Tamaño de lote por rango stkpu_id}
                            {--dry-run : Solo contadores, sin grabar}';

    protected $description = 'Importa stk_parte_unica (base_admin) desde Anita hacia articulo_parte_unica';

    public function handle(): int
    {
        $skuFiltro = $this->option('sku');
        $dryRun = (bool) $this->option('dry-run');
        $tamanoLote = max(500, (int) $this->option('lote'));

        $sistema = StkParteUnicaAnitaBridgeSupport::sistema();
        $tabla = StkParteUnicaAnitaBridgeSupport::tabla();
        $this->info("Consultando {$tabla} en Anita (sistema: {$sistema})…");

        $importados = 0;
        $omitidos = 0;
        $sinArticulo = 0;
        $filasAnita = 0;

        $procesar = function (array $filas) use ($skuFiltro, $dryRun, &$importados, &$omitidos, &$sinArticulo, &$filasAnita) {
            foreach ($filas as $fila) {
                $filasAnita++;
                $numeroparte = (int) ($fila->stkpu_id ?? $fila->STKPU_ID ?? 0);
                $sku = trim((string) ($fila->stkpu_articulo ?? $fila->STKPU_ARTICULO ?? ''));

                if ($numeroparte <= 0 || $sku === '') {
                    $omitidos++;

                    continue;
                }

                if ($skuFiltro && ltrim($sku, '0') !== ltrim(trim($skuFiltro), '0')) {
                    continue;
                }

                if (Articulo_ParteUnica::query()->where('numeroparte', $numeroparte)->exists()) {
                    $omitidos++;

                    continue;
                }

                $articulo = Articulo::query()->where('sku', ltrim($sku, '0'))->first();
                if (! $articulo) {
                    $sinArticulo++;

                    continue;
                }

                if (! $dryRun) {
                    Articulo_ParteUnica::create([
                        'articulo_id' => $articulo->id,
                        'numeroparte' => $numeroparte,
                    ]);
                }

                $importados++;
            }
        };

        if ($skuFiltro) {
            $sku = str_pad(trim((string) $skuFiltro), 13, ' ', STR_PAD_RIGHT);
            $filas = StkParteUnicaAnitaBridgeSupport::listarDesdeAnita(
                " WHERE stkpu_articulo = '".addslashes($sku)."'"
            );
            $procesar($filas);
        } else {
            $maxId = StkParteUnicaAnitaBridgeSupport::maxNumeroparteAnita();
            $this->line("stkpu_id máximo en Anita: {$maxId}");

            StkParteUnicaAnitaBridgeSupport::importarEnLotes(
                function (array $filas, int $desde, int $hasta) use ($procesar) {
                    $this->line("  Lote stkpu_id {$desde}..".($hasta - 1).' → '.count($filas).' filas');
                    $procesar($filas);
                },
                $tamanoLote
            );
        }

        $this->table(['Métrica', 'Cantidad'], [
            ['Sistema Anita', $sistema],
            ['Tabla', $tabla],
            ['Filas leídas Anita', $filasAnita],
            ['Importadas ERP', $importados],
            ['Omitidas (duplicadas/vacías)', $omitidos],
            ['Sin artículo ERP', $sinArticulo],
        ]);

        if ($dryRun) {
            $this->comment('Dry-run: no se grabó nada.');
        }

        return self::SUCCESS;
    }
}
