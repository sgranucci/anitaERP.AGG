<?php

declare(strict_types=1);

namespace App\Console\Commands\Contable;

use App\Support\Contable\HomologarCuentacontableAsientoSupport;
use Illuminate\Console\Command;

class HomologarCuentasAsientoCommand extends Command
{
    protected $signature = 'contable:homologar-cuentas-asiento {--dry-run : Solo cuenta las líneas a corregir}';

    protected $description = 'Pasa las líneas de asiento imputadas al plan de otra empresa a la cuenta del mismo código en la empresa del asiento';

    public function handle(HomologarCuentacontableAsientoSupport $support): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $resultado = $support->ejecutar($dryRun);

        $this->info('Líneas con cuenta de otra empresa: '.$resultado['lineas']);
        if ($dryRun) {
            $this->comment('Dry-run: no se escribió nada.');

            return self::SUCCESS;
        }

        $this->info('Líneas actualizadas: '.$resultado['actualizadas']);
        foreach ($resultado['errores'] as $error) {
            $this->error($error);
        }

        return $resultado['errores'] === [] ? self::SUCCESS : self::FAILURE;
    }
}
