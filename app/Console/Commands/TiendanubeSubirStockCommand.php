<?php

namespace App\Console\Commands;

use App\Models\Ventas\TiendanubeStockSubida;
use App\Services\Ventas\Tiendanube\TiendanubeStockSubidaService;
use App\Support\Configuracion\EntornoEmpresaSupport;
use Illuminate\Console\Command;

class TiendanubeSubirStockCommand extends Command
{
    protected $signature = 'tiendanube:subir-stock
                            {--store= : Store id. Vacío = todas las tiendas con la subida activa}
                            {--origen=cron : cron, manual o simulacion}
                            {--usuario= : Usuario que disparó la subida manual}
                            {--que=stock : stock, precios o ambos}';

    protected $description = 'Sube stock a Tiendanube. Los precios salen solo con --que=precios o --que=ambos.';

    public function handle(TiendanubeStockSubidaService $servicio): int
    {
        if (! EntornoEmpresaSupport::esFerli()) {
            $this->warn('Solo aplica en entorno Ferli.');

            return self::SUCCESS;
        }

        $origen = strtolower(trim((string) $this->option('origen')));
        if (! in_array($origen, [
            TiendanubeStockSubida::ORIGEN_CRON,
            TiendanubeStockSubida::ORIGEN_MANUAL,
            TiendanubeStockSubida::ORIGEN_SIMULACION,
        ], true)) {
            $origen = TiendanubeStockSubida::ORIGEN_CRON;
        }

        $store = trim((string) $this->option('store'));
        $usuario = (int) $this->option('usuario');
        $soloHora = $origen === TiendanubeStockSubida::ORIGEN_CRON;
        $que = strtolower(trim((string) $this->option('que')));
        if (! in_array($que, ['stock', 'precios', 'ambos'], true)) {
            $que = 'stock';
        }
        if ($origen === TiendanubeStockSubida::ORIGEN_CRON) {
            $que = 'stock';
        }
        $incluirStock = $que !== 'precios';
        $incluirPrecios = $que !== 'stock';

        $this->info(match ($que) {
            'precios' => 'Subiendo precios a Tiendanube…',
            'ambos' => 'Subiendo stock y precios a Tiendanube…',
            default => $origen === TiendanubeStockSubida::ORIGEN_SIMULACION
                ? 'Previsualizando stock y precios. No se escribe en Tiendanube…'
                : 'Subiendo stock a Tiendanube…',
        });

        $resultado = $servicio->ejecutar(
            $origen,
            $usuario > 0 ? $usuario : null,
            $store !== '' ? $store : null,
            $soloHora,
            $incluirPrecios,
            $incluirStock,
        );

        $this->line($resultado['mensaje']);
        foreach ($resultado['subidas'] as $id) {
            $subida = TiendanubeStockSubida::query()->find($id);
            if (! $subida) {
                continue;
            }
            $this->line(sprintf(
                'Tienda %s: %s. OK %d, error %d, omitidas %d.',
                $subida->store_id,
                $subida->estado,
                (int) $subida->variantes_ok,
                (int) $subida->variantes_error,
                (int) $subida->variantes_omitidas
            ));
        }

        return ($resultado['ok'] ?? false) ? self::SUCCESS : self::FAILURE;
    }
}
