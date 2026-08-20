<?php

namespace App\Console\Commands;

use App\Services\Ventas\PedidoFacturaAnitaRegrabacionService;
use App\Support\Configuracion\EntornoEmpresaSupport;
use App\Support\Ventas\PedidoFacturaAnitaSemaforoSupport;
use Illuminate\Console\Command;

class RegrabarAnitaPedidoCommand extends Command
{
    protected $signature = 'ventas:regrabar-anita-pedido
                            {--ejecutar : Regraba en Anita (sin este flag solo analiza)}
                            {--limite=20 : Máximo de facturas a procesar}';

    protected $description = 'Regraba en Anita facturas de pedido de El Bierzo con semáforo levantado (ERP/ARCA OK, Anita pendiente o en error)';

    public function handle(PedidoFacturaAnitaRegrabacionService $service): int
    {
        if (! EntornoEmpresaSupport::esElBierzo()) {
            $this->warn('Este proceso solo corre en El Bierzo.');

            return self::SUCCESS;
        }

        $ejecutar = (bool) $this->option('ejecutar');
        $limite = max(1, (int) $this->option('limite'));

        if (! $ejecutar && ! PedidoFacturaAnitaSemaforoSupport::levantado()) {
            $this->info('Semáforo bajado: no hay desfasajes pendientes.');

            return self::SUCCESS;
        }

        if (! $ejecutar) {
            $this->warn('Dry-run: no se escribe en Anita. Use --ejecutar para regrabar.');
        }

        $resultado = $service->procesarPendientes($ejecutar, $limite);

        $this->table(
            ['Venta', 'Pedido', 'Estado', 'Intentos', 'Acción', 'Mensaje'],
            array_map(static function (array $fila): array {
                return [
                    $fila['venta_id'],
                    $fila['pedido_id'] ?? '',
                    $fila['estado'],
                    $fila['intentos'],
                    $fila['accion'],
                    $fila['mensaje'],
                ];
            }, $resultado['detalle']),
        );

        $this->line(sprintf(
            'Semáforo: %s | Abiertos: %d | Agotados: %d | Procesados: %d | OK: %d | Error: %d | Omitidos: %d',
            $resultado['semaforo'] ? 'levantado' : 'bajado',
            $resultado['abiertos'],
            $resultado['agotados'] ?? 0,
            $resultado['procesados'],
            $resultado['ok'],
            $resultado['error'],
            $resultado['omitidos'],
        ));
        if (! empty($resultado['semaforo'])) {
            $this->comment('El semáforo sigue levantado: queda al menos una factura pendiente o en error.');
        }

        return self::SUCCESS;
    }
}
