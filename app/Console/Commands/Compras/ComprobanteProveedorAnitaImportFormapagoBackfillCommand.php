<?php

namespace App\Console\Commands\Compras;

use App\Support\Compras\AnitaImport\ComprobanteProveedorAnitaImportFormapagoBackfillSupport;
use Illuminate\Console\Command;

/**
 * Corrige formapago_id=Efectivo en cuotas importadas desde Anita.
 * Default dry-run; requiere --ejecutar para persistir.
 */
class ComprobanteProveedorAnitaImportFormapagoBackfillCommand extends Command
{
    protected $signature = 'compras:corregir-formapago-import-anita
                            {--desde= : Fecha comprobante ISO desde (opcional)}
                            {--hasta= : Fecha comprobante ISO hasta (opcional)}
                            {--empresa_id= : Solo esa empresa ERP}
                            {--ejecutar : Persiste; sin esto solo dry-run}';

    protected $description = 'Corrige cuotas ANITA_IMPORT en Efectivo usando medio de la OC o del proveedor';

    public function handle(): int
    {
        $desde = $this->option('desde') !== null ? (string) $this->option('desde') : null;
        $hasta = $this->option('hasta') !== null ? (string) $this->option('hasta') : null;
        $empresaId = $this->option('empresa_id') !== null ? (int) $this->option('empresa_id') : null;
        $ejecutar = (bool) $this->option('ejecutar');

        $rango = ($desde ?: '…').'..'.($hasta ?: '…');
        $emp = $empresaId && $empresaId > 0 ? " empresa_id={$empresaId}" : '';
        $this->info(($ejecutar ? 'EJECUTAR' : 'DRY-RUN')." formapago import Anita {$rango}{$emp}");

        $stats = ComprobanteProveedorAnitaImportFormapagoBackfillSupport::ejecutar(
            $desde,
            $hasta,
            $empresaId && $empresaId > 0 ? $empresaId : null,
            ! $ejecutar,
        );

        $filasDestino = [];
        foreach ($stats['por_destino'] as $abrev => $cant) {
            $filasDestino[] = [$abrev, $cant];
        }

        $this->table(['Métrica', 'Cantidad'], [
            ['Cuotas candidatas (ANITA_IMPORT + Efectivo)', $stats['candidatas']],
            ['A actualizar', $stats['a_actualizar']],
            ['  vía OC', $stats['via_oc']],
            ['  vía proveedor', $stats['via_proveedor']],
            ['Quedan Efectivo (OC/prov sin otro medio)', $stats['quedan_efectivo']],
            ['Actualizadas', $stats['actualizadas']],
        ]);

        if ($filasDestino !== []) {
            $this->table(['Destino formapago', 'Cantidad'], $filasDestino);
        }

        if ($stats['muestra'] !== []) {
            $this->line('Muestra (hasta 25):');
            $this->table(
                ['Cuota', 'CP', 'Etiqueta', 'OC', 'Nuevo FP', 'Origen'],
                array_map(static fn (array $r) => [
                    $r['cuota_id'],
                    $r['cp_id'],
                    $r['etiqueta'],
                    $r['ordencompra_id'] ?? '',
                    $r['formapago_id'],
                    $r['origen'],
                ], $stats['muestra'])
            );
        }

        if (! $ejecutar) {
            $this->comment('Dry-run: no se grabó nada. Relanzá con --ejecutar para persistir.');
        }

        return self::SUCCESS;
    }
}
