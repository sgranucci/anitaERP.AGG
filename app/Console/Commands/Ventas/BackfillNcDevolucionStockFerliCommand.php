<?php

declare(strict_types=1);

namespace App\Console\Commands\Ventas;

use App\Models\Ventas\Venta;
use App\Support\Configuracion\EntornoEmpresaSupport;
use App\Support\Ventas\PedidoPickingFerliSupport;
use Illuminate\Console\Command;

/**
 * Backfill del alta «Devolución NC» Ferli para notas emitidas antes del 2/nov/2026.
 */
class BackfillNcDevolucionStockFerliCommand extends Command
{
    protected $signature = 'ventas:backfill-nc-devolucion-stock-ferli
        {venta_id : ID de la nota de crédito (venta.id)}
        {--dry-run : Simula sin grabar}
        {--ejecutar : Persiste el alta de stock}';

    protected $description = 'Ferli: genera el alta Devolución NC faltante para una NC de OT/pedido.';

    public function handle(): int
    {
        if (! EntornoEmpresaSupport::esFerli()) {
            $this->error('Solo corre en Ferli.');

            return self::FAILURE;
        }

        $ventaId = (int) $this->argument('venta_id');
        $dryRun = (bool) $this->option('dry-run') || ! $this->option('ejecutar');
        $ejecutar = (bool) $this->option('ejecutar');

        if ($ejecutar && $this->option('dry-run')) {
            $this->error('No combines --dry-run con --ejecutar.');

            return self::FAILURE;
        }

        $venta = Venta::query()->with('tipotransacciones')->find($ventaId);
        if (! $venta) {
            $this->error("No existe venta id {$ventaId}.");

            return self::FAILURE;
        }
        if (! $venta->tipotransacciones || ! $venta->tipotransacciones->esNotaCredito()) {
            $this->error("La venta {$ventaId} ({$venta->codigo}) no es una nota de crédito.");

            return self::FAILURE;
        }

        $preview = PedidoPickingFerliSupport::previewIngresoMercaderiaDevuelta($ventaId);
        $this->info("NC {$venta->codigo} (id {$ventaId}) fecha {$venta->fecha}");
        $this->info('Modo: '.($dryRun ? 'dry-run (sin grabar)' : 'EJECUTAR (persiste)'));

        if ($preview === []) {
            $this->warn('Sin líneas de pedido/OT en la NC, o picking Ferli deshabilitado.');

            return self::SUCCESS;
        }

        $this->table(
            ['PC', 'OT', 'Art', 'Comb', 'Dep', 'Cant NC', 'Ya', 'Falta', 'Pend.consumo', 'Compañero', 'Acción'],
            array_map(static fn (array $f) => [
                $f['pedido_combinacion_id'],
                $f['ot_codigo'] !== '' ? $f['ot_codigo'] : $f['ordentrabajo_id'],
                $f['articulo_id'],
                $f['combinacion_id'],
                $f['deposito_id'],
                $f['cantidad_nc'],
                $f['ya_ingresado'],
                $f['falta'],
                $f['pendiente_consumo'],
                $f['companero'] ? 'Sí' : 'No',
                $f['accion'],
            ], $preview)
        );

        foreach ($preview as $fila) {
            if (($fila['talles'] ?? []) === []) {
                continue;
            }
            $this->line(
                '  talles PC '.$fila['pedido_combinacion_id'].': '
                .collect($fila['talles'])
                    ->map(static fn ($t) => 'talle '.$t['talle_id'].'×'.$t['cantidad'])
                    ->implode(', ')
            );
        }

        $aCrear = collect($preview)->where('accion', '!=', 'nada')->where('falta', '>', 0.0001);
        if ($aCrear->isEmpty()) {
            $this->comment('Nada para crear: la NC ya tiene el ingreso o no aplica alta.');

            return self::SUCCESS;
        }

        if ($dryRun) {
            $this->warn('Dry-run OK. Para persistir: php artisan ventas:backfill-nc-devolucion-stock-ferli '.$ventaId.' --ejecutar');

            return self::SUCCESS;
        }

        $ingresos = PedidoPickingFerliSupport::ingresarMercaderiaDevuelta(
            $ventaId,
            $venta->fecha ? (string) $venta->fecha : null
        );
        $this->info("Movimientos de ingreso creados/afectados: {$ingresos}");

        $despues = PedidoPickingFerliSupport::previewIngresoMercaderiaDevuelta($ventaId);
        $this->table(
            ['PC', 'Ya ingresado', 'Falta', 'Acción'],
            array_map(static fn (array $f) => [
                $f['pedido_combinacion_id'],
                $f['ya_ingresado'],
                $f['falta'],
                $f['accion'],
            ], $despues)
        );

        return self::SUCCESS;
    }
}
