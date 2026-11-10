<?php

namespace App\Console\Commands;

use App\Support\Configuracion\EntornoEmpresaSupport;
use App\Support\Ventas\GastronomiaCrown\GastronomiaCrownCategoriaAreaSyncSupport;
use Illuminate\Console\Command;

class SincronizarCategoriaAreaComandaCrown extends Command
{
    protected $signature = 'ventas:sincronizar-categoria-area-comanda-crown
                            {--dry-run : Solo analiza, no escribe}
                            {--ejecutar : Persiste los vínculos categoría ↔ área}';

    protected $description = 'Crown: sincroniza área de comanda desde Anita stkagr.stka_area hacia categoria_area_comanda';

    public function handle(): int
    {
        if (! EntornoEmpresaSupport::esCrown()) {
            $this->error('Solo corre en EMPRESA=CROWN.');

            return self::FAILURE;
        }

        $ejecutar = (bool) $this->option('ejecutar');
        if ($ejecutar && $this->option('dry-run')) {
            $this->error('No combine --dry-run con --ejecutar.');

            return self::FAILURE;
        }
        if (! $ejecutar) {
            $this->warn('Modo dry-run (pase --ejecutar para persistir).');
        }

        $resultado = GastronomiaCrownCategoriaAreaSyncSupport::sincronizar($ejecutar);
        $this->info($resultado['mensaje']);

        $filas = collect($resultado['detalle'])
            ->filter(fn (array $d) => in_array($d['accion'], ['crear', 'actualizar', 'sin_categoria', 'sin_area_erp'], true))
            ->values();
        if ($filas->isNotEmpty()) {
            $this->table(
                ['Código', 'Nombre', 'Área Anita', 'Acción'],
                $filas->map(fn (array $d) => [$d['codigo'], $d['nombre'], $d['area_anita'], $d['accion']])->all()
            );
        }

        return self::SUCCESS;
    }
}
