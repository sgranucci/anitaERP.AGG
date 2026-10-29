<?php

namespace App\Console\Commands\Compras;

use App\Services\Compras\ComprobanteProveedorAnitaSyncBridgeService;
use App\Support\Compras\ComprobanteProveedorAnitaBridgeGate;
use Illuminate\Console\Command;

class ComprobanteProveedorSincronizarBridgeCommand extends Command
{
    protected $signature = 'comprobante-proveedor:sincronizar-bridge
                            {--dry-run : Lista las facturas pendientes sin grabar en el bridge}
                            {--limite= : Máximo de comprobantes a procesar}';

    protected $description = 'Sincroniza al bridge Anita las facturas de proveedor contabilizadas en el ERP y todavía pendientes';

    public function handle(ComprobanteProveedorAnitaSyncBridgeService $service): int
    {
        if (! ComprobanteProveedorAnitaBridgeGate::syncBridgeProgramadoHabilitado()) {
            $this->warn('Barrido solo corre en Crown, o está deshabilitado (sync_bridge.habilitada).');

            return self::SUCCESS;
        }

        $dryRun = (bool) $this->option('dry-run');
        $limite = trim((string) ($this->option('limite') ?? ''));
        $limiteInt = $limite !== '' ? max(1, (int) $limite) : null;

        $this->line(sprintf(
            'Barrido facturas ERP → bridge Anita%s%s',
            $dryRun ? ' · dry-run' : '',
            $limiteInt !== null ? ' · límite '.$limiteInt : '',
        ));

        $informe = $service->barrer($dryRun, $limiteInt);

        if ($informe['omitida']) {
            $this->warn((string) $informe['motivo']);

            return self::SUCCESS;
        }

        $this->table(
            ['Concepto', 'Cantidad'],
            [
                ['Pendientes', (string) $informe['total']],
                [$dryRun ? 'A sincronizar' : 'Sincronizadas', (string) ($dryRun ? $informe['total'] : $informe['sincronizadas'])],
                ['Errores', (string) count($informe['errores'])],
            ],
        );

        foreach ($informe['filas'] as $fila) {
            $this->info(sprintf(
                '#%d %s · %s',
                (int) $fila['id'],
                (string) $fila['etiqueta'],
                (string) $fila['estado'],
            ));
        }

        foreach ($informe['errores'] as $error) {
            $this->error(sprintf(
                '#%d %s: %s',
                (int) $error['id'],
                (string) $error['etiqueta'],
                (string) $error['mensaje'],
            ));
        }

        return $informe['errores'] !== [] ? self::FAILURE : self::SUCCESS;
    }
}
