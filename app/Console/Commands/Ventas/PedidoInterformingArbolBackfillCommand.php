<?php

declare(strict_types=1);

namespace App\Console\Commands\Ventas;

use App\Services\Ventas\PedidoInterformingArbolBackfillService;
use App\Support\Ventas\PedidoInterformingSupport;
use Illuminate\Console\Command;

final class PedidoInterformingArbolBackfillCommand extends Command
{
    protected $signature = 'ventas:backfill-arbol-pedidos-interforming
                            {--pedido= : Solo este pedido_id}
                            {--dry-run : Solo analiza (default si no hay --ejecutar)}
                            {--ejecutar : Dispara el árbol y crea pendientes}';

    protected $description = 'INTERFORMING: genera pendientes PE para pedidos sin movimiento de árbol';

    public function handle(PedidoInterformingArbolBackfillService $service): int
    {
        if (! PedidoInterformingSupport::esInterforming()) {
            $this->error('Solo aplica en entorno INTERFORMING.');

            return self::FAILURE;
        }

        $ejecutar = (bool) $this->option('ejecutar');
        $dryRunFlag = (bool) $this->option('dry-run');
        if ($ejecutar && $dryRunFlag) {
            $this->error('No combine --ejecutar con --dry-run.');

            return self::FAILURE;
        }

        $dryRun = ! $ejecutar;
        $pedidoOpt = $this->option('pedido');
        $pedidoId = ($pedidoOpt !== null && $pedidoOpt !== '') ? (int) $pedidoOpt : null;

        $this->line($dryRun ? 'DRY-RUN (no escribe)' : 'EJECUTAR (crea pendientes)');

        $resultado = $service->ejecutar(! $dryRun, $pedidoId);

        $this->table(['Concepto', 'Valor'], [
            ['Candidatos', (string) $resultado['candidatos']],
            [$dryRun ? 'A disparar' : 'Disparados', (string) ($dryRun ? $resultado['candidatos'] : $resultado['disparados'])],
            ['Con pendiente tras disparo', (string) $resultado['con_pendiente']],
            ['Sin pendiente/nivel', (string) $resultado['sin_nivel']],
            ['Errores', (string) count($resultado['errores'])],
        ]);

        if ($resultado['detalle'] !== []) {
            $this->table(
                ['ID', 'Código', 'Resultado', 'Nivel'],
                array_map(static function (array $row): array {
                    return [
                        (string) $row['id'],
                        (string) $row['codigo'],
                        (string) $row['resultado'],
                        isset($row['nivel']) ? (string) $row['nivel'] : '',
                    ];
                }, $resultado['detalle'])
            );
        }

        foreach ($resultado['errores'] as $err) {
            $this->error(sprintf('Pedido %d (%s): %s', $err['id'], $err['codigo'], $err['error']));
        }

        if ($dryRun) {
            $this->comment('Ejecute con --ejecutar para crear los pendientes (mismo alcance).');
        }

        return count($resultado['errores']) === 0 ? self::SUCCESS : self::FAILURE;
    }
}
