<?php

namespace App\Console\Commands;

use App\Models\Caja\Caja_Movimiento;
use App\Support\Caja\IngresoEgresoAnitaTesmovSupport;
use App\Support\Caja\IngresoEgresoTransferenciaSupport;
use Illuminate\Console\Command;

class CorregirAnitaTraTesmovTedTehCommand extends Command
{
    protected $signature = 'caja:corregir-anita-tra-tesmov-ted-teh
                            {--desde=2026-09-01 : Fecha inicial YYYY-MM-DD (caja_movimiento.fecha)}
                            {--hasta= : Fecha final YYYY-MM-DD (default hoy)}
                            {--ejecutar : Escribe TED/TEH en tesmov/auxpag Anita (sin este flag solo analiza)}';

    protected $description = 'Pasa tesmov TRA de transferencias a TED (Debe) y TEH (Haber) como a-tesmov.c';

    public function handle(): int
    {
        $desde = (string) $this->option('desde');
        $hasta = (string) ($this->option('hasta') ?: date('Y-m-d'));
        $ejecutar = (bool) $this->option('ejecutar');

        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $desde) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $hasta)) {
            $this->error('Fechas inválidas. Use YYYY-MM-DD.');

            return self::FAILURE;
        }

        $this->line($ejecutar
            ? 'EJECUTAR: actualiza tesmov TRA → TED/TEH y auxpag en Anita.'
            : 'Dry-run: no se escribe en Anita. Use --ejecutar para aplicar.');
        $this->line("TRA IE | fecha {$desde} → {$hasta}");

        $movimientos = Caja_Movimiento::query()
            ->with(['tipotransaccioncajas', 'caja_movimiento_cuentacajas.cuentacajas'])
            ->whereHas('tipotransaccioncajas', function ($q) {
                $q->whereRaw('UPPER(TRIM(abreviatura)) = ?', [IngresoEgresoTransferenciaSupport::ABREV_TRA]);
            })
            ->whereBetween('fecha', [$desde, $hasta])
            ->orderBy('empresa_id')
            ->orderBy('id')
            ->get();

        $this->line('Movimientos ERP: '.$movimientos->count());

        $ok = 0;
        $aCorregir = 0;
        $actualizadas = 0;
        $omitidos = 0;
        $errores = 0;
        $filasTabla = [];

        foreach ($movimientos as $movimiento) {
            try {
                $r = IngresoEgresoAnitaTesmovSupport::corregirTesmovTedTehDesdeMovimiento(
                    $movimiento,
                    $ejecutar
                );
            } catch (\Throwable $e) {
                $errores++;
                $filasTabla[] = [
                    $movimiento->id,
                    $movimiento->numerotransaccion,
                    $movimiento->fecha,
                    $movimiento->empresa_id,
                    'ERROR',
                    $e->getMessage(),
                ];
                $this->error('TRA '.$movimiento->numerotransaccion.' (id '.$movimiento->id.'): '.$e->getMessage());
                continue;
            }

            if ($r['omitido'] !== null) {
                $omitidos++;
                $filasTabla[] = [
                    $r['movimiento_id'],
                    $r['nro'],
                    $movimiento->fecha,
                    $r['empresa'],
                    'omitido',
                    $r['omitido'],
                ];
                continue;
            }

            $ok++;
            $aCorregir += $r['filas_a_corregir'];
            $actualizadas += $r['filas_actualizadas'];
            $detalle = [];
            foreach ($r['filas'] as $fila) {
                if (isset($fila['skip'])) {
                    $detalle[] = $fila['cuenta'].' skip:'.$fila['skip'];
                    continue;
                }
                $nroNew = $fila['tesv_nro_new'] ?? '?';
                $detalle[] = sprintf(
                    '%s %s %d→%s %s',
                    $fila['cuenta'],
                    $fila['lado'],
                    $fila['tesv_nro_old'],
                    $nroNew,
                    $fila['desc_new']
                );
            }
            $filasTabla[] = [
                $r['movimiento_id'],
                $r['nro'],
                $movimiento->fecha,
                $r['empresa'],
                $r['filas_a_corregir'] > 0
                    ? ($ejecutar ? 'actualizado' : 'pendiente')
                    : 'ok',
                implode(' | ', $detalle),
            ];
        }

        if ($filasTabla !== []) {
            $this->table(
                ['IE id', 'TRA', 'Fecha', 'Emp Anita', 'Estado', 'Detalle'],
                $filasTabla
            );
        }

        $this->line(sprintf(
            'OK: %d | Filas tesmov a corregir: %d | Actualizadas: %d | Omitidos: %d | Errores: %d',
            $ok,
            $aCorregir,
            $actualizadas,
            $omitidos,
            $errores
        ));

        return $errores === 0 ? self::SUCCESS : self::FAILURE;
    }
}
