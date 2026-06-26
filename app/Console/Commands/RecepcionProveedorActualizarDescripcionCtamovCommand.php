<?php

namespace App\Console\Commands;

use App\Services\Stock\RecepcionProveedorAsientoDescripcionCtamovService;
use Illuminate\Console\Command;

class RecepcionProveedorActualizarDescripcionCtamovCommand extends Command
{
    protected $signature = 'recepcion-proveedor:actualizar-descripcion-ctamov
                            {--id= : Solo una recepción por ID ERP}
                            {--limite= : Máximo de recepciones a procesar}
                            {--incluir-importadas : Incluye origen ANITA_IMPORT}
                            {--solo-anita : Solo re-sincroniza contab.ctamov en Anita (no toca observaciones ERP)}
                            {--dry-run : Solo informa, no actualiza}';

    protected $description = 'Actualiza leyenda Rec. #COM proveedor en ctav_desc_mov y cabecera asiento ERP';

    public function handle(RecepcionProveedorAsientoDescripcionCtamovService $service): int
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
                ['COM', 'Estado', 'Descripción ctamov'],
                array_map(static fn (array $row) => [
                    $row['numerorecepcion'] ?? '',
                    $row['estado'] ?? '',
                    $row['descripcion_ctamov'] ?? '',
                ], $cambios)
            );
        }

        $this->table(['Métrica', 'Cantidad'], [
            ['Candidatas', $stats['candidatas']],
            ['Ya con leyenda correcta', $stats['ya_ok']],
            ['Actualizadas ERP', $stats['actualizadas_erp']],
            ['Actualizadas Anita ctamov', $stats['actualizadas_anita']],
            ['Errores', $stats['errores']],
        ]);

        return $stats['errores'] > 0 ? self::FAILURE : self::SUCCESS;
    }
}
