<?php

namespace App\Console\Commands;

use App\Support\Ventas\ClienteAnitaNumeracionSupport;
use Illuminate\Console\Command;

class ClienteSincronizarNumeradorCliAnitaCommand extends Command
{
    protected $signature = 'cliente:sincronizar-numerador-cli-anita {--alinear-max-global : Peligroso: sube numerador a max(ERP, climae)}';

    protected $description = 'Informe numerador CLI Anita (t_comp). Por defecto no modifica Anita.';

    public function handle(): int
    {
        if (! ClienteAnitaNumeracionSupport::estaHabilitada()) {
            $this->warn('Numeración Anita CLI no habilitada para esta empresa.');

            return self::SUCCESS;
        }

        $resultado = ClienteAnitaNumeracionSupport::sincronizarNumeradorCliGlobal(
            (bool) $this->option('alinear-max-global')
        );

        $this->info('Numerador CLI Anita (t_comp CLI → num_clave '.$resultado['num_clave'].')');
        $this->table(['Fuente', 'Valor'], [
            ['max código ERP', $resultado['max_erp']],
            ['max clim_cliente Anita', $resultado['max_climae']],
            ['num_ult_numero antes', $resultado['antes']],
            ['num_ult_numero después', $resultado['despues']],
            ['siguiente código', $resultado['despues'] + 1],
        ]);

        if ($resultado['despues'] > $resultado['antes']) {
            $this->info('Numerador actualizado en Anita.');
        } else {
            $this->comment('Numerador sin cambios (use --alinear-max-global solo si necesita empatar max global).');
        }

        return self::SUCCESS;
    }
}
