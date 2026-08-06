<?php

namespace App\Console\Commands;

use App\Services\Stock\RecepcionProveedorImpuestoInternoDevolucionReparacionService;
use Illuminate\Console\Command;

class RecepcionProveedorCorregirImpuestoInternoDevolucionesCommand extends Command
{
    protected $signature = 'recepcion-proveedor:corregir-impuesto-interno-devoluciones
                            {--id= : Solo una devolución por ID ERP}
                            {--limite= : Máximo de devoluciones a procesar}
                            {--dry-run : Solo lista candidatas, no modifica}
                            {--forzar : Omite validación de período contable cerrado (reparación)}';

    protected $description = 'Corrige devoluciones sin impuesto interno (campo, asiento ERP/ctamov y recepmov Anita)';

    public function handle(RecepcionProveedorImpuestoInternoDevolucionReparacionService $service): int
    {
        $opciones = [
            'id' => $this->option('id') ? (int) $this->option('id') : null,
            'limite' => $this->option('limite') ? (int) $this->option('limite') : null,
            'dry_run' => (bool) $this->option('dry-run'),
            'forzar' => (bool) $this->option('forzar'),
        ];

        if ($opciones['dry_run']) {
            $this->warn('Dry-run: no se modificarán devoluciones ni asientos.');
        }

        if ($opciones['forzar'] && ! $opciones['dry_run']) {
            $this->warn('Forzar: se omitirá la validación de período contable cerrado al recuadrar ctamov.');
        }

        $total = $service->contarCandidatas($opciones['id']);
        $this->info("Devoluciones candidatas (origen con II, devolución sin II): {$total}");

        if ($total === 0) {
            return self::SUCCESS;
        }

        $stats = $service->ejecutar($opciones);

        $filas = array_values(array_filter(
            $stats['detalle'],
            static fn (array $row): bool => in_array($row['estado'] ?? '', ['pendiente', 'reparada', 'error'], true)
        ));

        if ($filas !== []) {
            $this->table(
                ['COM', 'OC', 'Origen', 'II ant.', 'II nuevo', 'Estado', 'Msg'],
                array_map(static fn (array $row) => [
                    $row['numerorecepcion'] ?? '',
                    $row['ordencompra'] ?? '',
                    $row['origen_nro'] ?? '',
                    isset($row['ii_anterior']) ? number_format((float) $row['ii_anterior'], 2, '.', '') : '',
                    isset($row['ii_nuevo']) ? number_format((float) $row['ii_nuevo'], 2, '.', '') : '',
                    $row['estado'] ?? '',
                    $row['mensaje'] ?? '',
                ], $filas)
            );
        }

        $this->table(['Métrica', 'Cantidad'], [
            ['Candidatas', $stats['candidatas']],
            ['Pendientes (dry-run)', $stats['pendientes']],
            ['Reparadas', $stats['reparadas']],
            ['Omitidas', $stats['omitidas']],
            ['Errores', $stats['errores']],
        ]);

        return $stats['errores'] > 0 ? self::FAILURE : self::SUCCESS;
    }
}
