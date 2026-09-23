<?php

namespace App\Console\Commands\Compras;

use App\Support\Compras\PagoproveedorComprobanteDesdeAplicacionBackfillSupport;
use Illuminate\Console\Command;

/**
 * Completa `pagoproveedor_comprobante` desde apps de CC (import Anita).
 * Default dry-run; requiere --ejecutar para persistir.
 */
class PagoproveedorComprobanteDesdeAplicacionBackfillCommand extends Command
{
    protected $signature = 'compras:backfill-pagoproveedor-comprobante
                            {--desde=2026-01-01 : Fecha ISO desde (app.fecha o deuda.fecha)}
                            {--hasta=2026-12-31 : Fecha ISO hasta}
                            {--ejecutar : Persiste; sin esto solo dry-run}';

    protected $description = 'Backfill pagoproveedor_comprobante desde aplicaciones CC (Anita→ERP) para enganchar Pagos del legajo';

    public function handle(): int
    {
        $desde = (string) $this->option('desde');
        $hasta = (string) $this->option('hasta');
        $ejecutar = (bool) $this->option('ejecutar');

        $this->info(($ejecutar ? 'EJECUTAR' : 'DRY-RUN')." pagoproveedor_comprobante desde apps {$desde}..{$hasta}");

        $stats = PagoproveedorComprobanteDesdeAplicacionBackfillSupport::ejecutar($desde, $hasta, ! $ejecutar);

        $this->table(['Métrica', 'Cantidad'], [
            ['Apps candidatas (filas)', $stats['candidatas']],
            ['Resueltas por etiqueta OPP/OPA', $stats['resueltas_por_etiqueta'] ?? 0],
            ['Pares OP↔deuda a crear', $stats['a_crear']],
            ['Creados', $stats['creadas']],
            ['Omitidos (ya existen)', $stats['omitidas_ya_existen']],
        ]);

        if ($stats['muestra'] !== []) {
            $this->line('Muestra (hasta 25):');
            $this->table(
                ['OP id', 'CC deuda', 'CP', 'Monto'],
                array_map(static fn (array $r) => [
                    $r['pagoproveedor_id'],
                    $r['proveedor_cuentacorriente_id'],
                    $r['cp_id'],
                    number_format($r['montoaplicado'], 2, ',', '.'),
                ], $stats['muestra'])
            );
        }

        if (! $ejecutar) {
            $this->comment('Dry-run: no se grabó nada. Relanzá con --ejecutar para persistir.');
        }

        return self::SUCCESS;
    }
}
