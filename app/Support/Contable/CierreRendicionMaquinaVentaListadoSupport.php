<?php

namespace App\Support\Contable;

use App\Models\Caja\RendicionMaquina;
use App\Models\Configuracion\Empresa;
use App\Support\Caja\RendicionMaquina\RendicionMaquinaTurno;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use InvalidArgumentException;

/**
 * Listado «Venta de máquinas» (p-vtamaquina.c LISTADO / ENCAB1).
 *
 * Una fila por jornada con Completo (turno C), mismos totales que el cierre contable.
 */
final class CierreRendicionMaquinaVentaListadoSupport
{
    /**
     * @return array<string, mixed>
     */
    public function generar(int $empresaId, string $fechaDesde, string $fechaHasta): array
    {
        if ($empresaId <= 0) {
            throw new InvalidArgumentException('Indique empresa.');
        }

        $desde = Carbon::parse($fechaDesde)->toDateString();
        $hasta = Carbon::parse($fechaHasta)->toDateString();
        if ($desde > $hasta) {
            throw new InvalidArgumentException('El rango de fechas es inválido.');
        }

        $empresa = Empresa::query()->find($empresaId);
        if ($empresa === null) {
            throw new InvalidArgumentException('Empresa inexistente.');
        }

        $rendiciones = RendicionMaquina::query()
            ->where('empresa_id', $empresaId)
            ->where('estado', '!=', RendicionMaquina::ESTADO_ANULADA)
            ->whereDate('fecha', '>=', $desde)
            ->whereDate('fecha', '<=', $hasta)
            ->with(['valores.cuentacaja.monedas', 'valores.cuentacaja.cuentacontables', 'gastos.aperturaGasto'])
            ->orderBy('fecha')
            ->orderBy('id')
            ->get();

        /** @var array<string, EloquentCollection<int, RendicionMaquina>> $porDia */
        $porDia = [];
        foreach ($rendiciones as $r) {
            $f = $r->fecha?->format('Y-m-d');
            if ($f === null || $f === '') {
                continue;
            }
            if (! isset($porDia[$f])) {
                $porDia[$f] = new EloquentCollection;
            }
            $porDia[$f]->push($r);
        }

        $filas = [];
        $totales = $this->filaCero();

        $cursor = Carbon::parse($desde)->startOfDay();
        $fin = Carbon::parse($hasta)->startOfDay();
        while ($cursor->lte($fin)) {
            $fechaDia = $cursor->toDateString();
            $coleccion = $porDia[$fechaDia] ?? new EloquentCollection;
            $tieneCompleto = $coleccion->contains(
                fn (RendicionMaquina $r) => (string) ($r->turno ?? '') === RendicionMaquinaTurno::COMPLETO,
            );
            if (! $tieneCompleto) {
                $cursor->addDay();
                continue;
            }

            $tot = CierreRendicionMaquinaTotalesSupport::calcular($coleccion, $empresaId, $fechaDia);
            $fila = $this->filaDesdeTotales($fechaDia, $tot);
            $filas[] = $fila;
            $this->acumular($totales, $fila);
            $cursor->addDay();
        }

        $totales['fecha'] = null;
        $totales['fecha_fmt'] = 'Total';
        $totales['es_total'] = true;
        $totales['cot_dolar'] = 0.0;
        $totales['cot_euro'] = 0.0;

        return [
            'empresa_id' => $empresaId,
            'empresa_nombre' => (string) ($empresa->nombre ?? ''),
            'empresa_codigo' => (string) ($empresa->codigo ?? ''),
            'fecha_desde' => $desde,
            'fecha_hasta' => $hasta,
            'cantidad_dias' => count($filas),
            'filas' => $filas,
            'totales' => $totales,
        ];
    }

    /**
     * @param  array<string, mixed>  $tot
     * @return array<string, mixed>
     */
    private function filaDesdeTotales(string $fechaDia, array $tot): array
    {
        return [
            'fecha' => $fechaDia,
            'fecha_fmt' => Carbon::parse($fechaDia)->format('d/m/Y'),
            'es_total' => false,
            'maquinas' => round((float) ($tot['resultado_real'] ?? 0), 2),
            'total_online' => round((float) ($tot['resultado_online'] ?? 0), 2),
            'diferencia' => round((float) ($tot['diferencia_real_online'] ?? 0), 2),
            'efectivo_euro' => round((float) ($tot['efectivo_euro'] ?? 0), 2),
            'efectivo' => round((float) ($tot['efectivo'] ?? 0), 2),
            'visa' => round((float) ($tot['visa'] ?? 0), 2),
            'master' => round((float) ($tot['master'] ?? 0), 2),
            'mep' => round((float) ($tot['mep'] ?? 0), 2),
            'totalcoin' => round((float) ($tot['totalcoin_caja'] ?? 0), 2),
            'euros' => round((float) ($tot['euros'] ?? 0), 2),
            'cot_euro' => round((float) ($tot['cot_euro'] ?? 0), 4),
            'euros_en_pesos' => round((float) ($tot['euros_en_pesos'] ?? 0), 2),
            'dolares' => round((float) ($tot['dolares'] ?? 0), 2),
            'cot_dolar' => round((float) ($tot['cot_dolar'] ?? 0), 4),
            'dolares_en_pesos' => round((float) ($tot['dolares_en_pesos'] ?? 0), 2),
            'caja_trans' => round((float) ($tot['tot_caja_trans'] ?? 0), 2),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function filaCero(): array
    {
        return [
            'fecha' => null,
            'fecha_fmt' => '',
            'es_total' => false,
            'maquinas' => 0.0,
            'total_online' => 0.0,
            'diferencia' => 0.0,
            'efectivo_euro' => 0.0,
            'efectivo' => 0.0,
            'visa' => 0.0,
            'master' => 0.0,
            'mep' => 0.0,
            'totalcoin' => 0.0,
            'euros' => 0.0,
            'cot_euro' => 0.0,
            'euros_en_pesos' => 0.0,
            'dolares' => 0.0,
            'cot_dolar' => 0.0,
            'dolares_en_pesos' => 0.0,
            'caja_trans' => 0.0,
        ];
    }

    /**
     * @param  array<string, mixed>  $acc
     * @param  array<string, mixed>  $fila
     */
    private function acumular(array &$acc, array $fila): void
    {
        foreach ([
            'maquinas', 'total_online', 'diferencia', 'efectivo_euro', 'efectivo',
            'visa', 'master', 'mep', 'totalcoin', 'euros', 'euros_en_pesos',
            'dolares', 'dolares_en_pesos', 'caja_trans',
        ] as $k) {
            $acc[$k] = round((float) ($acc[$k] ?? 0) + (float) ($fila[$k] ?? 0), 2);
        }
    }
}
