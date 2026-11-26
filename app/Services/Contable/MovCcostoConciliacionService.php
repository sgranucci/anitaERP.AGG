<?php

namespace App\Services\Contable;

use App\Support\Contable\MovCcosto\MovCcostoConciliacionProcesador;
use App\Support\Contable\MovCcosto\MovCcostoRuntimeSupport;
use Illuminate\Pagination\LengthAwarePaginator;

class MovCcostoConciliacionService
{
    public function __construct(
        private readonly MovCcostoConciliacionProcesador $procesador,
        private readonly MovCcostoReporteService $reporteService,
    ) {
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return array<string, mixed>
     */
    public function generarDesdeFiltros(array $filtros): array
    {
        MovCcostoRuntimeSupport::elevarLimites();

        $empresaIds = array_values(array_filter(
            array_map('intval', $filtros['empresa_ids'] ?? []),
            static fn (int $id): bool => $id > 0,
        ));

        return $this->procesador->generar($empresaIds, $filtros);
    }

    /**
     * Lista plana para pantalla / export.
     *
     * Detalle: por cuenta, encabezado + movimientos + total de la cuenta. Resumen: una fila por
     * cuenta. Las dos terminan con el total general.
     *
     * @param  array<string, mixed>  $resultado
     * @return list<array<string, mixed>>
     */
    public function aplanarFilas(array $resultado, bool $soloResumen): array
    {
        $cuentas = $resultado['cuentas'] ?? [];
        if (! is_array($cuentas) || $cuentas === []) {
            return [];
        }

        $out = [];
        foreach ($cuentas as $cuenta) {
            $base = [
                'empresa_id' => (int) $cuenta['empresa_id'],
                'empresa_nombre' => (string) $cuenta['empresa_nombre'],
                'cuentacontable_id' => (int) $cuenta['cuentacontable_id'],
                'codigo' => (int) $cuenta['codigo'],
                'codigo_fmt' => (string) $cuenta['codigo_fmt'],
                'nombre' => (string) $cuenta['nombre'],
                'cantidad' => (int) $cuenta['cantidad'],
                'debe' => (float) $cuenta['debe'],
                'haber' => (float) $cuenta['haber'],
            ];

            if ($soloResumen) {
                $out[] = ['tipo_fila' => 'resumen'] + $base;

                continue;
            }

            $out[] = ['tipo_fila' => 'cuenta'] + $base;
            foreach ($cuenta['movimientos'] as $mov) {
                $out[] = ['tipo_fila' => 'movimiento', 'codigo' => (int) $cuenta['codigo']] + $mov;
            }
            $out[] = ['tipo_fila' => 'total_cuenta'] + $base;
        }

        $totales = $resultado['totales'] ?? [];
        $out[] = [
            'tipo_fila' => 'total_general',
            'codigo_fmt' => '',
            'nombre' => 'Total general',
            'cantidad' => (int) ($totales['movimientos'] ?? 0),
            'debe' => (float) ($totales['debe'] ?? 0),
            'haber' => (float) ($totales['haber'] ?? 0),
        ];

        return $out;
    }

    /**
     * @param  list<array<string, mixed>>  $filas
     */
    public function paginarFilas(array $filas, int $perPage = 50): LengthAwarePaginator
    {
        return $this->reporteService->paginarFilas($filas, $perPage);
    }

    public function reporte(): MovCcostoReporteService
    {
        return $this->reporteService;
    }
}
