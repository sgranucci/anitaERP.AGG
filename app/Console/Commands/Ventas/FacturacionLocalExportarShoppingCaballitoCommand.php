<?php

declare(strict_types=1);

namespace App\Console\Commands\Ventas;

use App\Services\Ventas\FacturacionLocal\FacturacionLocalShoppingCaballitoExportService;
use App\Support\Configuracion\EntornoEmpresaSupport;
use App\Support\Ventas\FacturacionLocal\FacturacionLocalShoppingCaballitoSupport;
use Carbon\Carbon;
use Illuminate\Console\Command;

class FacturacionLocalExportarShoppingCaballitoCommand extends Command
{
    protected $signature = 'facturacion-local:exportar-shopping-caballito
                            {--fecha= : Día de ventas Y-m-d. Vacío = hoy}
                            {--forzar : Reenvía aunque ese día ya se haya mandado}
                            {--dry-run : Arma el archivo y no envía ni graba el log}';

    protected $description = 'Arma el TXT de ventas de Caballito para el shopping y lo envía por correo';

    public function handle(FacturacionLocalShoppingCaballitoExportService $servicio): int
    {
        if (! EntornoEmpresaSupport::esFerli()) {
            $this->info('Exportación shopping Caballito: solo corre en Ferli.');

            return self::SUCCESS;
        }

        if (! $this->option('dry-run') && ! $this->option('forzar') && ! FacturacionLocalShoppingCaballitoSupport::habilitado()) {
            $this->info('Exportación shopping Caballito deshabilitada en Parámetros Facturación Local.');

            return self::SUCCESS;
        }

        $fecha = trim((string) $this->option('fecha'));
        if ($fecha === '') {
            $fecha = Carbon::now((string) config('app.timezone'))->toDateString();
        }

        $resultado = $servicio->ejecutar($fecha, (bool) $this->option('forzar'), (bool) $this->option('dry-run'));
        $this->line($resultado['mensaje']);

        if ($resultado['estado'] === 'omitido' || $resultado['ok']) {
            return self::SUCCESS;
        }

        return self::FAILURE;
    }
}
