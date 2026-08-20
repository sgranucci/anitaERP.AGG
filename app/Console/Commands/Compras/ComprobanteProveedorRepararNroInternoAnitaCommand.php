<?php

namespace App\Console\Commands\Compras;

use App\ApiAnita;
use App\Services\Compras\ComprobanteProveedorAnitaNroInternoColisionRepararService;
use Illuminate\Console\Command;

class ComprobanteProveedorRepararNroInternoAnitaCommand extends Command
{
    protected $signature = 'comprobante-proveedor:reparar-nro-interno-anita
                            {--id= : Solo un comprobante ERP}
                            {--dry-run : Analiza sin escribir (default si no hay --ejecutar)}
                            {--ejecutar : Reasigna internos ERP y recorta concmov propio}';

    protected $description = 'Reasigna nro. interno Anita de facturas ERP en colisión; no borra concmov de la otra factura';

    public function handle(ComprobanteProveedorAnitaNroInternoColisionRepararService $service): int
    {
        $ejecutar = (bool) $this->option('ejecutar');
        $dryRun = ! $ejecutar || (bool) $this->option('dry-run');
        if ($ejecutar && (bool) $this->option('dry-run')) {
            $this->error('No combine --ejecutar con --dry-run.');

            return self::FAILURE;
        }

        $idOpt = $this->option('id');
        $id = ($idOpt !== null && $idOpt !== '') ? (int) $idOpt : null;

        $this->line('Bridge: '.ApiAnita::urlBridge());
        $this->line($dryRun ? 'DRY-RUN: no se escribe Anita ni ERP.' : 'EJECUTAR: reasigna internos ERP.');
        $this->line('concmov: solo se borran líneas que coinciden con concepto+importe de la factura ERP.');

        $stats = $service->ejecutar($dryRun, $id);

        $filas = [];
        foreach ($stats['detalle'] as $item) {
            $filas[] = [
                $item['comprobante_id'],
                $item['etiqueta'],
                $item['interno_viejo'],
                $item['nuevo_interno'] ?: '—',
                $item['accion'],
                implode(' | ', $item['otras'] ?? []) ?: '—',
                $item['motivo'],
            ];
        }

        if ($filas !== []) {
            $this->table(
                ['ERP id', 'Factura', 'Interno viejo', 'Nuevo', 'Acción', 'Otra compra (intacta)', 'Motivo'],
                $filas
            );
        }

        $this->table(['Métrica', 'Cantidad'], [
            ['Candidatas SYNC_OK', $stats['candidatas']],
            ['A reasignar / reparadas', $dryRun
                ? collect($stats['detalle'])->where('accion', 'reasignar')->count()
                : $stats['reparadas']],
            ['Omitidas (sin colisión)', $stats['omitidas']],
            ['Errores / ambiguas', $stats['errores']],
        ]);

        return $stats['errores'] > 0 ? self::FAILURE : self::SUCCESS;
    }
}
