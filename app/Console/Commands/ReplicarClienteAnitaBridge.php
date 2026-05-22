<?php

namespace App\Console\Commands;

use App\ApiAnita;
use App\Repositories\Ventas\ClienteRepositoryInterface;
use Illuminate\Console\Command;

class ReplicarClienteAnitaBridge extends Command
{
    protected $signature = 'anita:replicar-cliente
                            {codigo : Código de cliente en el ERP (ej. 10815)}
                            {--sql : Solo muestra el INSERT en climae sin llamar al bridge}';

    protected $description = 'Replica en Informix (climae) un cliente ya grabado en el ERP vía bridge HTTP';

    public function handle(ClienteRepositoryInterface $clienteRepository): int
    {
        $codigo = (string) $this->argument('codigo');

        $this->line('Bridge: '.ApiAnita::urlBridge());

        try {
            if ($this->option('sql')) {
                $sql = $clienteRepository->previewInsertClimaeSqlPorCodigo($codigo);
                $this->line('');
                $this->line($sql);
                $this->line('');
                $this->comment('Revise comillas y cantidad de valores. Copie apiERP.php actualizado al servidor .4 si ve parse error en línea 71.');

                return self::SUCCESS;
            }

            $resultado = $clienteRepository->replicarClienteEnAnitaPorCodigo($codigo);
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info('Cliente '.$codigo.' '.$resultado.' en Anita (tabla climae).');

        return self::SUCCESS;
    }
}
