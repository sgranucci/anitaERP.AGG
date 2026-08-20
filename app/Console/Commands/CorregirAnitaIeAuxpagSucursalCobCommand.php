<?php

namespace App\Console\Commands;

use App\Models\Caja\Caja_Movimiento;
use App\Support\Caja\IngresoEgresoAnitaTesmovSupport;
use Illuminate\Console\Command;

class CorregirAnitaIeAuxpagSucursalCobCommand extends Command
{
    protected $signature = 'caja:corregir-anita-ie-auxpag-sucursal-cob
                            {--desde=2026-08-01 : Fecha inicial YYYY-MM-DD (caja_movimiento.fecha)}
                            {--hasta=2026-08-31 : Fecha final YYYY-MM-DD}
                            {--ejecutar : Escribe axp_sucursal_cob en Anita (sin este flag solo analiza)}';

    protected $description = 'Pone empresa (sucursal MultiEmpresa) en auxpag.axp_sucursal_cob de OPP de Ingresos/Egresos';

    public function handle(): int
    {
        $desde = (string) $this->option('desde');
        $hasta = (string) $this->option('hasta');
        $ejecutar = (bool) $this->option('ejecutar');

        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $desde) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $hasta)) {
            $this->error('Fechas inválidas. Use YYYY-MM-DD.');

            return self::FAILURE;
        }

        $this->line($ejecutar
            ? 'EJECUTAR: actualiza axp_sucursal_cob en Anita.'
            : 'Dry-run: no se escribe en Anita. Use --ejecutar para aplicar.');
        $this->line("OPP IE | fecha {$desde} → {$hasta}");

        $movimientos = Caja_Movimiento::query()
            ->with(['tipotransaccioncajas'])
            ->whereHas('tipotransaccioncajas', function ($q) {
                $q->whereRaw('UPPER(TRIM(abreviatura)) = ?', ['OPP']);
            })
            ->whereBetween('fecha', [$desde, $hasta])
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
                $r = IngresoEgresoAnitaTesmovSupport::corregirSucursalCobDesdeMovimiento(
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
                $this->error('OPP '.$movimiento->numerotransaccion.' (id '.$movimiento->id.'): '.$e->getMessage());
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
            $filasTabla[] = [
                $r['movimiento_id'],
                $r['nro'],
                $movimiento->fecha,
                $r['empresa'],
                $r['filas_a_corregir'] > 0
                    ? ($ejecutar ? 'actualizado' : 'pendiente')
                    : 'ok',
                sprintf(
                    'anita=%d cob_mal=%d upd=%d suc=%d',
                    $r['filas_anita'],
                    $r['filas_a_corregir'],
                    $r['filas_actualizadas'],
                    $r['sucursal']
                ),
            ];
        }

        if ($filasTabla !== []) {
            $this->table(
                ['IE id', 'OPP', 'Fecha', 'Emp Anita', 'Estado', 'Detalle'],
                $filasTabla
            );
        }

        $this->line(sprintf(
            'OK: %d | Con axp_sucursal_cob a corregir: %d filas | Actualizadas: %d | Omitidos: %d | Errores: %d',
            $ok,
            $aCorregir,
            $actualizadas,
            $omitidos,
            $errores
        ));

        return $errores === 0 ? self::SUCCESS : self::FAILURE;
    }
}
