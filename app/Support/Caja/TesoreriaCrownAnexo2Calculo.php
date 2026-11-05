<?php

namespace App\Support\Caja;

use App\Models\Caja\TesoreriaCrownJornada;
use App\Models\Caja\TesoreriaCrownMesa;
use App\Models\Caja\TesoreriaCrownValor;

/**
 * Anexo II / l-resmesa.c: resultado diario por mesa.
 *
 * Creditos, reposiciones, estado final, drop, propina (caja empl.),
 * recomposición de banca y resultado. Sin pagos manuales de progresivo
 * (Anita los lee de fichmm_progresivo; acá quedan en cero).
 */
final class TesoreriaCrownAnexo2Calculo
{
    /**
     * @return array{
     *     filas: list<array<string, mixed>>,
     *     totales: array<string, float>,
     *     avisos: list<string>
     * }
     */
    public static function deJornada(TesoreriaCrownJornada $jornada): array
    {
        $fecha = $jornada->fecha->format('Y-m-d');
        $salaId = (int) $jornada->sala_id;
        $tablero = TesoreriaCrownMesaSupport::tablero($jornada);
        $valores = TesoreriaCrownValor::query()
            ->where('sala_id', $salaId)
            ->get(['id', 'denominacion'])
            ->keyBy('id');

        $filas = [];
        $totales = self::ceros();
        foreach ($tablero['mesas'] as $filaTablero) {
            $mesa = $filaTablero['mesa'];
            $banca = $filaTablero['banca'];
            $montos = self::montosMesa((int) $mesa->id, $fecha, $valores);
            if (abs($montos['habilitacion']) < 0.001) {
                // Sin alta A del día: la banca tipica es la apertura (estado final de ayer).
                $stock = TesoreriaCrownMesaSupport::stock((int) $mesa->id, $salaId, $fecha);
                $montos['habilitacion'] = self::montoDeCantidades($stock['apertura'], $valores);
            }
            if (self::vacia($montos)) {
                continue;
            }
            $recomp = round(
                $montos['estado_final'] + $montos['drop'] - $montos['habilitacion']
                + $montos['caja_empleado'] - $montos['pago_manual'],
                2
            );
            $resultado = round(
                $recomp - $montos['reposicion'] + $montos['credito']
                - (2 * $montos['caja_empleado']),
                2
            );
            $pctMesa = abs($montos['drop']) > 0.001
                ? round($resultado / $montos['drop'] * 100, 2)
                : null;
            $pctProp = abs($montos['drop']) > 0.001
                ? round($montos['caja_empleado'] / $montos['drop'] * 100, 2)
                : null;
            $fila = [
                'juego' => (string) ($banca->nombre ?? $banca->descripcion ?? ''),
                'mesa' => (string) $mesa->codigo,
                'banca' => $banca !== null ? (string) $banca->codigo : '',
                'creditos' => $montos['credito'],
                'reposiciones' => $montos['reposicion'],
                'estado_final' => $montos['estado_final'],
                'drop' => $montos['drop'],
                'pagos_manuales' => $montos['pago_manual'],
                'recomp_banca' => $recomp,
                'caja_empleado' => $montos['caja_empleado'],
                'resultado' => $resultado,
                'pct_mesa' => $pctMesa,
                'pct_prop' => $pctProp,
            ];
            $filas[] = $fila;
            foreach (['creditos', 'reposiciones', 'estado_final', 'drop', 'pagos_manuales', 'recomp_banca', 'caja_empleado', 'resultado'] as $clave) {
                $totales[$clave] = round($totales[$clave] + (float) $fila[$clave], 2);
            }
        }

        return [
            'filas' => $filas,
            'totales' => $totales,
            'avisos' => $filas === []
                ? ['No hay movimientos de mesa confirmados en esta jornada.']
                : [],
        ];
    }

    /**
     * @return array{habilitacion: float, reposicion: float, credito: float, estado_final: float, drop: float, caja_empleado: float, pago_manual: float}
     */
    private static function montosMesa(int $depositoId, string $fechaYmd, $valores): array
    {
        $out = [
            'habilitacion' => 0.0,
            'reposicion' => 0.0,
            'credito' => 0.0,
            'estado_final' => 0.0,
            'drop' => 0.0,
            'caja_empleado' => 0.0,
            'pago_manual' => 0.0,
        ];
        $mapa = [
            'A' => 'habilitacion',
            'R' => 'reposicion',
            'C' => 'credito',
            'F' => 'estado_final',
            'D' => 'drop',
            'P' => 'caja_empleado',
        ];
        $movimientos = TesoreriaCrownMesa::query()
            ->with(['lineas', 'reversa'])
            ->where('deposito_id', $depositoId)
            ->where('estado', TesoreriaCrownMesa::CONFIRMADO)
            ->whereNull('revierte_id')
            ->whereDoesntHave('reversa')
            ->whereHas('jornada', fn ($q) => $q->whereDate('fecha', $fechaYmd))
            ->get();

        foreach ($movimientos as $movimiento) {
            $clave = $mapa[$movimiento->tipo] ?? null;
            if ($clave === null) {
                continue;
            }
            $monto = 0.0;
            foreach ($movimiento->lineas as $linea) {
                $valor = $valores->get((int) $linea->valor_id);
                if ($valor === null) {
                    continue;
                }
                $monto += (float) $linea->cantidad * (float) $valor->denominacion;
            }
            $out[$clave] = round($out[$clave] + $monto, 2);
        }

        return $out;
    }

    /**
     * @param  array<int, float>  $cantidades
     */
    private static function montoDeCantidades(array $cantidades, $valores): float
    {
        $total = 0.0;
        foreach ($cantidades as $valorId => $cantidad) {
            $valor = $valores->get((int) $valorId);
            if ($valor === null) {
                continue;
            }
            $total += (float) $cantidad * (float) $valor->denominacion;
        }

        return round($total, 2);
    }

    /** @param  array<string, float>  $montos */
    private static function vacia(array $montos): bool
    {
        foreach ($montos as $importe) {
            if (abs($importe) > 0.001) {
                return false;
            }
        }

        return true;
    }

    /** @return array<string, float> */
    private static function ceros(): array
    {
        return [
            'creditos' => 0.0,
            'reposiciones' => 0.0,
            'estado_final' => 0.0,
            'drop' => 0.0,
            'pagos_manuales' => 0.0,
            'recomp_banca' => 0.0,
            'caja_empleado' => 0.0,
            'resultado' => 0.0,
        ];
    }
}
