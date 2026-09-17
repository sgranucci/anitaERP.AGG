<?php

namespace App\Console\Commands;

use App\Services\Ventas\Ferli\PedidoRepararLineasHuerfanasDesdeL8Service;
use App\Support\Configuracion\EntornoEmpresaSupport;
use Illuminate\Console\Command;

class PedidoRepararHuerfanosL8Command extends Command
{
    protected $signature = 'ventas:reparar-pedidos-huerfanos-l8
                            {--pedido= : Solo un pedido_id}
                            {--dry-run : Lista el impacto sin grabar}
                            {--ejecutar : Persiste las líneas faltantes desde L8}';

    protected $description = 'Completa en L12 las líneas de pedidos con cabecera pero incompletos/vacíos respecto de L8 (Ferli).';

    public function handle(PedidoRepararLineasHuerfanasDesdeL8Service $service): int
    {
        if (! EntornoEmpresaSupport::esFerli()) {
            $this->warn('Solo aplica a Calzados Ferli.');

            return self::SUCCESS;
        }

        $dryRun = (bool) $this->option('dry-run');
        $ejecutar = (bool) $this->option('ejecutar');
        if ($dryRun && $ejecutar) {
            $this->error('No combine --ejecutar con --dry-run.');

            return self::FAILURE;
        }
        if (! $dryRun && ! $ejecutar) {
            $this->warn('Sin flags: modo dry-run.');
            $dryRun = true;
        }

        $pedido = $this->option('pedido');
        $soloId = ($pedido !== null && $pedido !== '') ? (int) $pedido : null;

        try {
            $stats = $service->reparar($dryRun, $soloId);
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info(sprintf(
            '%s · fuente %s · pedidos %d · comb +%d · talles +%d · estados +%d · precios %d · ot omitidas %d',
            $dryRun ? 'DRY-RUN' : 'EJECUTADO',
            $stats['fuente'],
            $stats['pedidos'],
            $stats['insert_combinacion'],
            $stats['insert_talle'],
            $stats['insert_estado'],
            $stats['precios_aplicados'],
            $stats['ot_omitidas']
        ));

        foreach ($stats['detalle'] as $line) {
            $this->line($line);
        }
        foreach ($stats['errores'] as $err) {
            $this->error($err);
        }

        return self::SUCCESS;
    }
}
