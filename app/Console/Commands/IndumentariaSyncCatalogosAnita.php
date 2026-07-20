<?php

namespace App\Console\Commands;

use App\Models\Stock\Color;
use App\Models\Stock\Talle;
use App\Repositories\Sueldos\Prenda_SueldosRepositoryInterface;
use App\Services\Sueldos\DotacionAgrupamientoAnitaSync;
use Illuminate\Console\Command;

/**
 * Importa desde Anita (base sueldos) todo el módulo de indumentaria: catálogos (color, talle),
 * prendas + matriz de variantes (prendart) y dotación por agrupamiento/sexo (prendxagr).
 * Sync pull unilateral e idempotente: solo agrega lo que falta. Nunca escribe hacia Anita.
 */
class IndumentariaSyncCatalogosAnita extends Command
{
    protected $signature = 'indumentaria:sync-catalogos-anita {--solo-catalogos : Sincroniza únicamente color y talle}';

    protected $description = 'Sincroniza indumentaria desde Anita: color, talle, prendas, variantes y dotación.';

    public function handle(
        Prenda_SueldosRepositoryInterface $prendaRepo,
        DotacionAgrupamientoAnitaSync $dotacionSync
    ): int {
        @ini_set('memory_limit', '-1');
        @ini_set('max_execution_time', '0');

        $this->info('Sincronizando colores desde Anita...');
        $colores = (new Color)->sincronizarCatalogoAnita();
        $this->line("  Colores nuevos importados: {$colores} (total: ".Color::count().')');

        $this->info('Sincronizando talles desde Anita...');
        $talles = (new Talle)->sincronizarConAnita();
        $this->line("  Talles nuevos importados: {$talles} (total: ".Talle::count().')');

        if ($this->option('solo-catalogos')) {
            $this->info('Listo (solo catálogos).');

            return self::SUCCESS;
        }

        $this->info('Sincronizando prendas y variantes (prenda/prendart) desde Anita...');
        $rp = $prendaRepo->sincronizarConAnita();
        if (! empty($rp['errores'])) {
            foreach ($rp['errores'] as $e) {
                $this->warn('  '.$e);
            }
        }
        $this->line("  Prendas nuevas: {$rp['importados']}, existentes: {$rp['omitidos']}, variantes cargadas: {$rp['variantes']} (en Anita: {$rp['en_anita']}).");

        $this->info('Sincronizando dotación por agrupamiento/sexo (prendxagr) desde Anita...');
        $rd = $dotacionSync->sincronizar();
        if (! empty($rd['errores'])) {
            foreach ($rd['errores'] as $e) {
                $this->warn('  '.$e);
            }
        }
        $this->line("  Dotación nueva: {$rd['importados']}, existentes: {$rd['omitidos']}, sin mapeo: {$rd['sin_mapeo']} (en Anita: {$rd['en_anita']}).");

        $this->info('Listo.');

        return self::SUCCESS;
    }
}
