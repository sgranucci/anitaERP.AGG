<?php

namespace App\Console\Commands;

use App\Services\Stock\RecepcionProveedorAsientoRecuadreService;
use Illuminate\Console\Command;

class RecepcionProveedorRecuadrarAsientosContablesCommand extends Command
{
    protected $signature = 'recepcion-proveedor:recuadrar-asientos-contables
                            {--id= : Solo una recepción por ID ERP}
                            {--limite= : Máximo de recepciones a procesar}
                            {--incluir-importadas : Incluye origen ANITA_IMPORT}
                            {--solo-anita : Solo sincroniza contab.ctamov en Anita (no toca asiento_movimiento ERP)}
                            {--dry-run : Solo informa diferencias, no actualiza}';

    protected $description = 'Recuadra asientos ERP (Σ cant×precio) y sincroniza contab.ctamov en Anita';

    public function handle(RecepcionProveedorAsientoRecuadreService $service): int
    {
        $opciones = [
            'id' => $this->option('id') ? (int) $this->option('id') : null,
            'limite' => $this->option('limite') ? (int) $this->option('limite') : null,
            'dry_run' => (bool) $this->option('dry-run'),
            'incluir_importadas' => (bool) $this->option('incluir-importadas'),
            'solo_anita' => (bool) $this->option('solo-anita'),
        ];

        if ($opciones['dry_run']) {
            $this->warn('Dry-run: no se modificarán asientos ERP ni ctamov Anita.');
        }

        if ($opciones['solo_anita']) {
            $this->info('Modo solo-anita: solo contab.ctamov en Anita.');
        } elseif (! $opciones['incluir_importadas']) {
            $this->info('Alcance: recepciones cargadas en anitaERP (excluye ANITA_IMPORT).');
        }

        $total = $service->contarCandidatas($opciones['incluir_importadas'], $opciones['id']);
        $this->info("Recepciones candidatas: {$total}");

        if ($total === 0) {
            return self::SUCCESS;
        }

        $stats = $service->ejecutar($opciones);

        $cambios = array_values(array_filter(
            $stats['detalle'],
            static fn (array $row): bool => in_array($row['estado'] ?? '', ['pendiente', 'actualizada_erp', 'actualizada_anita', 'actualizada_erp_anita'], true)
        ));

        if ($cambios !== []) {
            $this->table(
                ['COM', 'Estado', 'Debe ERP', 'Debe Anita', 'Esperado', 'Δ ERP', 'Δ Anita'],
                array_map(static fn (array $row) => [
                    $row['numerorecepcion'] ?? '',
                    $row['estado'] ?? '',
                    isset($row['debe_erp']) ? number_format((float) $row['debe_erp'], 2, '.', '') : '',
                    $row['debe_anita'] !== null ? number_format((float) $row['debe_anita'], 2, '.', '') : '—',
                    isset($row['debe_esperado']) ? number_format((float) $row['debe_esperado'], 2, '.', '') : '',
                    isset($row['diff_erp']) ? number_format((float) $row['diff_erp'], 2, '.', '') : '',
                    $row['diff_anita'] !== null ? number_format((float) $row['diff_anita'], 2, '.', '') : '—',
                ], $cambios)
            );
        }

        $this->table(['Métrica', 'Cantidad'], [
            ['Candidatas', $stats['candidatas']],
            ['Ya cuadradas (ERP + Anita)', $stats['ya_cuadradas']],
            ['Actualizadas ERP', $stats['actualizadas_erp']],
            ['Actualizadas Anita ctamov', $stats['actualizadas_anita']],
            ['Errores', $stats['errores']],
        ]);

        return $stats['errores'] > 0 ? self::FAILURE : self::SUCCESS;
    }
}
