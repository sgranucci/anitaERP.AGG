<?php

namespace App\Console\Commands\Compras;

use App\Models\Compras\Proveedor;
use App\Services\Compras\ProveedorCuentacorrienteCuotasAbiertasService;
use Illuminate\Console\Command;

class RepararCcCuotasAbiertasCommand extends Command
{
    protected $signature = 'compras:reparar-cc-cuotas-abiertas
                            {--proveedor= : Código Anita del proveedor}
                            {--aplicar : Graba las filas de cuenta corriente. Sin esto solo muestra el plan}';

    protected $description = 'Crea un movimiento de cuenta corriente por cada cuota abierta que el import dejó colapsada en una sola fila';

    public function handle(ProveedorCuentacorrienteCuotasAbiertasService $service): int
    {
        $codigo = trim((string) $this->option('proveedor'));
        $proveedorId = null;
        if ($codigo !== '') {
            $proveedorId = (int) (Proveedor::query()->where('codigo', $codigo)->value('id') ?: 0);
            if ($proveedorId <= 0) {
                $this->error('No está el proveedor '.$codigo);

                return self::FAILURE;
            }
        }

        $aplicar = (bool) $this->option('aplicar');
        $informe = $service->reparar($proveedorId, $aplicar);
        if ($informe === []) {
            $this->info('No hay facturas con cuotas abiertas colapsadas en un solo movimiento.');

            return self::SUCCESS;
        }

        $this->table(
            ['Comprobante', 'Proveedor', 'Número', 'Filas nuevas', 'Reasignada', 'Omitido'],
            array_map(static fn (array $f) => [
                $f['comprobante_id'],
                $f['codigo'],
                $f['numero'],
                $f['creadas'],
                $f['reasignada'] ? 'sí' : '',
                $f['omitido'] ?? '',
            ], $informe)
        );
        $this->line($aplicar ? 'Aplicado.' : 'Plan. Para grabar: --aplicar');

        return self::SUCCESS;
    }
}
