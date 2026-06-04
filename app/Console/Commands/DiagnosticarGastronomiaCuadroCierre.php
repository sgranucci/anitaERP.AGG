<?php

namespace App\Console\Commands;

use App\Services\Ventas\Gastronomia\GastronomiaCierreJornadaProcesoService;
use App\Support\Ventas\Gastronomia\CierreJornadaCuadroDetalleSupport;
use Illuminate\Console\Command;
use InvalidArgumentException;
use Throwable;

/**
 * Lista las comandas / facturas que componen una celda del cuadro de cierre (fila × medio).
 * Útil para conciliar totales QR, MP, etc. contra Waitry con fecha y hora de cada orden.
 */
class DiagnosticarGastronomiaCuadroCierre extends Command
{
    protected $signature = 'gastronomia:diagnostico-cuadro-cierre
                            {empresa : ID empresa}
                            {fecha : Fecha jornada YYYY-MM-DD}
                            {fila : anita_jornada|anita_totem|waitry_pago|waitry_impago|waitry_cash}
                            {medio : qr|mp|efectivo|otros}
                            {--csv= : Exportar todo a CSV (ruta destino)}
                            {--por-pagina=200 : Registros por página al consultar}';

    protected $description = 'Detalle de comandas/facturas por celda del cuadro de cierre Waitry (fecha/hora para conciliar)';

    public function handle(GastronomiaCierreJornadaProcesoService $procesoService): int
    {
        $empresaId = (int) $this->argument('empresa');
        $fecha = (string) $this->argument('fecha');
        $fila = mb_strtolower(trim((string) $this->argument('fila')));
        $medio = mb_strtolower(trim((string) $this->argument('medio')));
        $porPagina = max(10, min(500, (int) $this->option('por-pagina')));

        if ($empresaId <= 0) {
            $this->error('Empresa inválida.');

            return self::FAILURE;
        }

        try {
            $pagina = 1;
            $items = [];
            $resumen = null;

            do {
                $lote = $procesoService->detalleCuadroCeldaPorEmpresaYFecha(
                    $empresaId,
                    $fecha,
                    $fila,
                    $medio,
                    $pagina,
                    $porPagina,
                );
                $resumen = $lote;
                $items = array_merge($items, $lote['items'] ?? []);
                $pagina++;
            } while ($pagina <= (int) ($lote['total_paginas'] ?? 1));
        } catch (InvalidArgumentException $e) {
            $this->error($e->getMessage());
            $this->line('Filas: '.implode(', ', CierreJornadaCuadroDetalleSupport::FILAS));
            $this->line('Medios: '.implode(', ', CierreJornadaCuadroDetalleSupport::MEDIOS));

            return self::FAILURE;
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info(sprintf(
            'Empresa %d · jornada %s · %s / %s',
            $empresaId,
            $fecha,
            $resumen['etiqueta_fila'] ?? $fila,
            $resumen['etiqueta_medio'] ?? $medio,
        ));

        if (! empty($resumen['meta']['ventana_operativa'])) {
            $this->line('Ventana operativa: '.$resumen['meta']['ventana_operativa']);
        }
        if (! empty($resumen['meta']['consulta_waitry_rango'])) {
            $this->line('Rango API Waitry: '.$resumen['meta']['consulta_waitry_rango']);
        }

        $this->line(sprintf(
            'Registros: %d · Total: $ %s',
            (int) ($resumen['total_registros'] ?? count($items)),
            number_format((float) ($resumen['total_importe'] ?? 0), 2, ',', '.'),
        ));
        $this->newLine();

        $csvPath = trim((string) $this->option('csv'));
        if ($csvPath !== '') {
            $this->exportarCsv($csvPath, $items);
            $this->info('CSV exportado: '.$csvPath);

            return self::SUCCESS;
        }

        if ($items === []) {
            $this->warn('Sin registros en esta celda.');

            return self::SUCCESS;
        }

        $this->table(
            ['#Waitry', 'Ref.', 'Fecha/hora', 'Total', 'Tipo pago Waitry', 'Factura Anita', 'Origen'],
            array_map(fn (array $it) => [
                $it['waitry_order_id'] ?? '—',
                $it['display_id'] ?? '',
                $it['fecha_hora_fmt'] ?? '—',
                number_format((float) ($it['total'] ?? 0), 2, ',', '.'),
                $it['waitry_medio_label'] ?? ($it['waitry_tipo_pago'] ?? '—'),
                $it['venta_codigo'] ?? '—',
                $it['fuente'] ?? '',
            ], $items),
        );

        return self::SUCCESS;
    }

    /**
     * @param  list<array<string, mixed>>  $items
     */
    private function exportarCsv(string $path, array $items): void
    {
        $fh = fopen($path, 'w');
        if ($fh === false) {
            throw new InvalidArgumentException('No se pudo escribir CSV en: '.$path);
        }

        fputcsv($fh, [
            'waitry_order_id',
            'display_id',
            'fecha_hora',
            'total',
            'waitry_tipo_pago',
            'waitry_medio_label',
            'venta_codigo',
            'medio_anita_label',
            'facturada_erp',
            'paid_waitry',
            'origen',
            'fuente',
        ], ';');

        foreach ($items as $it) {
            fputcsv($fh, [
                $it['waitry_order_id'] ?? '',
                $it['display_id'] ?? '',
                $it['fecha_hora_raw'] ?? '',
                $it['total'] ?? '',
                $it['waitry_tipo_pago'] ?? '',
                $it['waitry_medio_label'] ?? '',
                $it['venta_codigo'] ?? '',
                $it['medio_anita_label'] ?? '',
                ! empty($it['facturada_erp']) ? '1' : '0',
                $it['paid_waitry'] === null ? '' : ($it['paid_waitry'] ? '1' : '0'),
                $it['origen'] ?? '',
                $it['fuente'] ?? '',
            ], ';');
        }

        fclose($fh);
    }
}
